<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<class-string> */
    private array $dispatchedClasses = [];

    public function reset(): void
    {
        $this->dispatchedClasses = [];
    }

    /**
     * @return list<class-string>
     */
    public function getDispatchedClasses(): array
    {
        return $this->dispatchedClasses;
    }

    public function dispatch(object $event): object
    {
        $this->dispatchedClasses[] = $event::class;

        return $event;
    }
}
