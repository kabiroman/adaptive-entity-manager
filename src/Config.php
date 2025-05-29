<?php

namespace Kabiroman\AEM;

class Config implements ConfigInterface
{
    public function __construct(
        public readonly string $entityFolder = __DIR__."/../../../../src/Entity",
        public readonly string $entityNamespace = 'App\\Entity\\',
        public readonly string $cacheFolder = __DIR__ . "/../../../../var/cache",
    ) {
    }

    public function getEntityFolder(): string
    {
        return $this->entityFolder;
    }

    public function getEntityNamespace(): string
    {
        return $this->entityNamespace;
    }

    public function getCacheFolder(): string
    {
        return $this->cacheFolder;
    }
}
