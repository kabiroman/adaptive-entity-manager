<?php

namespace Kabiroman\AEM;

interface PersisterInterface
{
    /**
     * @return object[]
     */
    public function getInserts(): iterable;

    /**
     * @return object[]
     */
    public function getUpdates(): iterable;

    /**
     * @return object[]
     */
    public function getDeletes(): iterable;

    public function addInsert(object $entity);

    public function addDelete(object $entity);

    public function insert(object $entity);

    public function update(object $entity);

    public function delete(object $entity);

    public function refresh(object $entity);

    public function exists(object $entity): bool;

    public function detach(object $entity);

    public function loadById(array $identifier): object|null;

    public function loadAll(
        array $criteria = [],
        array|null $orderBy = null,
        int|null $limit = null,
        int|null $offset = null,
    ): array;
}
