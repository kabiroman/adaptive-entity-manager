<?php

namespace Kabiroman\AEM\Tests\Mock\Orm;

use Kabiroman\AEM\DataAdapter\EntityDataAdapter;
use RuntimeException;

class MockEntityDataAdapter implements EntityDataAdapter
{
    private array $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function insert(array $row): array
    {
        $id = time();
        $this->data[$id] = $row;

        return ['id' => $id];
    }

    public function update(array $identifier, array $row): void
    {
    }

    public function delete(array $identifier): void
    {
        unset($this->data[$identifier['id']]);
    }

    public function refresh(array $identifier): array
    {
        return $this->loadById($identifier) ?? throw new RuntimeException('Unable to refresh entity.');
    }

    public function loadById(array $identifier): array|null
    {
        return !empty($this->data[$identifier['id']]) ? array_merge($this->data[$identifier['id']], $identifier) : null;
    }

    public function loadAll(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        if (!empty($this->data)) {
            return [$this->data];
        } else {
            return [];
        }
    }
}
