<?php

namespace Kabiroman\AEM\Tests\Unit\Factory;

use InvalidArgumentException;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\IntegerTypeEntityMetadata;
use Kabiroman\AEM\Tests\Mock\Metadata\MockEntityMetadata;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use PHPUnit\Framework\MockObject\Generator\MockClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use Throwable;

class EntityMetadataFactoryTest extends TestCase
{
    private readonly EntityMetadataFactory $entityMetadataFactory;

    /**
     * @throws ReflectionException
     */
    public function setUp(): void
    {
        $this->entityMetadataFactory = new EntityMetadataFactory(
            new Config(
                __DIR__.'/../../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__.'/../../../var/cache'
            ), new MockClassMetadataProvider()
        );
        parent::setUp();
    }

    public function testHasMetadataFor(): void
    {
        $this->assertTrue($this->entityMetadataFactory->hasMetadataFor(MockEntity::class));
    }

    /**
     * @throws ReflectionException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function testGetMetadataFor(): void
    {
        $this->assertInstanceOf(
            MockEntityMetadata::class,
            $this->entityMetadataFactory->getMetadataFor(MockEntity::class)
        );
    }

    public function testGetMetadataForInvalidClass(): void
    {
        try {
            $this->entityMetadataFactory->getMetadataFor(MockClass::class);
        } catch (Throwable $e) {
            $this->assertInstanceOf(InvalidArgumentException::class, $e);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testGetAllMetadata(): void
    {
        $result = $this->entityMetadataFactory->getAllMetadata();
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
        
        // Check that we have the expected metadata types
        $hasIntegerType = false;
        $hasMockEntity = false;
        
        foreach ($result as $metadata) {
            if ($metadata instanceof IntegerTypeEntityMetadata) {
                $hasIntegerType = true;
            }
            if ($metadata instanceof MockEntityMetadata) {
                $hasMockEntity = true;
            }
        }
        
        $this->assertTrue($hasIntegerType, 'Should contain IntegerTypeEntityMetadata');
        $this->assertTrue($hasMockEntity, 'Should contain MockEntityMetadata');
    }
}
