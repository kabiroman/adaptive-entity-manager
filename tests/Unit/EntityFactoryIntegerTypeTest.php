<?php

namespace Kabiroman\AEM\Tests\Unit;

use Kabiroman\AEM\EntityFactory;
use Kabiroman\AEM\EntityManagerInterface;
use Kabiroman\AEM\EntityProxy\EntityProxyFactory;
use Kabiroman\AEM\Tests\Mock\Entity\IntegerTypeEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\IntegerTypeEntityMetadata;
use PHPUnit\Framework\TestCase;

class EntityFactoryIntegerTypeTest extends TestCase
{
    private EntityFactory $entityFactory;
    private IntegerTypeEntityMetadata $metadata;
    
    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $proxyFactory = $this->createMock(EntityProxyFactory::class);
        
        $this->entityFactory = new EntityFactory($entityManager, $proxyFactory);
        $this->metadata = new IntegerTypeEntityMetadata();
    }
    
    public function testCreateEntityWithIntegerType(): void
    {
        $row = ['id' => 42];
        
        $entity = $this->entityFactory->makeEntity($this->metadata, $row);
        
        $this->assertInstanceOf(IntegerTypeEntity::class, $entity);
        $this->assertEquals(42, $entity->getId());
    }
    
    public function testFillEntityWithIntegerType(): void
    {
        $entity = new IntegerTypeEntity();
        $row = ['id' => 42];
        
        $this->entityFactory->fillEntity($entity, $this->metadata, $row, false);
        
        $this->assertEquals(42, $entity->getId());
    }
}
