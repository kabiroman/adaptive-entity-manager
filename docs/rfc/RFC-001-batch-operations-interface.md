# RFC-001: Интерфейс пакетных операций для EntityDataAdapter

| Поле            | Значение                                  |
|-----------------|-------------------------------------------|
| Статус          | Proposed                                  |
| Дата            | 2026-05-05                                |
| Автор           | Ruslan Kabirov                            |
| Затронутые файлы | `Adapter/EntityDataAdapter.php`, все реализации адаптеров, `Persistence/EntityPersister.php` |

## Мотивация

Текущий интерфейс `EntityDataAdapter` поддерживает только пооперационную обработку:

```php
interface EntityDataAdapter
{
    public function insert(array $row): void;
    public function update(array $row, mixed $identifier): void;
    public function delete(mixed $identifier): void;
    // ...
}
```

Когда `UnitOfWork::commit()` flushed изменения, он вызывает `insert()`/`update()`/`delete()` для каждой сущности по отдельности. Для адаптеров, работающих с REST API или GraphQL, это приемлемо — каждый вызов соответствует одному HTTP-запросу. Однако для SQL-адаптеров это критическая проблема производительности:

- Вставка 1000 записей = 1000 отдельных `INSERT`-запросов к БД.
- Каждый запрос — отдельный round-trip к серверу базы данных.
- При network latency 1ms — 1 секунда только на ожидание ответов. При 5ms — 5 секунд.
- Multi-row `INSERT` сокращает время в 10-50x.

Данная проблема особенно остра в контексте заявленного позиционирования библиотеки как инструмента миграции данных из legacy-систем — именно при миграции объёмы данных максимальны.

## Предлагаемое решение

### Расширение интерфейса EntityDataAdapter

Добавить опциональные методы для пакетных операций с дефолтной реализацией через пооперационные вызовы:

```php
interface EntityDataAdapter
{
    // Существующие методы
    public function insert(array $row): void;
    public function update(array $row, mixed $identifier): void;
    public function delete(mixed $identifier): void;

    // Новые пакетные методы
    public function bulkInsert(array $rows): void;
    public function bulkUpdate(array $rowsWithIdentifiers): void;
    public function bulkDelete(array $identifiers): void;

    // Информация о поддержке пакетных операций
    public function supportsBulkOperations(): bool;
}
```

### Дефолтная реализация в AbstractDataAdapter

```php
abstract class AbstractDataAdapter implements EntityDataAdapter
{
    public function bulkInsert(array $rows): void
    {
        foreach ($rows as $row) {
            $this->insert($row);
        }
    }

    public function bulkUpdate(array $rowsWithIdentifiers): void
    {
        foreach ($rowsWithIdentifiers as ['row' => $row, 'identifier' => $identifier]) {
            $this->update($row, $identifier);
        }
    }

    public function bulkDelete(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $this->delete($identifier);
        }
    }

    public function supportsBulkOperations(): bool
    {
        return false;
    }
}
```

Адаптеры, не поддерживающие пакетные операции (REST, GraphQL), ничего не переопределяют — дефолтная реализация работает корректно.

### SQL-специфичная реализация

```php
class SqlEntityDataAdapter extends AbstractDataAdapter
{
    public function bulkInsert(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = array_keys($rows[0]);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $placeholders));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->table,
            implode(', ', $columns),
            $allPlaceholders
        );

        $flatParams = [];
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $flatParams[] = $row[$column];
            }
        }

        $this->connection->executeStatement($sql, $flatParams);
    }

    public function supportsBulkOperations(): bool
    {
        return true;
    }
}
```

### Изменения в EntityPersister

`EntityPersister` накапливает сущности для вставки/обновления/удаления в `SplObjectStorage`. При commit он может выбрать стратегию:

```php
class EntityPersister
{
    public function performInsert(EntityDataAdapter $adapter): void
    {
        $rows = [];
        foreach ($this->insertions as $entity) {
            $rows[] = $this->entityFactory->extractRow($entity);
        }

        if ($adapter->supportsBulkOperations() && count($rows) > 1) {
            $adapter->bulkInsert($rows);
        } else {
            foreach ($rows as $row) {
                $adapter->insert($row);
            }
        }
    }
}
```

### Настраиваемый threshold

Добавить опциональный порог для переключения на пакетный режим:

```php
class EntityPersister
{
    public function __construct(
        // ... existing params
        private readonly int $bulkThreshold = 1,
    ) {}
}
```

- `bulkThreshold = 1` — использовать пакетные операции всегда (если поддерживаются).
- `bulkThreshold = 10` — переключаться на пакетный режим при 10+ записях.
- Адаптеры без поддержки пакетных операций игнорируют threshold.

## Формат данных для пакетных операций

### bulkInsert

```php
$rows = [
    ['name' => 'Alice', 'email' => 'alice@example.com'],
    ['name' => 'Bob',   'email' => 'bob@example.com'],
    ['name' => 'Carol', 'email' => 'carol@example.com'],
];
$adapter->bulkInsert($rows);
```

