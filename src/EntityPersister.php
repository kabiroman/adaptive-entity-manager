<?php

namespace Kabiroman\AEM;

use Kabiroman\AEM\DataAdapter\EntityDataAdapter;
use Kabiroman\AEM\EntityProxy\EntityProxyFactory;
use Kabiroman\AEM\Mapping\LifecycleCallbackHandlerTrait;
use RuntimeException;
use SplObjectStorage;

class EntityPersister implements PersisterInterface
{
    use LifecycleCallbackHandlerTrait;

    private readonly SplObjectStorage $inserts;

    /**
     * @var object[]
     */
    private array $indexedUpdates = [];

    private readonly SplObjectStorage $updates;

    private readonly SplObjectStorage $deletes;

    private static EntityFactory $entityFactory;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityDataAdapter $entityDataAdapter,
        private readonly ClassMetadata $classMetadata,
    ) {
        $this->inserts = new SplObjectStorage();
        $this->updates = new SplObjectStorage();
        $this->deletes = new SplObjectStorage();

        if (!isset(self::$entityFactory)) {
            // Create ValueObject-aware factory if ValueObject support is enabled
            if ($this->entityManager->hasValueObjectSupport()) {
                self::$entityFactory = new ValueObjectAwareEntityFactory(
                    $this->entityManager,
                    new EntityProxyFactory($entityManager),
                    $this->entityManager->getValueObjectRegistry()
                );
            } else {
                self::$entityFactory = new EntityFactory($this->entityManager, new EntityProxyFactory($entityManager));
            }
        }
    }

    public function getInserts(): iterable
    {
        return $this->inserts;
    }

    public function getUpdates(): iterable
    {
        return $this->updates;
    }

    public function getDeletes(): iterable
    {
        return $this->deletes;
    }

    public function addInsert(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            return;
        }
        if ($this->updates->contains($entity) || $this->deletes->contains($entity)) {
            return;
        }
        if ($this->classMetadata->hasLifecycleCallbacks('prePersist')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('prePersist');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }
        $this->inserts->attach($entity);
    }

    public function addDelete(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getOriginal();
        }
        if ($this->classMetadata->hasLifecycleCallbacks('preRemove')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('preRemove');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }
        $this->inserts->detach($entity);
        $this->updates->detach($entity);
        $identifier = $this->classMetadata->getIdentifierValues($entity);
        unset($this->indexedUpdates[$this->hash($identifier)]);
        $this->deletes->attach($entity);
    }

    public function insert(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            return;
        }
        $identifier = $this->entityDataAdapter->insert($this->getEntityDataRow($entity));
        $this->prepareIdentifierFromAdapter($identifier);

        $identifierForAdapter = $identifier;
        $this->prepareIdentifierToAdapter($identifierForAdapter);

        if (!$row = $this->entityDataAdapter->loadById($identifierForAdapter)) {
            throw new RuntimeException('Failed to get entity data row.');
        }
        $this->fillEntity($entity, $row, false);

        $this->inserts->detach($entity);
        $this->updates->attach($entity, $this->makeEntityInfo($row));
        $this->indexedUpdates[$this->hash($identifier)] = $entity;

        if ($this->classMetadata->hasLifecycleCallbacks('postPersist')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('postPersist');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }
    }

    public function update(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getOriginal();
        }
        if (!$this->isDirty($entity)) {
            return;
        }
        $identifier = $this->classMetadata->getIdentifierValues($entity);
        $this->prepareIdentifierToAdapter($identifier);

        if ($this->classMetadata->hasLifecycleCallbacks('preUpdate')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('preUpdate');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }
        $this->entityDataAdapter->update($identifier, $this->getEntityDataRow($entity));

        if ($this->classMetadata->hasLifecycleCallbacks('postUpdate')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('postUpdate');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }
    }

    public function delete(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getOriginal();
        }
        $identifier = $this->classMetadata->getIdentifierValues($entity);
        $this->prepareIdentifierToAdapter($identifier);
        $this->entityDataAdapter->delete($identifier);
        $this->deletes->detach($entity);

        if ($this->classMetadata->hasLifecycleCallbacks('postRemove')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('postRemove');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }
    }

    public function refresh(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getOriginal();
        }
        $identifier = $this->classMetadata->getIdentifierValues($entity);
        $this->prepareIdentifierToAdapter($identifier);
        if (!$row = $this->entityDataAdapter->refresh($identifier)) {
            throw new RuntimeException('Unable to refresh entity');
        }
        $this->fillEntity($entity, $row);
    }

    public function exists(object $entity): bool
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getOriginal();
        }
        return $this->inserts->contains($entity)
            || $this->updates->contains($entity)
            || $this->deletes->contains($entity);
    }

    public function detach(object $entity): void
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getOriginal();
        }
        $this->inserts->detach($entity);
        $this->updates->detach($entity);
        if (in_array($entity, $this->indexedUpdates, true)) {
            $identifier = $this->classMetadata->getIdentifierValues($entity);
            unset($this->indexedUpdates[$this->hash($identifier)]);
        }
        $this->deletes->detach($entity);
    }

    public function loadById(array $identifier): object|null
    {
        $this->prepareIdentifierToAdapter($identifier);
        if (!$row = $this->entityDataAdapter->loadById($identifier)) {
            return null;
        }
        $entity = $this->makeEntity($row);
        $identifierValues = $this->classMetadata->getIdentifierValues($entity);
        $hash = $this->hash($identifierValues);

        if (isset($this->indexedUpdates[$hash])) {
            return $this->indexedUpdates[$hash];
        }
        $this->indexedUpdates[$hash] = $entity;
        $this->fillEntity($entity, $row);

        $row = self::$entityFactory->getEntityDataRow($entity, $this->classMetadata);
        $this->updates->attach($entity, $this->makeEntityInfo($row));

        if ($this->classMetadata->hasLifecycleCallbacks('postLoad')) {
            $callbacks = $this->classMetadata->getLifecycleCallbacks('postLoad');
            $this->handleLifecycleCallbacks($entity, $callbacks);
        }

        return $entity;
    }

    public function loadAll(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $result = [];
        $this->prepareCriteriaParamNames($criteria);
        if (is_array($orderBy)) {
            $this->prepareOrderParamNames($orderBy);
        }
        $list = $this->entityDataAdapter->loadAll($criteria, $orderBy, $limit, $offset);
        foreach ($list as $row) {
            $entity = $this->makeEntity($row);
            $identifier = $this->classMetadata->getIdentifierValues($entity);
            $hash = $this->hash($identifier);

            if (isset($this->indexedUpdates[$hash])) {
                $result[] = $this->indexedUpdates[$hash];
                continue;
            }
            $this->indexedUpdates[$hash] = $entity;
            $this->fillEntity($entity, $row);
            $result[] = $entity;

            if ($this->classMetadata->hasLifecycleCallbacks('postLoad')) {
                $callbacks = $this->classMetadata->getLifecycleCallbacks('postLoad');
                $this->handleLifecycleCallbacks($entity, $callbacks);
            }

            $row = self::$entityFactory->getEntityDataRow($entity, $this->classMetadata);
            $this->updates->attach($entity, $this->makeEntityInfo($row));
        }

        return $result;
    }

    private function hash(mixed $identifier): string
    {
        return md5(serialize($identifier));
    }

    private function getEntityDataRow(object $entity): array
    {
        return self::$entityFactory->getEntityDataRow($entity, $this->classMetadata);
    }

    private function isDirty(object $entity): bool
    {
        if (!$this->updates->contains($entity)) {
            throw new RuntimeException('Unable to update entity', 0);
        }
        $info = $this->updates->offsetGet($entity);
        $currentRow = self::$entityFactory->getEntityDataRow($entity, $this->classMetadata);
        if (!isset($info['original'])) {
            throw new RuntimeException('Unable to update entity', 1);
        }

        return $info['original'] !== $currentRow;
    }

    private function makeEntity(array $row): object
    {
        return self::$entityFactory->makeEntity($this->classMetadata, $row);
    }

    private function fillEntity(object $entity, array $row, bool $withoutIdentifier = true): void
    {
        self::$entityFactory->fillEntity($entity, $this->classMetadata, $row, $withoutIdentifier);
    }

    private function makeEntityInfo(mixed $row): array
    {
        return ['original' => $row];
    }

    private function prepareCriteriaParamNames(array &$criteria): void
    {
        foreach ($criteria as $key => $value) {
            $fieldName = preg_replace('/^[=%><@!]{0,3}/', '', $key);
            $new_key = preg_replace('/'.$fieldName.'/', $this->classMetadata->getColumnOfField($fieldName), $key);
            $this->changeKey($key, $new_key, $criteria);
        }
    }

    private function prepareOrderParamNames(array &$orderBy): void
    {
        foreach ($orderBy as $key => $value) {
            $this->changeKey($key, $this->classMetadata->getColumnOfField($key), $orderBy);
        }
    }

    private function prepareIdentifierFromAdapter(array &$identifiers): void
    {
        foreach ($identifiers as $key => $value) {
            $this->changeKey($this->classMetadata->getColumnOfField($key), $key, $identifiers);
        }
    }

    private function prepareIdentifierToAdapter(array &$identifiers): void
    {
        foreach ($identifiers as $key => $value) {
            $this->changeKey($key, $this->classMetadata->getColumnOfField($key), $identifiers);
        }
    }

    protected function changeKey($key, $new_key, &$arr): void
    {
        if (!array_key_exists($new_key, $arr)) {
            $arr[$new_key] = $arr[$key];
            unset($arr[$key]);
        }
    }
}
