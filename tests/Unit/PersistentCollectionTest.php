<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Unit;

use Kabiroman\AEM\PersistentCollection;
use PHPUnit\Framework\TestCase;

final class PersistentCollectionTest extends TestCase
{
    public function testLazyInitializationRunsCallbackOnceAndFillsData(): void
    {
        $counter = new class () {
            public int $invocations = 0;
        };

        $collection = new PersistentCollection(static function () use ($counter) {
            ++$counter->invocations;

            return [10 => 'a', 20 => 'b'];
        });

        self::assertFalse($collection->__isInitialized());
        self::assertSame(0, $counter->invocations);

        self::assertSame('a', $collection[10]);
        self::assertTrue($collection->__isInitialized());
        self::assertSame(1, $counter->invocations);

        self::assertCount(2, $collection);
        self::assertSame(1, $counter->invocations);

        $iter = 0;
        foreach ($collection as $_) {
            ++$iter;
        }
        self::assertSame(2, $iter);
        self::assertSame(1, $counter->invocations);
    }

    public function testCollectionWithoutCallbackDoesNotUseLazyCallback(): void
    {
        $collection = new PersistentCollection();
        $collection->append('x');
        self::assertFalse($collection->__isInitialized());

        self::assertSame(1, $collection->count());
        self::assertTrue($collection->__isInitialized());
        self::assertSame('x', $collection[0]);
    }
}
