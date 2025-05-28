<?php

namespace Kabiroman\AEM\Mapping;

trait LifecycleCallbackHandlerTrait
{
    protected function handleLifecycleCallbacks(object $entity, array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            if (is_callable($callback)) {
                $callback($entity);
            } elseif (is_string($callback)) {
                $entity->{$callback}();
            } elseif (is_array($callback)) {
                $callback[0]::{$callback[1]}($entity);
            }
        }
    }
}
