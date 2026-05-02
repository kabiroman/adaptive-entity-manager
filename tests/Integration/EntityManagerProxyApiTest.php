<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration;

use Doctrine\Persistence\Proxy;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\PersistentCollection;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use PHPUnit\Framework\TestCase;

final class EntityManagerProxyApiTest extends TestCase
{
    public function testInitializeObjectLoadsDoctrineProxy(): void
    {
        $proxy = $this->createMock(Proxy::class);
        $proxy->expects(self::once())->method('__load');

        $em = new AdaptiveEntityManager(
            new Config(
                __DIR__ . '/../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__ . '/../../var/cache',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider(),
        );

        $em->initializeObject($proxy);
    }

    public function testInitializeObjectInitializesPersistentCollection(): void
    {
        $callbackCalls = 0;
        $collection = new PersistentCollection(static function () use (&$callbackCalls) {
            ++$callbackCalls;

            return ['only'];
        });

        $em = new AdaptiveEntityManager(
            new Config(
                __DIR__ . '/../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__ . '/../../var/cache',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider(),
        );

        self::assertFalse($collection->__isInitialized());

        $em->initializeObject($collection);

        self::assertTrue($collection->__isInitialized());
        self::assertSame(1, $callbackCalls);
    }

    public function testIsUninitializedObjectReflectsProxyInitializedFlag(): void
    {
        $em = new AdaptiveEntityManager(
            new Config(
                __DIR__ . '/../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__ . '/../../var/cache',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider(),
        );

        $proxy = $this->createMock(Proxy::class);
        $proxy->method('__isInitialized')->willReturn(false);
        self::assertTrue($em->isUninitializedObject($proxy));

        $proxy = $this->createMock(Proxy::class);
        $proxy->method('__isInitialized')->willReturn(true);
        self::assertFalse($em->isUninitializedObject($proxy));

        self::assertFalse($em->isUninitializedObject(new \stdClass()));
    }
}
