<?php

namespace Kabiroman\AEM\Event;

use Psr\EventDispatcher\StoppableEventInterface;

class EntityEvent implements StoppableEventInterface
{
    private bool $propagationStopped = false;

    public function __construct(private object $entity) {}

    public function getEntity(): object
    {
        return $this->entity;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
}
