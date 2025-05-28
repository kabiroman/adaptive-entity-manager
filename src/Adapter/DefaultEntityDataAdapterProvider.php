<?php

namespace Kabiroman\AEM\Adapter;

use Kabiroman\AEM\ClassMetadata;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class DefaultEntityDataAdapterProvider implements EntityDataAdapterProvider
{
    public function __construct(private readonly ?ContainerInterface $container = null)
    {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter
    {
        if ($this->container !== null) {
            return $this->container->get($metadata->getEntityDataAdapterClass());
        }

        return new ($metadata->getEntityDataAdapterClass())();
    }
}
