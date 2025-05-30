<?php

namespace Kabiroman\AEM\Tests\Integration;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\MockEntityMetadata;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;
use DateTime;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\ClassMetadata;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\PersisterFactoryInterface;
use Kabiroman\AEM\TransactionalConnection;
use Kabiroman\AEM\UnitOfWork;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class EntityManagerTest extends TestCase
{
    private AdaptiveEntityManager $em;

    private MockEntityDataAdapter $entityDataAdapter;

    private TransactionalConnection $connection;

    private EntityMetadataFactory $metadataFactory;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->em = new AdaptiveEntityManager(
            new Config(
                __DIR__ . '/../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__.'/../../var/cache'
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider(
                $this->entityDataAdapter = $this->createMock(MockEntityDataAdapter::class)
            ),
            $this->connection = $this->createMock(TransactionalConnection::class),
            $this->metadataFactory = $this->createMock(EntityMetadataFactory::class),
        );
        $this->metadataFactory->method('getMetadataFor')->willReturn(new MockEntityMetadata());
        $this->em->clear();
    }

    public function testContains()
    {
        $this->em->persist($entity = new MockEntity());
        $this->assertTrue($this->em->contains($entity));
    }

    public function testFind()
    {
        $this->entityDataAdapter->expects($this->once())->method('loadById');
        $this->em->find(MockEntity::class, 1);
    }

    public function testDetach()
    {
        $this->em->clear();
        $entity = new MockEntity();
        $this->em->persist($entity);
        $this->em->detach($entity);
        $this->assertFalse($this->em->contains($entity));
    }

    public function testGetRepository()
    {
        $this->metadataFactory->expects($this->once())->method('getMetadataFor');
        $repository = $this->em->getRepository(MockEntity::class);
        $this->assertInstanceOf(MockEntityRepository::class, $repository);
    }

    public function testGetEntityPersisterFactory()
    {
        $persisterFactory = $this->em->getEntityPersisterFactory();
        $this->assertInstanceOf(PersisterFactoryInterface::class, $persisterFactory);
    }

    public function testGetMetadataFactory()
    {
        $metadataFactory = $this->em->getMetadataFactory();
        $this->assertInstanceOf(ClassMetadataFactory::class, $metadataFactory);
    }

    public function testGetClassMetadata()
    {
        $this->metadataFactory->expects($this->once())->method('getMetadataFor');
        $this->em->clear();
        $classMetadata = $this->em->getClassMetadata(MockEntity::class);
        $this->assertInstanceOf(ClassMetadata::class, $classMetadata);
    }

    public function testGetUnitOfWork()
    {
        $unitOfWork = $this->em->getUnitOfWork();
        $this->assertInstanceOf(UnitOfWork::class, $unitOfWork);
    }

    public function testGetConnection()
    {
        $connection = $this->em->getConnection();
        $this->assertInstanceOf(TransactionalConnection::class, $connection);
    }

    public function testClear()
    {
        $unitOfWork = $this->em->getUnitOfWork();
        $reflection = new ReflectionClass(UnitOfWork::class);
        $classMetadata = $this->em->getClassMetadata(MockEntity::class);
        $firstPersister = $unitOfWork->getEntityPersister($classMetadata);
        $this->assertNotEmpty($reflection->getStaticPropertyValue('persisters'));
        $firstPersister->addInsert(new MockEntity());

        $this->em->clear();

        $this->assertEmpty($reflection->getStaticPropertyValue('persisters'));
        $secondPersister = $unitOfWork->getEntityPersister($classMetadata);
        $this->assertNotEquals($firstPersister, $secondPersister);
    }

    public function testPersist()
    {
        $this->metadataFactory->expects($this->once())->method('getMetadataFor');
        $this->em->clear();
        $this->em->persist(new MockEntity());
    }

    public function testRemove()
    {
        $this->metadataFactory->expects($this->atMost(2))->method('getMetadataFor');
        $this->em->clear();
        $entity = new MockEntity();
        $entity->setId(1);
        $this->em->remove($entity);
    }

    /**
     * @throws CommitFailedException
     */
    public function testFlush()
    {
        $this->em->clear();
        $this->entityDataAdapter->method('loadById')->willReturn([
            'id' => 1,
            'active' => true,
            'name' => 'test',
            'createdAt' => new DateTime(),
            'price' => 25.65,
            'nullable' => null
        ]);
        $this->entityDataAdapter->method('insert')->willReturn([
            'id' => 1,
        ]);
        $this->entityDataAdapter->method('update')->withAnyParameters();
        $this->entityDataAdapter->method('delete')->withAnyParameters();

        $this->entityDataAdapter->expects(self::once())->method('insert');
        $this->entityDataAdapter->expects(self::once())->method('update');
        $this->entityDataAdapter->expects(self::once())->method('delete');
        $this->connection->expects(self::atMost(3))->method('beginTransaction');
        $this->connection->expects(self::atMost(3))->method('commitTransaction');

        $this->em->persist($entity = new MockEntity());

        $entity
            ->setActive(true)
            ->setName('name')
            ->setCreatedAt(new DateTime())
            ->setNullable(null)
            ->setPrice(25.65);
        $this->em->flush();

        $entity->setActive(false);
        $this->em->flush();

        $this->em->remove($entity);
        $this->em->flush();
    }


    public function testRefresh()
    {
        $this->metadataFactory->expects($this->once())->method('getMetadataFor');
        $this->entityDataAdapter->expects(self::once())->method('refresh')->willReturn([
            'id' => 1,
            'active' => true,
            'price' => 12.25,
            'nullable' => null,
            'createdAt' => new DateTime(),
            'name' => 'TestName',
        ]);
        $this->em->clear();
        $entity = new MockEntity();
        $entity->setId(1);
        $this->em->refresh($entity);
    }
}
