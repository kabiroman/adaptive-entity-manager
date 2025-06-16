<?php

namespace Kabiroman\AEM\Metadata;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Proxy;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\EntityProxy\EntityProxyTrait;
use Kabiroman\AEM\ProxyInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RegexIterator;
use RuntimeException;

class OptimizedEntityMetadataFactory implements ClassMetadataFactory
{
    private const METADATA_LIST_CACHE_KEY = 'aem_metadata_list';
    private const ENTITY_SCAN_CACHE_KEY = 'aem_entity_scan_results';
    
    private array $runtimeCache = [];
    private ?array $cachedEntityList = null;

    public function __construct(
        private readonly Config $config,
        private readonly ClassMetadataProvider $classMetadataProvider,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly int $cacheTtl = 3600
    ) {}

    /**
     * @throws ReflectionException
     */
    public function getAllMetadata(): array
    {
        if ($this->cachedEntityList !== null) {
            return $this->cachedEntityList;
        }

        // Try to load from cache first
        if ($this->cache !== null) {
            try {
                $cacheItem = $this->cache->getItem(self::METADATA_LIST_CACHE_KEY);
                if ($cacheItem->isHit()) {
                    $this->cachedEntityList = $cacheItem->get();
                    return $this->cachedEntityList;
                }
            } catch (InvalidArgumentException $e) {
                // Fall through to scan
            }
        }

        // Scan and build metadata list
        $metadataList = $this->scanAndBuildMetadata();
        $this->cachedEntityList = array_values($metadataList);

        // Store in cache
        $this->storeCachedMetadataList($this->cachedEntityList);

        return $this->cachedEntityList;
    }

    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getMetadataFor(string $className): \Kabiroman\AEM\ClassMetadata
    {
        $className = $this->resolveClassName($className);

        // Runtime cache check
        if (isset($this->runtimeCache[$className])) {
            return $this->runtimeCache[$className];
        }

        // Get metadata from provider (which should be cached)
        $metadata = $this->classMetadataProvider->getClassMetadata($className);
        
        if ($metadata === null) {
            throw new \InvalidArgumentException(
                sprintf('Metadata for class "%s" does not exist.', $className)
            );
        }

        $this->runtimeCache[$className] = $metadata;
        return $metadata;
    }

    public function hasMetadataFor(string $className): bool
    {
        $className = $this->resolveClassName($className);
        
        // Check runtime cache first
        if (isset($this->runtimeCache[$className])) {
            return true;
        }

        // Check if provider can load metadata
        return $this->classMetadataProvider->getClassMetadata($className) !== null;
    }

    /**
     * @throws ReflectionException
     */
    public function setMetadataFor(string $className, ClassMetadata $class): void
    {
        $className = $this->resolveClassName($className);
        $metadata = $this->classMetadataProvider->getClassMetadata($class->getName());
        
        if ($metadata !== null) {
            $this->runtimeCache[$className] = $metadata;
            $this->invalidateCache();
        }
    }

    public function isTransient(string $className): bool
    {
        return !$this->hasMetadataFor($className);
    }

    public function warmUp(): void
    {
        try {
            $this->getAllMetadata();
        } catch (ReflectionException $e) {
            // Log error but don't fail
        }
    }

    public function clearCache(): void
    {
        $this->runtimeCache = [];
        $this->cachedEntityList = null;
        
        if ($this->cache !== null) {
            $this->cache->deleteItems([
                self::METADATA_LIST_CACHE_KEY,
                self::ENTITY_SCAN_CACHE_KEY
            ]);
        }
    }

    private function resolveClassName(string $className): string
    {
        $reflectionClass = new ReflectionClass($className);
        
        if (!empty($interfaces = $reflectionClass->getInterfaces())) {
            foreach ($interfaces as $name => $interface) {
                if ($name === Proxy::class) {
                    if (!$parentClass = $reflectionClass->getParentClass()) {
                        throw new RuntimeException('The proxy class must have a parent entity class.');
                    }
                    return $parentClass->getName();
                }
            }
        }

        return $className;
    }

    /**
     * @throws ReflectionException
     */
    private function scanAndBuildMetadata(): array
    {
        // Try to get cached scan results
        $entityClasses = $this->getCachedEntityScan();
        
        if ($entityClasses === null) {
            $entityClasses = $this->scanEntityFiles();
            $this->storeCachedEntityScan($entityClasses);
        }

        $result = [];
        $associationTargetClasses = [];

        foreach ($entityClasses as $entityClass) {
            if ($classMetadata = $this->classMetadataProvider->getClassMetadata($entityClass)) {
                $result[$entityClass] = $classMetadata;
                
                // Collect association targets for proxy generation
                $associationNames = $classMetadata->getAssociationNames();
                foreach ($associationNames as $associationName) {
                    if ($classMetadata->isSingleValuedAssociation($associationName)) {
                        $associationTargetClass = $classMetadata->getAssociationTargetClass($associationName);
                        $associationTargetClasses[$associationTargetClass] = $associationTargetClass;
                    }
                }
            }
        }

        // Generate proxies for association targets
        foreach ($associationTargetClasses as $associationTargetClass) {
            $this->generateProxy($associationTargetClass);
        }

        return $result;
    }

