<?php

namespace Kabiroman\AEM;

use Kabiroman\AEM\DataAdapter\DataAdapterFactoryInterface;

class EntityPersisterFactory implements PersisterFactoryInterface
{
    public function __construct(private readonly DataAdapterFactoryInterface $dataAdapterFactory)
    {
    }

    public function makePersister(
        EntityManagerInterface $entityManager,
        ClassMetadata $classMetadata
    ): PersisterInterface {
        return new EntityPersister(
            $entityManager,
            $this->dataAdapterFactory->getAdapter($classMetadata),
            $classMetadata
        );
    }
}
