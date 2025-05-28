<?php

namespace Kabiroman\AEM;

interface PersisterFactoryInterface
{
    public function makePersister(EntityManagerInterface $entityManager, ClassMetadata $classMetadata): PersisterInterface;
}
