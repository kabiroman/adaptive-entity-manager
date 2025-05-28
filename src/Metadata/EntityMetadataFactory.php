<?php

namespace Kabiroman\AEM\Metadata;

use App\Persistence\EntityProxyTrait;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Proxy;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\ProxyInterface;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\FileGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Psr\Cache\InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use RegexIterator;
use RuntimeException;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpArrayAdapter;

class EntityMetadataFactory implements ClassMetadataFactory
{
    private readonly PhpArrayAdapter $cache;

    /**
     * @throws ReflectionException
     */
    public function __construct(
        private readonly Config $config,
        private readonly ClassMetadataProvider $classMetadataProvider,
    ) {
        $cacheFile = $this->config->getCacheFolder() . '/entity_metadata.cache';
        $this->cache = new PhpArrayAdapter($cacheFile, new FilesystemAdapter());
        if (!file_exists($cacheFile)) {
            $this->cacheWarmUp();
        }
    }

    /**
     * @throws ReflectionException
     */
    public function getAllMetadata(): array
    {
        return array_values($this->getResult());
    }

    /**
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    public function getMetadataFor(string $className): \Kabiroman\AEM\ClassMetadata
    {
        $reflectionClass = new ReflectionClass($className);
        if (!empty($interfaces = $reflectionClass->getInterfaces())) {
            foreach ($interfaces as $name => $interface) {
                if ($name === Proxy::class) {
                    if (!$parentClass = $reflectionClass->getParentClass()) {
                        throw new RuntimeException('The proxy class must have a parent entity class.');
                    }
                    $className = $parentClass->getName();
                }
            }
        }

        return $this->getCached($className) ?? throw new \InvalidArgumentException(
            sprintf('Metadata for class "%s" does not exist.', $className)
        );
    }

    public function hasMetadataFor(string $className): bool
    {
        try {
            $this->cache->getItem($this->hashKey($className))->get();
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * @throws ReflectionException
     */
    public function setMetadataFor(string $className, ClassMetadata $class): void
    {
        $storage = $this->getAllMetadata();
        $storage[$className] = $this->classMetadataProvider->getClassMetadata($class->getName());

        $this->cache->warmUp($storage);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isTransient(string $className): bool
    {
        if ($this->hasMetadataFor($className)) {
            $entity = $this->getCached($className);
            if (method_exists($entity, 'getMetadata')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getCached(string $key): ?\Kabiroman\AEM\ClassMetadata
    {
        return $this->cache->getItem($this->hashKey($key))->get();
    }

    /**
     * @throws ReflectionException
     */
    private function cacheWarmUp(): void
    {
        $result = $this->getResult();
        if (!empty($result)) {
            $this->cache->warmUp($result);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function getResult(): array
    {
        $result = [];
        $check = $this->config->getEntityNamespace();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(realpath($this->config->getEntityFolder()))
        );
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);
        $associationTargetClasses = [];
        foreach ($regex as $file => $value) {
            $current = $this->parseTokens(token_get_all(file_get_contents(str_replace('\\', '/', $file))));
            if ($current !== false) {
                list($namespace, $class) = $current;
                if ($namespace === $check) {
                    if ($classMetadata = $this->classMetadataProvider->getClassMetadata($namespace . $class)) {
                        $result[$this->hashKey($namespace . $class)] = $classMetadata;
                        $associationNames = $classMetadata->getAssociationNames();

                        foreach ($associationNames as $associationName) {
                            if ($classMetadata->isSingleValuedAssociation($associationName)) {
                                $associationTargetClass = $classMetadata->getAssociationTargetClass($associationName);
                                $associationTargetClasses[$this->hashKey(
                                    $associationTargetClass
                                )] = $associationTargetClass;
                            }
                        }
                    }
                }
            }
        }
        foreach ($associationTargetClasses as $associationTargetClass) {
            $this->generateProxy($associationTargetClass);
        }

        return $result;
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

    private function hashKey(string $className): string
    {
        return md5($className);
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

        $reflectionMethods = $reflectionClass->getMethods(!ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC);
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
