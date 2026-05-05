# ADR-004: Формализация контракта порядка диспетчеризации событий

| Поле            | Значение                                                                     |
|-----------------|------------------------------------------------------------------------------|
| Статус          | Proposed                                                                     |
| Дата            | 2026-05-05                                                                   |
| Автор           | Ruslan Kabirov                                                               |
| Затронутые файлы | `Persistence/UnitOfWork.php`, `Event/EntityEvent.php`, `AdaptiveEntityManager.php` |

## Контекст

AEM диспетчеризует lifecycle-события при commit-е: `PrePersistEvent`, `PostPersistEvent`, `PreUpdateEvent`, `PostUpdateEvent`, `PreRemoveEvent`, `PostRemoveEvent`. В проекте существует интеграционный тест `CommitEventDispatchOrderTest`, который проверяет конкретный порядок событий. Это означает, что порядок важен для корректности работы.

Однако нигде в контракте библиотеки этот порядок не зафиксирован:

1. **`UnitOfWork` не документирует порядок** — в коде порядок определяется реализацией метода `commit()`, но нет контрактного обещания, что он останется таким же в будущих версиях.

2. **Пользователь может заменить `UnitOfWork`** — интерфейс `UnitOfWorkInterface` позволяет предоставить собственную реализацию. Но нигде не сказано, что замена должна соблюдать определённый порядок событий.

3. **Порядок между типами операций неочевиден** — если в одном commit-е есть и inserts, и updates, и deletes, в каком порядке они выполняются? Сначала все inserts, потом все updates, потом все deletes? Или перемежаются по сущностям?

### Текущий порядок (из кода UnitOfWork)

```
1. Все PrePersistEvent  (для каждой вставляемой сущности)
2. Все insert()         (вызов адаптера для каждой сущности)
3. Все PostPersistEvent (для каждой вставленной сущности)
4. Все PreUpdateEvent   (для каждой обновляемой сущности)
5. Все update()         (вызов адаптера для каждой сущности)
6. Все PostUpdateEvent  (для каждой обновлённой сущности)
7. Все PreRemoveEvent   (для каждой удаляемой сущности)
8. Все delete()         (вызов адаптера для каждой сущности)
9. Все PostRemoveEvent  (для каждой удалённой сущности)
```

Это "batch-по-типу-операции" — сначала все вставки, потом все обновления, потом все удаления. Альтернативный подход — "по-сущности": для каждой сущности выполняется полный цикл pre→операция→post перед переходом к следующей.

## Решение

Формализовать порядок диспетчеризации событий как часть контракта `UnitOfWorkInterface`.

### 1. Документировать контракт в интерфейсе

```php
interface UnitOfWorkInterface
{
    /**
     * Commits all pending changes in the following order:
     *
     * 1. Pre-persist events for all new entities
     * 2. Insert operations for all new entities
     * 3. Post-persist events for all new entities
     * 4. Pre-update events for all modified entities
     * 5. Update operations for all modified entities
     * 6. Post-update events for all modified entities
     * 7. Pre-remove events for all removed entities
     * 8. Delete operations for all removed entities
     * 9. Post-remove events for all removed entities
     *
     * This ordering guarantees that:
     * - Pre-events always precede their corresponding operation
     * - Post-events always follow their corresponding operation
     * - All inserts are completed before any updates begin
     * - All updates are completed before any deletes begin
     * - Listeners can rely on this order for cross-entity invariants
     *
     * @throws CommitFailedException If any operation fails
     */
    public function commit(): void;
}
```

### 2. Добавить CommitOrder enum для расширяемости

```php
enum CommitOrder: string
{
    /**
     * Batch by operation type: all inserts, then all updates, then all deletes.
     * Best for SQL adapters where batch operations are possible.
     */
    case BATCH_BY_OPERATION = 'batch_by_operation';

    /**
     * Per-entity: for each entity, execute full lifecycle (pre → operation → post).
     * Best for REST/GraphQL adapters where each operation is independent.
     */
    case PER_ENTITY = 'per_entity';
}
```

