<?php

namespace Kabiroman\AEM\Tests\Unit\Factory;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\ClassMetadata;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\DataAdapter\EntityDataAdapterFactory;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;

class EntityDataAdapterFactoryTest extends TestCase
{
    private EntityDataAdapterFactory $entityDataAdapterFactory;
    private ClassMetadata $classMetadata;

    /**
     * @throws InvalidArgumentException
     */
    protected function setUp(): void
    {
        $this->entityDataAdapterFactory = new EntityDataAdapterFactory(
            new MockEntityDataAdapterProvider(
                new MockEntityDataAdapter()
            )
        );
        $this->classMetadata = (new EntityMetadataFactory(
            new Config(
                __DIR__ . '/../Entity',
                'App\\Tests\\Entity\\',
            ),
            new MockClassMetadataProvider()
        ))->getMetadataFor(MockEntity::class);
    }

    public function testGetAdapter(): void
    {
        $adapterFirst = $this->entityDataAdapterFactory->getAdapter($this->classMetadata);
        $this->assertInstanceOf(MockEntityDataAdapter::class, $adapterFirst);
        $adapterSecond = $this->entityDataAdapterFactory->getAdapter($this->classMetadata);
        $this->assertEquals($adapterFirst, $adapterSecond);
    }
}
