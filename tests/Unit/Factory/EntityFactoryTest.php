<?php

namespace Kabiroman\AEM\Tests\Unit\Factory;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityMetadata;
use DateTime;
use Exception;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\EntityFactory;
use Kabiroman\AEM\EntityProxy\EntityProxyFactory;
use PHPUnit\Framework\TestCase;

class EntityFactoryTest extends TestCase
{
    private EntityFactory $entityFactory;

    private MockEntityMetadata $classMetadata;

    public function setUp(): void
    {
        $this->entityFactory = new EntityFactory(
            $em = new AdaptiveEntityManager(new Config(
                __DIR__ . '/../../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            ), new MockClassMetadataProvider(), new MockEntityDataAdapterProvider()),
            new EntityProxyFactory($em)
        );
        $this->classMetadata = new MockEntityMetadata();
        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function testMakeEntity(): void
    {
        $row = [
            'id' => 234,
            'active' => true,
            'name' => 'Test name',
            'createdAt' => new DateTime($date = '2004-02-12T15:19:21+00:00'),
            'price' => 567.89,
            'nullable' => null,
        ];
        $entity = $this->entityFactory->makeEntity($this->classMetadata, $row);
        $this->entityFactory->fillEntity($entity, $this->classMetadata, $row);
        $this->assertInstanceOf(MockEntity::class, $entity);
        $this->assertEquals(234, $entity->getId());
        $this->assertEquals('Test name', $entity->getName());
        $this->assertEquals(567.89, $entity->getPrice());
        $this->assertTrue($entity->isActive());
        $this->assertInstanceOf(DateTime::class, $entity->getCreatedAt());
        $this->assertEquals($date, $entity->getCreatedAt()->format('c'));
        $this->assertNull($entity->getNullable());
    }

    /**
     * @throws Exception
     */
    public function testGetEntityDataRow(): void
    {
        $row = [
            'id' => 234,
            'active' => true,
            'name' => 'Test name',
            'createdAt' => new DateTime('2004-02-12T15:19:21+00:00'),
            'price' => 567.89,
            'nullable' => null,
        ];
        $entity = $this->entityFactory->makeEntity($this->classMetadata, $row);
        $this->entityFactory->fillEntity($entity, $this->classMetadata, $row);
        $result = $this->entityFactory->getEntityDataRow($entity, $this->classMetadata);
        $this->assertEquals($row, $result);
    }
}