### 3. Configurable commit order

```php
class AdaptiveEntityManager
{
    public function __construct(
        ConfigInterface $config,
        // ... existing params
        CommitOrder $commitOrder = CommitOrder::BATCH_BY_OPERATION,
    ) {
        $this->unitOfWork = new UnitOfWork(
            $this,
            commitOrder: $commitOrder,
        );
    }
}
```

### 4. Гарантии для event listeners

Добавить документацию к событиям:

```php
class PrePersistEvent extends EntityEvent
{
    /**
     * Dispatched before the entity is inserted into the data source.
     *
     * Guarantees at the time of dispatch:
     * - No entities have been updated or deleted yet in this commit cycle
     * - The entity's identifier may not be assigned yet
     * - Modifications to the entity will be included in the insert operation
     * - Stopping propagation prevents the insert and all subsequent operations
     */
}
```

## Последствия

### Положительные

- **Явный контракт** — пользователи библиотеки могут полагаться на порядок событий, не читая исходный код `UnitOfWork`.
- **Тестируемость пользовательского кода** — если порядок гарантирован, пользователи могут писать тесты, зависящие от порядка, без риска, что минорное обновление их сломает.
- **Расширяемость** — `CommitOrder` enum позволяет добавить новые стратегии (например, `TOPOLOGICAL_SORT` для сущностей с зависимостями) без изменения интерфейса.
- **Совместимость с Doctrine** — Doctrine ORM использует аналогичный batch-по-типу порядок, что упрощает миграцию.

### Отрицательные

- **Ограничение гибкости** — зафиксировав порядок, мы теряем возможность изменить его без semver-major bump. Если обнаружится, что альтернативный порядок эффективнее, придётся ждать 2.0.
- **Complexity** — добавление `CommitOrder` enum и конфигурирования — это дополнительная сложность для простых use cases.
- **PER_ENTITY порядок требует дублирования логики** — реализация обоих порядков в `UnitOfWork` увеличивает объём кода.

## План реализации

### Фаза 1: Документация (patch release)

1. Добавить docblock с порядком commit в `UnitOfWorkInterface::commit()`.
2. Добавить гарантии к каждому event-классу.
3. Обновить `ARCHITECTURE_DIAGRAMS.md` или `INTERACTION_DIAGRAMS.md` с диаграммой порядка событий.
4. Никаких изменений в коде — только документация.

### Фаза 2: CommitOrder enum (minor release)

1. Создать `CommitOrder` enum.
2. Добавить параметр в конструктор `AdaptiveEntityManager`.
3. Реализовать `PER_ENTITY` порядок в `UnitOfWork` (за feature flag).
4. Дефолт — `BATCH_BY_OPERATION` (текущее поведение).
5. Добавить тесты для обоих порядков.

### Фаза 3: Валидация контракта (minor release)

1. Добавить `CommitOrderValidator`, который в dev-режиме проверяет, что события действительно диспетчеризуются в заявленном порядке (через assertion layer).
2. Добавить в CI отдельный тест-ран с assertion-ами порядка.

## Альтернативы

### Альтернатива 1: Только документация, без CommitOrder

Зафиксировать порядок в docblock, но не добавлять альтернативные стратегии.

**Плюс**: минимум изменений в коде.
**Минус**: REST/GraphQL адаптеры могут предпочитать `PER_ENTITY` порядок (один HTTP-запрос = полный lifecycle одной сущности). Без CommitOrder они не могут этого добиться.

### Альтернатива 2: Transactional event listeners (Doctrine approach)

Разделить события на "внутри транзакции" и "после коммита", как Doctrine ORM (`onFlush` vs `postFlush`).

**Плюс**: явное разделение between "можно откатить" и "уже закоммичено".
**Минус**: значительно усложняет API. AEM позиционируется как простой инструмент — добавление двух фаз событий может быть over-engineering на данном этапе.
