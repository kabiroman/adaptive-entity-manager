# ADR-001: Устранение статической EntityFactory в EntityPersister

| Поле            | Значение                                         |
|-----------------|--------------------------------------------------|
| Статус          | Proposed                                         |
| Дата            | 2026-05-05                                       |
| Автор           | Ruslan Kabirov                                   |
| Затронутые файлы | `Persistence/EntityPersister.php`, `Entity/EntityFactory.php`, `Entity/ValueObjectAwareEntityFactory.php`, `AdaptiveEntityManager.php` |

## Контекст

В текущей реализации `EntityPersister` использует статическое свойство для хранения экземпляра `EntityFactory`:

```php
class EntityPersister
{
    private static EntityFactory $entityFactory;

    public static function setEntityFactory(EntityFactory $factory): void
    {
        self::$entityFactory = $factory;
    }
}
```

Это означает, что **все** экземпляры `EntityPersister` в рамках одного PHP-процесса разделяют один и тот же объект `EntityFactory`. Для классического PHP-FPM (один запрос — один процесс) это не создаёт проблем, поскольку каждый запрос начинает с чистого состояния.

Однако с распространением long-running runtime-ов (Swoole, RoadRunner, ReactPHP, FrankenPHP) один процесс обслуживает множество запросов последовательно. Если в таком процессе создаются два экземпляра `AdaptiveEntityManager` с разными конфигурациями ValueObject (например, разные `ValueObjectConverterRegistry`), второй `setEntityFactory()` перезапишет фабрику первого менеджера. Результат — некорректная гидратация сущностей, которая может проявляться как молчаливая порча данных.

README проекта явно отмечает это как known limitation, но не предлагает пути решения.

## Решение

Заменить статическое хранение `EntityFactory` на инъекцию через экземпляр `EntityPersister`. Каждый `EntityPersister` получает собственную фабрику при создании через `EntityPersisterFactory`.

### До

```php
class EntityPersister
{
    private static EntityFactory $entityFactory;

    public static function setEntityFactory(EntityFactory $factory): void
    {
        self::$entityFactory = $factory;
    }

    public function someMethod(): void
    {
        $entity = self::$entityFactory->createEntity(...);
    }
}
```

### После

```php
class EntityPersister
{
    private EntityFactory $entityFactory;

    public function __construct(
        // ... other params
        EntityFactory $entityFactory,
    ) {
        $this->entityFactory = $entityFactory;
    }

    public function someMethod(): void
    {
        $entity = $this->entityFactory->createEntity(...);
    }
}
```

`EntityPersisterFactory` при создании каждого персистера передаёт ему соответствующую фабрику сущностей. Выбор между `EntityFactory` и `ValueObjectAwareEntityFactory` остаётся за фабрикой персистеров, которая получает эту информацию из конфигурации `AdaptiveEntityManager`.

## Последствия

### Положительные

- **Изоляция контекстов**: каждый `AdaptiveEntityManager` работает со своей `EntityFactory`, что корректно для long-running процессов.
- **Тестируемость**: в тестах можно создавать `EntityPersister` с мок-фабрикой без побочных эффектов между тестами.
- **Явность зависимостей**: зависимость от `EntityFactory` видна в конструкторе, а не скрыта в статическом вызове.
- **Устранение known limitation**: документированное ограничение снимается полностью.

### Отрицательные

- **Расход памяти**: каждый `EntityPersister` хранит собственную ссылку на фабрику. Это пренебрежимо мало (ссылка на объект, не копия), но формально потребление памяти растёт.
- **Совместимость**: удаление `EntityPersister::setEntityFactory()` — breaking change. Потребуется major или minor version bump с соответствующим changelog-записем.
- **Код инициализации**: вызов `setEntityFactory()` при создании EM нужно убрать, а конструктор `EntityPersister` расширить. Это касается `EntityPersisterFactory` и всех точек, где персистеры создаются.

## План миграции

1. Добавить `EntityFactory` как параметр конструктора `EntityPersister`.
2. Обновить `EntityPersisterFactory` — передавать фабрику при создании персистера.
3. Удалить `static $entityFactory` и `setEntityFactory()`.
4. Обновить все тесты, использующие `setEntityFactory()`.
5. Добавить интеграционный тест с двумя EM в одном процессе.
6. Выпустить как minor release с пометкой BC break в changelog.

## Альтернативы

### Альтернатива 1: Per-process singleton с reset-ом

Добавить метод `reset()` для очистки статической фабрики между запросами в long-running runtime. Это подход Doctrine ORM (`EntityManager::clear()`).

**Минус**: возлагает ответственность на пользователя — забыл вызвать `reset()`, получил порчу данных. Статическое состояние по-прежнему разделяемое.

### Альтернатива 2: Registry паттерн

Создать `EntityFactoryRegistry`, который хранит фабрики по ключу (например, по хэшу конфигурации EM), и передавать ключ в `EntityPersister`.

**Минус**: добавляет ещё один глобальный объект, не решает фундаментальную проблему разделения состояния, усложняет код.
