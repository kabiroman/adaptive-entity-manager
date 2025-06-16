<?php

namespace Kabiroman\AEM;

use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use Kabiroman\AEM\DataAdapter\EntityDataAdapterFactory;
use Kabiroman\AEM\DataAdapter\EntityDataAdapterProvider;
use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\Metadata\ClassMetadataProvider;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Metadata\MetadataSystemFactory;
use Psr\Cache\CacheItemPoolInterface;

final class AdaptiveEntityManager implements EntityManagerInterface
{

    private readonly UnitOfWork $unitOfWork;

    private readonly ClassMetadataFactory $metadataFactory;

    private readonly RepositoryFactoryInterface $repositoryFactory;

    private readonly PersisterFactoryInterface $persisterFactory;

    public function __construct(
        private readonly Config $config,
        ClassMetadataProvider $classMetadataProvider,
        EntityDataAdapterProvider $entityDataAdapterProvider,
        private readonly ?TransactionalConnection $transactionalConnection = null,
        ClassMetadataFactory $metadataFactory = null,
        RepositoryFactoryInterface $repositoryFactory = null,
        PersisterFactoryInterface $persisterFactory = null,
        ?CacheItemPoolInterface $metadataCache = null,
        bool $useOptimizedMetadata = true,
    ) {
        if ($metadataFactory === null) {
            if ($useOptimizedMetadata) {
                $metadataSystem = MetadataSystemFactory::createOptimized(
                    $config, 
                    $classMetadataProvider, 
                    $metadataCache
                );
                $this->metadataFactory = $metadataSystem['factory'];
            } else {
                $this->metadataFactory = new EntityMetadataFactory($config, $classMetadataProvider);
            }
        } else {
            $this->metadataFactory = $metadataFactory;
        }

        $this->repositoryFactory = $repositoryFactory ?? new EntityRepositoryFactory();

        $this->persisterFactory = $persisterFactory
            ?? new EntityPersisterFactory(new EntityDataAdapterFactory($entityDataAdapterProvider));

        $this->unitOfWork = new UnitOfWork($this);
    }

    public function find(string $className, mixed $id): object|null
    {
        $identifier = [];
        $class = $this->getMetadataFactory()->getMetadataFor(ltrim($className, '\\'));

        if (!is_array($id)) {
            if (count($class->getIdentifier()) > 1) {
                throw new InvalidArgumentException('Invalid composite identifier');
            }
            $identifier = [$class->getIdentifier()[0] => $id];
        } else {
            foreach ($class->getIdentifier() as $name) {
                $identifier[$name] = $id[$name];
            }
        }

        return $this->unitOfWork->getEntityPersister($this->getClassMetadata($className))->loadById($identifier);
    }

    public function persist(object $object): void
    {
        $class = $this->getMetadataFactory()->getMetadataFor(get_class($object));
        $this->unitOfWork->getEntityPersister($class)->addInsert($object);
    }

    public function remove(object $object): void
    {
        $class = $this->getMetadataFactory()->getMetadataFor(get_class($object));
        $this->unitOfWork->getEntityPersister($class)->addDelete($object);
    }

    public function clear(): void
    {
        $this->unitOfWork->clear();
    }

    public function detach(object $object): void
    {
        $class = $this->getMetadataFactory()->getMetadataFor(get_class($object));
        $this->unitOfWork->getEntityPersister($class)->detach($object);
    }

    public function refresh(object $object): void
    {
        $class = $this->getMetadataFactory()->getMetadataFor(get_class($object));
        $this->unitOfWork->getEntityPersister($class)->refresh($object);
    }

    /**
     * @throws CommitFailedException
     */
    public function flush(): void
    {
        $this->unitOfWork->commit();
    }

    public function getRepository(string $className): EntityRepository
    {
        return $this->repositoryFactory->getRepository($this, $className);
    }

    public function getClassMetadata(string $className): ClassMetadata
    {
        return $this->getMetadataFactory()->getMetadataFor($className);
    }

    public function getMetadataFactory(): ClassMetadataFactory
    {
        return $this->metadataFactory;
    }

    public function initializeObject(object $obj): void
    {
        if ($obj instanceof Proxy) {
            $obj->__load();

            return;
        }

        if ($obj instanceof PersistentCollection) {
            $obj->initialize();
        }
    }

    public function isUninitializedObject(mixed $value): bool
    {
        return $value instanceof Proxy && !$value->__isInitialized();
    }

    public function contains(object $object): bool
    {
        $class = $this->getMetadataFactory()->getMetadataFor(get_class($object));

        return $this->unitOfWork->getEntityPersister($class)->exists($object);
    }

    public function getEntityPersisterFactory(): PersisterFactoryInterface
    {
        return $this->persisterFactory;
    }

    public function getUnitOfWork(): UnitOfWork
    {
        return $this->unitOfWork;
    }

    public function getConnection(): TransactionalConnection|null
    {
        return $this->transactionalConnection;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