    private function scanEntityFiles(): array
    {
        $entityClasses = [];
        $targetNamespace = trim($this->config->getEntityNamespace(), '\\');
        $entityFolder = realpath($this->config->getEntityFolder());
        
        if (!$entityFolder) {
            return $entityClasses;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($entityFolder)
        );
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);

        foreach ($regex as $file => $value) {
            $fileContent = file_get_contents(str_replace('\\', '/', $file));
            if ($fileContent === false) {
                continue;
            }

            $current = $this->parseTokens(token_get_all($fileContent));
            if ($current !== false) {
                list($namespace, $class) = $current;
                $namespace = trim($namespace, '\\');
                
                if ($namespace === $targetNamespace) {
                    $entityClasses[] = $namespace . '\\' . $class;
                }
            }
        }

        return $entityClasses;
    }

    private function parseTokens(array $tokens): bool|array
    {
        $nsStart = false;
        $classStart = false;
        $namespace = '';
        
        foreach ($tokens as $token) {
            if ($token[0] === T_CLASS) {
                $classStart = true;
            }
            if ($classStart && $token[0] === T_STRING) {
                return [$namespace, $token[1]];
            }
            if ($token[0] === T_NAMESPACE) {
                $nsStart = true;
            }
            if ($nsStart && $token[0] === ';') {
                $nsStart = false;
            }
            if ($nsStart && (int)$token[0] === T_NAME_QUALIFIED) {
                $namespace .= $token[1] . '\\';
            }
        }

        return false;
    }

    private function getCachedEntityScan(): ?array
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $cacheItem = $this->cache->getItem(self::ENTITY_SCAN_CACHE_KEY);
            return $cacheItem->isHit() ? $cacheItem->get() : null;
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    private function storeCachedEntityScan(array $entityClasses): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $cacheItem = $this->cache->getItem(self::ENTITY_SCAN_CACHE_KEY);
            $cacheItem->set($entityClasses);
            $cacheItem->expiresAfter($this->cacheTtl);
            $this->cache->save($cacheItem);
        } catch (InvalidArgumentException $e) {
            // Ignore cache errors
        }
    }

    private function storeCachedMetadataList(array $metadataList): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $cacheItem = $this->cache->getItem(self::METADATA_LIST_CACHE_KEY);
            $cacheItem->set($metadataList);
            $cacheItem->expiresAfter($this->cacheTtl);
            $this->cache->save($cacheItem);
        } catch (InvalidArgumentException $e) {
            // Ignore cache errors
        }
    }

    private function invalidateCache(): void
    {
        $this->cachedEntityList = null;
        
        if ($this->cache !== null) {
            $this->cache->deleteItems([
                self::METADATA_LIST_CACHE_KEY,
                self::ENTITY_SCAN_CACHE_KEY
            ]);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function generateProxy(string $className): void
    {
        $reflectionClass = new ReflectionClass($className);
        $shortClassName = $reflectionClass->getShortName();
        $proxyFolder = $this->config->getCacheFolder() . '/proxy/entity';
        
        if (!is_dir($proxyFolder)) {
            mkdir($proxyFolder, 0755, true);
        }

        if (file_exists($proxyClassFile = $proxyFolder . '/' . $shortClassName . 'Proxy.php')) {
            return;
        }
        
        ($class = new ClassGenerator())
            ->setName($shortClassName . 'Proxy')
            ->setNamespaceName('Proxy\\Entity')
            ->setExtendedClass($className)
            ->setImplementedInterfaces([ProxyInterface::class])
            ->addTrait('\\' . EntityProxyTrait::class);

        $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC & ~ReflectionMethod::IS_STATIC);
        foreach ($reflectionMethods as $reflectionMethod) {
            if ($reflectionMethod->isConstructor()) {
                continue;
            }
            
            ($method = new MethodGenerator())
                ->setName($methodName = $reflectionMethod->getName());
            
            $methodParamNames = [];
            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                $method->setParameter($methodParamName = $reflectionParameter->getName());
                $methodParamNames[] = '$' . $methodParamName;
            }
            
            $returnLine = '';
            $reflectionReturnType = $reflectionMethod->getReturnType();
            if ($reflectionReturnType !== null && $reflectionReturnType->getName() !== 'void') {
                $returnLine = sprintf(
                    'return $this->original->%s(%s);' . PHP_EOL,
                    $methodName,
                    implode(',', $methodParamNames)
                );
            }
            
            $method->setBody(sprintf('$this->__load();' . PHP_EOL . '%s', $returnLine));
            
            if ($reflectionReturnType !== null) {
                $method->setReturnType($reflectionReturnType);
            }
            
            $class->addMethodFromGenerator($method);
        }
        
        $file = new FileGenerator();
        $file->setClass($class);

        file_put_contents($proxyClassFile, $file->generate());
    }
}
