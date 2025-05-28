<?php

namespace Kabiroman\AEM\DataAdapter;

interface EntityDataAdapter
{
    /**
     * @return array The identifier array.
     */
    public function insert(array $row): array;

    /**
     * @return mixed|void
     */
    public function update(array $identifier, array $row);

    /**
     * @return mixed|void
     */
    public function delete(array $identifier);

    /**
     * @return array The entity data row.
     */
    public function refresh(array $identifier): array;

    /**
     * @return array|null The entity data row.
     */
    public function loadById(array $identifier): array|null;

    /**
     * @return array The collection of entity data row.
     */
    public function loadAll(
        array $criteria = [],
        array|null $orderBy = null,
        int|null $limit = null,
        int|null $offset = null,
    ): array;
}
