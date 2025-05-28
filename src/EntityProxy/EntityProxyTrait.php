<?php

namespace Kabiroman\AEM\EntityProxy;

use Closure;
use Kabiroman\AEM\ProxyInterface;

/**
 * @see ProxyInterface
 */
trait EntityProxyTrait
{
    protected bool $initialized = false;

    protected Closure $callback;

    protected object $original;

    /**
     * Initializes this proxy if it`s not yet initialized.
     *
     * Acts as a no-op if already initialized.
     */
    public function __load(): void
    {
        if (!$this->initialized) {
            $this->initialized = true;
            $this->original = $this->callback->__invoke();
        }
    }

    /** Returns whether this proxy is initialized or not. */
    public function __isInitialized(): bool
    {
        return $this->initialized;
    }

    public function __getOriginal(): object
    {
        $this->__load();

        return $this->original;
    }

    public function __setCallback(Closure $callback): void
    {
        $this->callback = $callback;
    }
}
