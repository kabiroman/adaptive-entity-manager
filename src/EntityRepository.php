<?php

namespace Kabiroman\AEM;

use Doctrine\Persistence\ObjectRepository;

/**
 * An EntityRepository serves as a repository for entities with generic as well as
 * business specific methods for retrieving entities.
 *
 * This class is designed for inheritance and users can subclass this class to
 * write their own repositories with business-specific methods to locate entities.
 *
 * @template T of object
 * @template-implements ObjectRepository<T>
 */
class EntityRepository implements ObjectRepository
{
    private string $entityClass;

    /** @param ClassMetadata<T> $class */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClassMetadata $class
    ) {
        $this->entityClass = $class->getName();
    }

    public function find(mixed $id): object|null
    {
        return $this->em->find($this->getClassName(), $id);
    }

    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->em->getUnitOfWork()->getEntityPersister($this->class)
            ->loadAll($criteria, $orderBy, $limit, $offset);
    }

    public function findOneBy(array $criteria): object|null
    {
        return $this->findBy($criteria, null, 1)[0] ?? null;
    }

    public function getClassName(): string
    {
        return $this->entityClass;
    }
}
