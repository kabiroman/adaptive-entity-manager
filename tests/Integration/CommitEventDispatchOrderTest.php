<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration;

use DateTime;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Event\PostPersistEvent;
use Kabiroman\AEM\Event\PrePersistEvent;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Tests\Integration\Support\RecordingEventDispatcher;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\MockEntityMetadata;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use PHPUnit\Framework\TestCase;

final class CommitEventDispatchOrderTest extends TestCase
{
    public function testInsertCommitDispatchesPrePersistBeforePostPersist(): void
    {
        $dispatcher = new RecordingEventDispatcher();

        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $adapter->expects(self::once())->method('insert')->willReturn(['id' => 1]);
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
        $metadataFactory->method('getMetadataFor')->willReturn(new MockEntityMetadata());

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
            $dispatcher,
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

        self::assertSame(
            [PrePersistEvent::class, PostPersistEvent::class],
            $dispatcher->getDispatchedClasses(),
        );
    }
}
