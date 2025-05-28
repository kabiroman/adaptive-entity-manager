<?php

namespace Kabiroman\AEM;

class EntityRepositoryFactory implements RepositoryFactoryInterface
{
    public function getRepository(EntityManagerInterface $entityManager, string $entityName): EntityRepository
    {
        $class = $entityManager->getClassMetadata($entityName);
        if (!$repositoryClass = $class->getSpecifiedRepositoryName()) {
            $repositoryClass = EntityRepository::class;
        }

        return new $repositoryClass($entityManager, $class);
    }
}
