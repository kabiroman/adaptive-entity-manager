# ADR-002: Замена сырых массивов метаданных на типизированные структуры

| Поле            | Значение                                                      |
|-----------------|---------------------------------------------------------------|
| Статус          | Proposed                                                      |
| Дата            | 2026-05-05                                                    |
| Автор           | Ruslan Kabirov                                                |
| Затронутые файлы | `Metadata/AbstractClassMetadata.php`, все классы `*Metadata` |

## Контекст

В текущей реализации метаданные сущности определяются как ассоциативный массив в свойстве `$metadata` класса, наследующего `AbstractClassMetadata`:

```php
class UserMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        'id' => [
            'type'      => FieldTypeEnum::Integer,
            'column'    => 'user_id',
            'primary'   => true,
        ],
        'fields' => [
            'email' => [
                'type'    => FieldTypeEnum::String,
                'column'  => 'user_email',
                'length'  => 255,
                'nullable' => false,
            ],
            'balance' => [
                'type'   => FieldTypeEnum::ValueObject,
                'class'  => Money::class,
                'from'   => 'fromCents',
                'to'     => 'toCents',
            ],
        ],
        'hasOne' => [
            'profile' => [
                'class'       => Profile::class,
                'fetchMode'   => FetchModeEnum::LAZY,
                'joinColumn'  => 'profile_id',
            ],
        ],
        'hasMany' => [
            'orders' => [
                'class'       => Order::class,
                'fetchMode'   => FetchModeEnum::LAZY,
                'mappedBy'    => 'user_id',
            ],
        ],
        'dataAdapterClass' => UserDataAdapter::class,
        'repositoryClass'  => UserRepository::class,
        'lifecycleCallbacks' => [
            'prePersist' => ['onPrePersist'],
        ],
    ];
}
```

Этот подход имеет ряд серьёзных недостатков:

1. **Нет type-safety на уровне PHP**. Опечатка в ключе (`'hassOne'` вместо `'hasOne'`) не вызывает ошибки ни при загрузке метаданных, ни в рантайме — она молчаливо игнорируется, и ассоциация просто не регистрируется.

2. **Нет автодополнения в IDE**. Разработчик, создающий новый Metadata-класс, не получает подсказок о структуре массива. Приходится копировать из существующего класса и надеяться, что не пропущено обязательное поле.

3. **Нет валидации структуры**. `AbstractClassMetadata` не проверяет, что обязательные ключи присутствуют, что типы допустимы, что `class` указан для `ValueObject`-полей. Ошибки проявляются только в рантайме при гидратации или персистенции — и то не всегда сразу.

4. **СмешениеConcerns**. Идентификатор, поля, ассоциации, адаптер, репозиторий, lifecycle — всё в одном массиве без чёткого разделения. При росте числа полей массив становится нечитаемым.

5. **Дублирование при наследовании**. Если несколько сущностей имеют общие поля (например, `created_at`, `updated_at`), приходится дублировать их в каждом Metadata-классе. PHP-наследование `$metadata` массивов хрупкое и неочевидное.

## Решение

Заменить ассоциативный массив на набор типизированных value objects (DTO), каждый из которых описывает один аспект метаданных сущности.

### Структура DTO

```php
namespace Kabiroman\AEM\Metadata\Structure;

/**
 * Корневой DTO метаданных сущности.
 */
final class EntityMetadataStructure
{
    public function __construct(
        public readonly IdField $id,
        public readonly FieldMap $fields,
        public readonly ?AssociationMap $hasOne = null,
        public readonly ?AssociationMap $hasMany = null,
        public readonly ?string $dataAdapterClass = null,
        public readonly ?string $repositoryClass = null,
        public readonly ?LifecycleCallbackMap $lifecycleCallbacks = null,
    ) {}
}

final class IdField
{
    public function __construct(
        public readonly FieldTypeEnum $type,
        public readonly string $column,
        public readonly bool $primary = true,
    ) {}
}

final class FieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly FieldTypeEnum $type,
        public readonly ?string $column = null,
        public readonly ?int $length = null,
        public readonly bool $nullable = false,
        public readonly ?string $class = null,       // для ValueObject
        public readonly ?string $from = null,         // для ValueObject
        public readonly ?string $to = null,           // для ValueObject
        public readonly ?array $values = null,        // для Boolean mapping
    ) {}
}

/**
 * @extends Map<string, FieldDefinition>
 */
final class FieldMap extends Map {}

final class AssociationDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $class,
        public readonly FetchModeEnum $fetchMode = FetchModeEnum::LAZY,
        public readonly ?string $joinColumn = null,
        public readonly ?string $mappedBy = null,
    ) {}
}

/**
 * @extends Map<string, AssociationDefinition>
 */
final class AssociationMap extends Map {}

/**
 * @extends Map<string, list<string|callable|array{string, string}>>
 */
final class LifecycleCallbackMap extends Map {}
```

### Новый способ определения метаданных

