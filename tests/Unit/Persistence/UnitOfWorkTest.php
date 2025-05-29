<?php

namespace Kabiroman\AEM\Tests\Unit\Persistence;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use DateTime;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\PersisterInterface;
use Kabiroman\AEM\UnitOfWork;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class UnitOfWorkTest extends TestCase
{
    private static AdaptiveEntityManager $em;

    public static function setUpBeforeClass(): void
    {
        self::$em = new AdaptiveEntityManager(
            new Config(
                __DIR__.'/../Entity',
                'App\\Tests\\Entity\\',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider(new MockEntityDataAdapter()),
        );
        self::$em->clear();
    }

    public function testGetEntityPersister()
    {
        self::assertInstanceOf(UnitOfWork::class, self::$em->getUnitOfWork());
        self::assertInstanceOf(
            PersisterInterface::class,
            self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class))
        );
    }

    public function testClear()
    {
        $reflection = new ReflectionClass(UnitOfWork::class);
        self::$em->getUnitOfWork()->getEntityPersister(self::$em->getClassMetadata(MockEntity::class));
        $unitOfWork = self::$em->getUnitOfWork();
        $this->assertNotEmpty($reflection->getStaticPropertyValue('persisters'));

        $unitOfWork->clear();

        $this->assertEmpty($reflection->getStaticPropertyValue('persisters'));
    }

    /**
     * @throws CommitFailedException
     * @throws Exception
     */
    public function testCommit()
    {
        $em = new AdaptiveEntityManager(
            new Config(
                __DIR__.'/../Entity',
                'App\\Tests\\Entity\\',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider($adapter = $this->createMock(MockEntityDataAdapter::class))
        );
        $em->clear();

        $adapter->method('loadById')->willReturn([
            'id' => 1,
            'active' => true,
            'name' => 'test',
            'createdAt' => new DateTime(),
            'price' => 25.65,
            'nullable' => null
        ]);
        $adapter->method('insert')->willReturn([
            'id' => 1,
        ]);
        $adapter->method('update')->withAnyParameters();
        $adapter->method('delete')->withAnyParameters();

        $adapter->expects(self::once())->method('insert');
        $adapter->expects(self::once())->method('update');
        $adapter->expects(self::once())->method('delete');

        ($unitOfWork = $em->getUnitOfWork())->getEntityPersister($em->getClassMetadata(MockEntity::class))
            ->addInsert($entity = new MockEntity());

        $entity
            ->setActive(true)
            ->setName('name')
            ->setCreatedAt(new DateTime())
            ->setNullable(null)
            ->setPrice(25.65);
        $unitOfWork->commit();

        $entity->setActive(false);
        $unitOfWork->commit();

        $unitOfWork->getEntityPersister($em->getClassMetadata(MockEntity::class))->addDelete($entity);
        $unitOfWork->commit();
    }
}
