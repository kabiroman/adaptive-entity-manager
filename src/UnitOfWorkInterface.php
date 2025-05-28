<?php

namespace Kabiroman\AEM;

interface UnitOfWorkInterface
{
    public function getEntityPersister(ClassMetadata $classMetadata): PersisterInterface;

    public function clear();

    public function commit();
}
