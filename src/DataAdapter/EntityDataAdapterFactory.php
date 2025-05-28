<?php

namespace Kabiroman\AEM\DataAdapter;

use Kabiroman\AEM\ClassMetadata;

class EntityDataAdapterFactory implements DataAdapterFactoryInterface
{
    private array $adapters = [];

    public function __construct(
        private readonly EntityDataAdapterProvider $entityDataAdapterProvider
    ) {
    }

    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter
    {
        if (!isset($this->adapters[$this->hashKey($metadata->getName())])) {
            $this->adapters[$this->hashKey($metadata->getName())] = $this->entityDataAdapterProvider
                ->getAdapter($metadata);
        }

        return $this->adapters[$this->hashKey($metadata->getName())];
    }

    private function hashKey(string $className): string
    {
        return md5($className);
    }
}
