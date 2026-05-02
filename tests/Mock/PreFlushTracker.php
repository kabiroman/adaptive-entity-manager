<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock;

/**
 * @internal
 */
final class PreFlushTracker
{
    public static int $calls = 0;

    public static function reset(): void
    {
        self::$calls = 0;
    }

    public static function touch(object $entity): void
    {
        ++self::$calls;
    }
}
