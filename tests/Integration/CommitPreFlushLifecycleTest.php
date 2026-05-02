<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration;

use DateTime;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\MockEntityMetadataWithPreFlush;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\Tests\Mock\PreFlushTracker;
use PHPUnit\Framework\TestCase;

final class CommitPreFlushLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PreFlushTracker::reset();
    }

    public function testPreFlushCallbackRunsBeforeAdapterInsert(): void
    {
        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $adapter->expects(self::once())->method('insert')->willReturnCallback(function () {
            self::assertSame(1, PreFlushTracker::$calls, 'preFlush callback must run before insert');

            return ['id' => 1];
        });
        $row = [
            'id' => 1,
            'active' => true,
            'name' => 'n',
            'createdAt' => new DateTime(),
            'price' => 1.0,
            'nullable' => null,
        ];
        $adapter->method('loadById')->willReturn($row);

        $metadataFactory = $this->createMock(EntityMetadataFactory::class);
        $metadataFactory->method('getMetadataFor')->willReturn(new MockEntityMetadataWithPreFlush());

        $em = new AdaptiveEntityManager(
            new Config(
                __DIR__ . '/../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__ . '/../../var/cache',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider($adapter),
            null,
            $metadataFactory,
            null,
            null,
            null,
            false,
        );
        $em->clear();

        $em->persist($entity = new MockEntity());
        $entity
            ->setActive(true)
            ->setName('n')
            ->setCreatedAt(new DateTime())
            ->setPrice(1.0)
            ->setNullable(null);

        $em->flush();

        // preFlush runs in the insert pass and again in the update pass (entity moves to updates after insert).
        self::assertSame(2, PreFlushTracker::$calls);
    }
}
