<?php

namespace Kabiroman\AEM;

use ArrayObject;
use Closure;
use Iterator;

class PersistentCollection extends ArrayObject
{
    private Closure $callback;

    private bool $__initialized = false;

    public function __construct(Closure $callback = null)
    {
        if ($callback) {
            $this->callback = $callback;
        }
        parent::__construct();
    }

    public function initialize(): void
    {
        $this->__initialized = true;
        if (isset($this->callback)) {
            $callback = $this->callback;
            parent::__construct($callback());
        }
    }

    public function getIterator(): Iterator
    {
        if (!$this->__initialized) {
            $this->initialize();
        }
        return parent::getIterator();
    }

    public function offsetGet(mixed $key): mixed
    {
        if (!$this->__initialized) {
            $this->initialize();
        }
        return parent::offsetGet($key);
    }

    public function __isInitialized(): bool
    {
        return $this->__initialized;
    }

    public function count(): int
    {
        if (!$this->__initialized) {
            $this->initialize();
        }
        return parent::count();
    }
}