Все строки должны иметь одинаковую структуру колонок. Это гарантируется `EntityPersister`, который извлекает данные через `EntityFactory::extractRow()`.

### bulkUpdate

```php
$rowsWithIdentifiers = [
    ['row' => ['name' => 'Alice Updated'], 'identifier' => 1],
    ['row' => ['name' => 'Bob Updated'],   'identifier' => 2],
];
$adapter->bulkUpdate($rowsWithIdentifiers);
```

Для SQL-адаптера это может быть реализовано через `CASE WHEN`:

```sql
UPDATE users SET name = CASE id
    WHEN 1 THEN 'Alice Updated'
    WHEN 2 THEN 'Bob Updated'
END
WHERE id IN (1, 2)
```

### bulkDelete

```php
$identifiers = [1, 2, 3, 4, 5];
$adapter->bulkDelete($identifiers);
```

Для SQL: `DELETE FROM users WHERE id IN (1, 2, 3, 4, 5)`.

## Ограничения пакетных операций

Пакетные операции имеют семантические отличия от пооперационных:

1. **Lifecycle callbacks** — `prePersist` вызывается для каждой сущности ДО пакетной вставки (по одной), а `postPersist` — после всей пакетной вставки (один раз). Это отличается от пооперационного режима, где каждая пара pre/post обрамляет один `insert()`.

2. **Events** — `PrePersistEvent` / `PostPersistEvent` в пакетном режиме: либо одно событие с массивом сущностей, либо по одному событию на сущность, но с группировкой по адаптеру. Это требует обсуждения.

3. **Частичные failures** — при пакетной вставке 100 записей, если 50-я вызывает constraint violation, поведение зависит от БД. PostgreSQL откатывает весь `INSERT`, MySQL может вставить первые 49 (в зависимости от engine). Адаптер должен документировать своё поведение.

4. **Identity retrieval** — при пакетной `INSERT` в PostgreSQL можно использовать `RETURNING` для получения всех сгенерированных ID. MySQL `LAST_INSERT_ID()` работает только для первой записи multi-row INSERT. Это ограничение важно для `EntityPersister`, который должен проставить ID после вставки.

## План реализации

### Фаза 1: Интерфейс + дефолтная реализация (patch/minor release)

1. Добавить `bulkInsert()`, `bulkUpdate()`, `bulkDelete()`, `supportsBulkOperations()` в `EntityDataAdapter`.
2. Реализовать дефолтные fallback-методы в `AbstractDataAdapter`.
3. Обновить `EntityPersister` — использовать пакетные методы при `supportsBulkOperations() === true`.
4. Добавить `bulkThreshold` в конструктор `EntityPersister`.
5. Написать unit-тесты для дефолтной fallback-реализации.
6. Написать integration-тест с mock-адаптером, поддерживающим пакетные операции.

### Фаза 2: SQL-адаптер (следующий release)

1. Реализовать `bulkInsert()`, `bulkUpdate()`, `bulkDelete()` в SQL-адаптере.
2. Добавить тесты с реальной БД (SQLite in-memory для CI, MySQL/PostgreSQL опционально).
3. Бенчмарк: сравнить 1000 insert по одному vs. bulk insert.

### Фаза 3: Оптимизация lifecycle и events

1. Определить семантику lifecycle callbacks для пакетных операций.
2. При необходимости ввести `BulkPrePersistEvent` / `BulkPostPersistEvent`.
3. Документировать поведение при partial failures.

## Альтернативы

### Альтернатива 1: Отдельный BulkEntityDataAdapter

Вынести пакетные операции в отдельный интерфейс:

```php
interface BulkEntityDataAdapter
{
    public function bulkInsert(array $rows): void;
    public function bulkUpdate(array $rowsWithIdentifiers): void;
    public function bulkDelete(array $identifiers): void;
}
```

`EntityPersister` проверяет `instanceof BulkEntityDataAdapter`.

**Плюс**: не загрязняет основной интерфейс.
**Минус**: нарушение ISP (Interface Segregation) в обратную сторону — адаптер, поддерживающий пакетные операции, обязан реализовать оба интерфейса. Проверка через `instanceof` менее явная, чем метод `supportsBulkOperations()`.

### Альтернатива 2: Buffering decorator

Обернуть любой `EntityDataAdapter` в `BufferingDataAdapter`, который накапливает операции и flush-ит их пакетом при достижении threshold:

```php
$adapter = new BufferingDataAdapter($innerAdapter, threshold: 100);
$adapter->insert($row1);  // buffered
$adapter->insert($row2);  // buffered
// ...
$adapter->insert($row100); // triggers flush → bulkInsert() or 100x insert()
```

**Плюс**: не требует изменений в интерфейсе `EntityDataAdapter`. Работает с любым адаптером.
**Минус**: контроль над flush-моментом теряется —装饰ор решает сам. Трудно координировать с transactional boundaries. Не решает проблему эффективного SQL — буферизация через decorator всё равно вызовет `insert()` 100 раз, просто отложенно.
