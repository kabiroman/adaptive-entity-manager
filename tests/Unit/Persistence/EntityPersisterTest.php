<?php

namespace Kabiroman\AEM\Tests\Unit\Persistence;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use DateTime;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use SplObjectStorage;

class EntityPersisterTest extends TestCase
{
    private static AdaptiveEntityManager $em;
    private static int $entity_object_id;
    private static ?MockEntity $entity = null;

    public static function setUpBeforeClass(): void
    {
        self::$em = new AdaptiveEntityManager(
            new Config(
                __DIR__.'/../../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__.'/../../../var/cache'
            ), 
            new MockClassMetadataProvider(), 
            new MockEntityDataAdapterProvider(new MockEntityDataAdapter([
                1 => [
                    'id' => 1,
                    'active' => true,
                    'name' => 'test_name_1',
                    'createdAt' => new DateTime(),
                    'price' => 987.65,
                    'nullable' => null,
                ],
            ])),
            null, // transactionalConnection
            null, // metadataFactory
            null, // repositoryFactory
            null, // persisterFactory
            null, // metadataCache
            false // useOptimizedMetadata - отключаем для старых тестов
        );
        // Не очищаем $em->clear() чтобы persisters сохранялись между тестами
    }

    /**
     * @throws ReflectionException
     */
    public function testAddInsert(): void
    {
        $entity = new MockEntity();
        $entity
            ->setActive(true)
            ->setName('test_name')
            ->setCreatedAt(new DateTime())
            ->setPrice(123.45)
            ->setNullable(null);
        self::$entity_object_id = spl_object_id($entity);
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));

        $persister->addInsert($entity);

        $reflection = new ReflectionClass($persister);
        $inserts = $reflection->getProperty('inserts')->getValue($persister);
        $updates = $reflection->getProperty('updates')->getValue($persister);
        $deletes = $reflection->getProperty('deletes')->getValue($persister);
        $this->assertCount(1, $inserts);
        $this->assertCount(0, $updates);
        $this->assertCount(0, $deletes);
        $this->assertContains($entity, $inserts);
        $this->assertEquals(self::$entity_object_id, spl_object_id($entity));
    }

    /**
     * @throws ReflectionException
     */
    public function testExistsAfterInsert(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $reflection = new ReflectionClass($persister);
        $inserts = $reflection->getProperty('inserts')->getValue($persister);
        $inserts->rewind();
        $entity = $inserts->current();
        $this->assertNotFalse($entity, 'Entity should exist in inserts');
        $this->assertTrue($persister->exists($entity));
    }

    public function testGetInserts(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));

        $inserts = $persister->getInserts();

        $this->assertCount(1, $inserts);
        $this->assertInstanceOf(SplObjectStorage::class, $inserts);
        $inserts->rewind();
        $entity = $inserts->current();
        $this->assertInstanceOf(MockEntity::class, $entity);
        $this->assertEquals(self::$entity_object_id, spl_object_id($entity));
    }

    /**
     * @throws ReflectionException
     */
    public function testInsert(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $inserts = $persister->getInserts();
        $inserts->rewind();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $entity = $inserts->current();

        $persister->insert($entity);

        $this->assertIsInt($entity->getId());
        $this->assertGreaterThan(0, $entity->getId());
        $reflection = new ReflectionClass($persister);
        $inserts = $reflection->getProperty('inserts')->getValue($persister);
        $updates = $reflection->getProperty('updates')->getValue($persister);
        $deletes = $reflection->getProperty('deletes')->getValue($persister);
        $this->assertCount(0, $inserts);
        $this->assertCount(1, $updates);
        $this->assertCount(0, $deletes);
        $this->assertContains($entity, $updates);
        $updates->rewind();
        $entity = $updates->current();
        $this->assertInstanceOf(MockEntity::class, $entity);
        $this->assertEquals(self::$entity_object_id, spl_object_id($entity));
    }

    /**
     * @throws ReflectionException
     */
    public function testExistsAfterUpdate(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $reflection = new ReflectionClass($persister);
        $updates = $reflection->getProperty('updates')->getValue($persister);
        $updates->rewind();
        $entity = $updates->current();
        $this->assertNotFalse($entity, 'Entity should exist in updates');
        $this->assertTrue($persister->exists($entity));
    }

    public function testGetUpdates(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $updates = $persister->getUpdates();
        $this->assertCount(1, $updates);
        $updates->rewind();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $entity = $updates->current();
        $this->assertInstanceOf(MockEntity::class, $entity);
        $this->assertEquals(self::$entity_object_id, spl_object_id($entity));
    }

    public function testRefresh(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $updates = $persister->getUpdates();
        $updates->rewind();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $entity = $updates->current();
        $entityTemp = clone $entity;
        $entity->setActive(false)
            ->setName('name_test')
            ->setCreatedAt(new DateTime())
            ->setPrice(1987.65)
            ->setNullable(null);

        $persister->refresh($entity);

        $this->assertEquals($entityTemp, $entity);
    }

    /**
     * @throws ReflectionException
     */
    public function testUpdate(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $updates = $persister->getUpdates();
        $updates->rewind();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $entity = $updates->current();
        $entityTempBefore = clone $entity;
        $entity->setActive(false)
            ->setName('name_test')
            ->setCreatedAt(new DateTime())
            ->setPrice(1987.65)
            ->setNullable('null');
        $entityTempAfter = clone $entity;

        $persister->update($entity);

        $reflection = new ReflectionClass($persister);
        $inserts = $reflection->getProperty('inserts')->getValue($persister);
        $updates = $reflection->getProperty('updates')->getValue($persister);
        $deletes = $reflection->getProperty('deletes')->getValue($persister);
        $this->assertCount(0, $inserts);
        $this->assertCount(1, $updates);
        $this->assertCount(0, $deletes);
        $entity = $persister->loadById(['id' => $entity->getId()]);
        $this->assertNotEquals($entityTempBefore, $entity);
        $this->assertEquals($entityTempAfter, $entity);
    }

    /**
     * @throws ReflectionException
     */
    public function testAddDelete(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $reflection = new ReflectionClass($persister);
        $updates = $reflection->getProperty('updates')->getValue($persister);
        $updates->rewind();
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        $entity = $updates->current();

        $persister->addDelete($entity);

        $inserts = $reflection->getProperty('inserts')->getValue($persister);
        $deletes = $reflection->getProperty('deletes')->getValue($persister);
        $this->assertCount(0, $inserts);
        $this->assertCount(0, $updates);
        $this->assertCount(1, $deletes);
    }

    /**
     * @throws ReflectionException
     */
    public function testExistsAfterAddDelete(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $reflection = new ReflectionClass($persister);
        $deletes = $reflection->getProperty('deletes')->getValue($persister);
        $deletes->rewind();
        $entity = $deletes->current();
        $this->assertNotFalse($entity, 'Entity should exist in deletes');
        $this->assertTrue($persister->exists($entity));
    }

    public function testGetDeletes(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $deletes = $persister->getDeletes();

        $this->assertCount(1, $deletes);
        $this->assertInstanceOf(SplObjectStorage::class, $deletes);
        $deletes->rewind();
        $entity = $deletes->current();
        $this->assertInstanceOf(MockEntity::class, $entity);
        $this->assertEquals(self::$entity_object_id, spl_object_id($entity));
    }

    /**
     * @throws ReflectionException
     */
    public function testDelete(): void
    {
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $reflection = new ReflectionClass($persister);
        $deletes = $reflection->getProperty('deletes')->getValue($persister);
        $deletes->rewind();
        $entity = $deletes->current();

        $persister->delete($entity);

        $inserts = $reflection->getProperty('inserts')->getValue($persister);
        $updates = $reflection->getProperty('updates')->getValue($persister);
        $this->assertCount(0, $inserts);
        $this->assertCount(0, $updates);
        $this->assertCount(0, $deletes);
        $this->assertNull($persister->loadById(['id' => $entity->getId()]));

        self::$entity = $entity;
    }

    public function testExistsAfterDelete(): void
    {
        $this->assertNotNull(self::$entity, 'Entity should be set by previous test');
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $this->assertFalse($persister->exists(self::$entity));
    }

    public function testDetach(): void
    {
        $this->assertNotNull(self::$entity, 'Entity should be set by previous test');
        $persister = self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $persister->addInsert(self::$entity);
        $persister->detach(self::$entity);

        $this->assertFalse($persister->exists(self::$entity));

        $persister->addInsert(self::$entity);
        $persister->insert(self::$entity);
        $this->assertTrue($persister->exists(self::$entity));
        $persister->detach(self::$entity);
        $this->assertFalse($persister->exists(self::$entity));

        $persister->addInsert(self::$entity);
        $persister->insert(self::$entity);
        $persister->addDelete(self::$entity);
        $this->assertTrue($persister->exists(self::$entity));
        $persister->detach(self::$entity);
        $this->assertFalse($persister->exists(self::$entity));
    }
}