```php
class UserMetadata extends AbstractClassMetadata
{
    protected function defineMetadata(): EntityMetadataStructure
    {
        return new EntityMetadataStructure(
            id: new IdField(
                type: FieldTypeEnum::Integer,
                column: 'user_id',
            ),
            fields: new FieldMap([
                'email' => new FieldDefinition(
                    name: 'email',
                    type: FieldTypeEnum::String,
                    column: 'user_email',
                    length: 255,
                ),
                'balance' => new FieldDefinition(
                    name: 'balance',
                    type: FieldTypeEnum::ValueObject,
                    class: Money::class,
                    from: 'fromCents',
                    to: 'toCents',
                ),
            ]),
            hasOne: new AssociationMap([
                'profile' => new AssociationDefinition(
                    name: 'profile',
                    class: Profile::class,
                    fetchMode: FetchModeEnum::LAZY,
                    joinColumn: 'profile_id',
                ),
            ]),
            dataAdapterClass: UserDataAdapter::class,
            repositoryClass: UserRepository::class,
        );
    }
}
```

### Валидация при создании

Каждый DTO валидирует свои аргументы в конструкторе:

```php
final class FieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly FieldTypeEnum $type,
        // ...
    ) {
        if ($type === FieldTypeEnum::ValueObject && $this->class === null) {
            throw new MetadataDefinitionException(
                "Field '{$name}' has type ValueObject but no 'class' specified."
            );
        }
    }
}
```

## Последствия

### Положительные

- **Опечатки ловятся на этапе загрузки метаданных**, а не в рантайме. `EntityMetadataFactory` при сканировании сущностей получает валидные структуры или исключение.
- **Автодополнение в IDE** — разработчик видит все доступные поля и их типы при создании `FieldDefinition`, `AssociationDefinition` и т.д.
- **Неявные контракты становятся явными** — например, требование указать `class` для ValueObject-поля проверяется в конструкторе DTO, а не молчаливо нарушается.
- **Читаемость** — именованные аргументы PHP 8 делают структуру метаданных самодокументируемой.
- **Расширяемость** — добавление нового аспекта метаданных (например, `indexes`, `uniqueConstraints`) — это новый DTO-параметр, а не ключ в массиве, который можно забыть.

### Отрицательные

- **Breaking change** — все существующие Metadata-классы должны быть переписаны. Это затрагивает каждого пользователя библиотеки.
- **Boilerplate** — DTO-подход многословнее массива. Создание метаданных для сущности с 20 полями потребует больше строк кода.
- **Производительность при загрузке** — создание набора DTO-объектов вместо массива чуть медленнее. Однако метаданные кэшируются (`PhpArrayAdapter`), поэтому влияние ограничено первым запросом после сброса кэша.
- **Количество новых классов** — `EntityMetadataStructure`, `IdField`, `FieldDefinition`, `FieldMap`, `AssociationDefinition`, `AssociationMap`, `LifecycleCallbackMap`, `Map` — 8 новых классов. Это разумный компромисс, но увеличивает площадь API библиотеки.

## План миграции

### Фаза 1: Подготовка (minor release)

1. Создать namespace `Metadata\Structure` с DTO-классами.
2. Добавить метод `defineMetadata(): EntityMetadataStructure` в `AbstractClassMetadata` с дефолтной реализацией, бросающей `LogicException`.
3. Сохранить поддержку `$metadata` array — `AbstractClassMetadata` проверяет, переопределён ли `defineMetadata()`, и если нет — использует старый массив (с deprecation warning через `trigger_error(E_USER_DEPRECATED)`).
4. Добавить внутренний конвертер `ArrayMetadataConverter`, который переводит старый массив в `EntityMetadataStructure` для совместимости.
5. Обновить документацию с новым синтаксисом.

### Фаза 2: Переход (следующий minor release)

1. Добавить PHPStan-правило, детектирующее использование `$metadata` array.
2. Обновить все примеры и тесты на новый синтаксис.
3. Убедиться, что >= 80% тестов используют DTO-синтаксис.

### Фаза 3: Удаление (major release)

1. Удалить поддержку `$metadata` array.
2. Сделать `defineMetadata()` абстрактным методом.
3. Удалить `ArrayMetadataConverter`.

## Альтернативы

### Альтернатива 1: Валидация массива без изменения API

Добавить `MetadataValidator`, который проверяет структуру массива при загрузке метаданных. Опечатки ловятся, но автодополнение в IDE по-прежнему отсутствует.

**Минус**: не решает проблему читаемости и расширяемости. Валидация — это заплатка, а не решение.

### Альтернатива 2: PHP Attributes (аннотации)

Использовать PHP 8 attributes для определения метаданных прямо на классах сущностей:

```php
#[Entity(adapter: UserDataAdapter::class)]
class User
{
    #[Id]
    #[Column(name: 'user_id', type: 'integer')]
    private int $id;

    #[Column(name: 'user_email', type: 'string', length: 255)]
    private string $email;
}
```

**Минус**: это противоречит явному архитектурному решению проекта — не использовать аннотации/XML-маппинг. Плюс, смешивает метаданные с доменным классом, что нарушает разделение concerns в hexagonal architecture.

### Альтернатива 3: Fluent builder

```php
protected function defineMetadata(): EntityMetadataStructure
{
    return EntityMetadataStructure::create()
        ->id('id', FieldTypeEnum::Integer, 'user_id')
        ->field('email', FieldTypeEnum::String, column: 'user_email', length: 255)
        ->field('balance', FieldTypeEnum::ValueObject, class: Money::class, from: 'fromCents', to: 'toCents')
        ->hasOne('profile', Profile::class, joinColumn: 'profile_id')
        ->build();
}
```

**Плюс**: меньше boilerplate, цепочечный синтаксис.
**Минус**: теряется type-safety аргументов (всё уходит в `mixed`), сложнее валидировать на этапе создания. Можно комбинировать: builder возвращает DTO.
