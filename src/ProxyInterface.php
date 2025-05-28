<?php

namespace Kabiroman\AEM;

use Closure;
use Doctrine\Persistence\Proxy;

interface ProxyInterface extends Proxy
{
    public function __getOriginal(): object;

    public function __setCallback(Closure $callback);
}
