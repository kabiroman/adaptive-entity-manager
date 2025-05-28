<?php

namespace Kabiroman\AEM;

use Doctrine\Persistence\ObjectRepository;

/**
 * Interface for entity repository factory.
 */
interface RepositoryFactoryInterface
{
    /**
     * Gets the repository for an entity class.
     *
     * @param EntityManagerInterface $entityManager The EntityManager instance.
     * @param class-string<T>        $entityName    The name of the entity.
     *
     * @return ObjectRepository<T>
     *
     * @template T of object
     */
    public function getRepository(EntityManagerInterface $entityManager, string $entityName): ObjectRepository;
}
