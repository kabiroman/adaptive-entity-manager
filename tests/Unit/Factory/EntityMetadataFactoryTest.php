<?php

namespace Kabiroman\AEM\Tests\Unit\Factory;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityMetadata;
use InvalidArgumentException;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use PHPUnit\Framework\MockObject\Generator\MockClass;
use PHPUnit\Framework\TestCase;
use Throwable;

class EntityMetadataFactoryTest extends TestCase
{
    private readonly EntityMetadataFactory $entityMetadataFactory;

    public function setUp(): void
    {
        $this->entityMetadataFactory = new EntityMetadataFactory(new Config(
            __DIR__.'/../Entity',
            'App\\Tests\\Entity\\',
        ), new MockClassMetadataProvider());
        parent::setUp();
    }

    public function testHasMetadataFor(): void
    {
        $this->assertTrue($this->entityMetadataFactory->hasMetadataFor(MockEntity::class));
    }

    /**
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

    public function testGetAllMetadata(): void
    {
        $result = $this->entityMetadataFactory->getAllMetadata();
        $this->assertIsArray($result);
        $this->assertArrayHasKey(0, $result);
        $this->assertInstanceOf(MockEntityMetadata::class, $result[0]);
    }
}
