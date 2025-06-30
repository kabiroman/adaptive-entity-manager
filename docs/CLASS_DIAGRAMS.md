# Диаграммы классов

Этот документ содержит UML диаграммы классов Adaptive Entity Manager, показывающие структуру и взаимосвязи основных компонентов.

## 1. Core Entity Manager Classes

```mermaid
classDiagram
    class AdaptiveEntityManager {
        -Config config
        -UnitOfWork unitOfWork
        -ClassMetadataFactory metadataFactory
        -RepositoryFactoryInterface repositoryFactory
        -PersisterFactoryInterface persisterFactory
        -ValueObjectConverterRegistry valueObjectRegistry
        +find(className, id) object|null
        +persist(object) void
        +remove(object) void
        +flush() void
        +getRepository(className) EntityRepository
        +getClassMetadata(className) ClassMetadata
    }
    
    class EntityManagerInterface {
        <<interface>>
        +find(className, id) object|null
        +persist(object) void
        +remove(object) void
        +clear() void
        +detach(object) void
        +refresh(object) void
        +flush() void
        +getRepository(className) ObjectRepository
        +getClassMetadata(className) ClassMetadata
        +contains(object) bool
    }
    
    class UnitOfWork {
        -EntityManagerInterface em
        -EventDispatcherInterface eventDispatcher
        -PersisterInterface[] persisters
        +getEntityPersister(classMetadata) PersisterInterface
        +clear() void
        +commit() void
    }
    
    class UnitOfWorkInterface {
        <<interface>>
        +getEntityPersister(classMetadata) PersisterInterface
        +clear() void
        +commit() void
    }
    
    class Config {
        -array settings
        +get(key) mixed
        +set(key, value) void
        +has(key) bool
    }
    
    class ConfigInterface {
        <<interface>>
        +get(key) mixed
        +set(key, value) void
        +has(key) bool
    }
    
    AdaptiveEntityManager ..|> EntityManagerInterface
    AdaptiveEntityManager --> UnitOfWork
    AdaptiveEntityManager --> Config
    UnitOfWork ..|> UnitOfWorkInterface
    Config ..|> ConfigInterface
```

## 2. Metadata System Classes

```mermaid
classDiagram
    class ClassMetadata {
        -string name
        -string[] identifier
        -array fieldMappings
        -array lifecycleCallbacks
        +getName() string
        +getIdentifier() string[]
        +getFieldMapping(fieldName) array
        +hasLifecycleCallbacks(event) bool
        +getLifecycleCallbacks(event) array
    }
    
    class AbstractClassMetadata {
        <<abstract>>
        #string name
        #array identifier
        #array fieldMappings
        +getName() string
        +getIdentifier() array
        +setIdentifier(identifier) void
    }
    
    class ClassMetadataProvider {
        +getMetadataFor(className) ClassMetadata
        +getAllClassNames() string[]
    }
    
    class DefaultEntityMetadataProvider {
        -array metadataMap
        +getMetadataFor(className) ClassMetadata
        +getAllClassNames() string[]
        +addMetadata(className, metadata) void
    }
    
    class CachedEntityMetadataProvider {
        -ClassMetadataProvider provider
        -CacheItemPoolInterface cache
        +getMetadataFor(className) ClassMetadata
        +getAllClassNames() string[]
    }
    
    class EntityMetadataFactory {
        -Config config
        -ClassMetadataProvider provider
        +getMetadataFor(className) ClassMetadata
        +getAllClassNames() string[]
    }
    
    class OptimizedEntityMetadataFactory {
        -Config config
        -ClassMetadataProvider provider
        -CacheItemPoolInterface cache
        +getMetadataFor(className) ClassMetadata
        +getAllClassNames() string[]
    }
    
    ClassMetadata --|> AbstractClassMetadata
    DefaultEntityMetadataProvider ..|> ClassMetadataProvider
    CachedEntityMetadataProvider ..|> ClassMetadataProvider
    CachedEntityMetadataProvider --> ClassMetadataProvider
    EntityMetadataFactory --> ClassMetadataProvider
    OptimizedEntityMetadataFactory --> ClassMetadataProvider
```

## 3. Data Adapter Classes

```mermaid
classDiagram
    class EntityDataAdapter {
        <<interface>>
        +fetchData(criteria) array
        +saveData(data) bool
        +updateData(data, criteria) bool
        +deleteData(criteria) bool
    }
    
    class AbstractDataAdapter {
        <<abstract>>
        #toCamelCaseParams(row) void
        #toSnakeCaseParams(row) void
        #toUpperSnakeCaseParams(row) void
        #changeKey(key, newKey, arr) void
    }
    
    class EntityDataAdapterProvider {
        +getAdapter(className) EntityDataAdapter
        +hasAdapter(className) bool
    }
    
    class DefaultEntityDataAdapterProvider {
        -array adapters
        +getAdapter(className) EntityDataAdapter
        +hasAdapter(className) bool
        +addAdapter(className, adapter) void
    }
    
    class EntityDataAdapterFactory {
        -EntityDataAdapterProvider provider
        +createAdapter(className) EntityDataAdapter
    }
    
    class DataAdapterFactoryInterface {
        <<interface>>
        +createAdapter(className) EntityDataAdapter
    }
    
    AbstractDataAdapter ..|> EntityDataAdapter
    DefaultEntityDataAdapterProvider ..|> EntityDataAdapterProvider
    EntityDataAdapterFactory ..|> DataAdapterFactoryInterface
    EntityDataAdapterFactory --> EntityDataAdapterProvider
```

## 4. Persistence Layer Classes

```mermaid
classDiagram
    class EntityPersister {
        -EntityManagerInterface em
        -ClassMetadata metadata
        -EntityDataAdapter adapter
        -array inserts
        -array updates
        -array deletes
        +loadById(identifier) object|null
        +addInsert(entity) void
        +addUpdate(entity) void
        +addDelete(entity) void
        +insert(entity) void
        +update(entity) void
        +delete(entity) void
        +getInserts() array
        +getUpdates() array
        +getDeletes() array
    }
    
    class PersisterInterface {
        <<interface>>
        +loadById(identifier) object|null
        +addInsert(entity) void
        +addUpdate(entity) void
        +addDelete(entity) void
        +insert(entity) void
        +update(entity) void
        +delete(entity) void
        +exists(entity) bool
        +detach(entity) void
        +refresh(entity) void
    }
    
    class EntityPersisterFactory {
        -EntityDataAdapterFactory adapterFactory
        +makePersister(em, metadata) PersisterInterface
    }
    
    class PersisterFactoryInterface {
        <<interface>>
        +makePersister(em, metadata) PersisterInterface
    }
    
    EntityPersister ..|> PersisterInterface
    EntityPersisterFactory ..|> PersisterFactoryInterface
    EntityPersisterFactory --> EntityDataAdapterFactory
```

## 5. Value Object Classes

```mermaid
classDiagram
    class ValueObjectInterface {
        <<interface>>
        +toPrimitive() mixed
        +fromPrimitive(value) static
        +equals(other) bool
        +__toString() string
    }
    
    class AbstractValueObject {
        <<abstract>>
        +equals(other) bool
        +__toString() string
    }
    
    class Email {
        -string value
        +__construct(email)
        +toPrimitive() string
        +fromPrimitive(value) static
        +isValid() bool
        +getDomain() string
    }
    
    class Money {
        -int amount
        -string currency
        +__construct(amount, currency)
        +toPrimitive() array
        +fromPrimitive(value) static
        +getAmount() int
        +getCurrency() string
        +add(money) Money
        +subtract(money) Money
    }
    
    class UserId {
        -int id
        +__construct(id)
        +toPrimitive() int
        +fromPrimitive(value) static
        +getId() int
    }
    
    class ValueObjectConverterInterface {
        <<interface>>
        +supports(type) bool
        +fromPrimitive(value) ValueObjectInterface
        +toPrimitive(valueObject) mixed
    }
    
    class DefaultValueObjectConverter {
        +supports(type) bool
        +fromPrimitive(value) ValueObjectInterface
        +toPrimitive(valueObject) mixed
    }
    
    class ValueObjectConverterRegistry {
        -array converters
        +addConverter(converter) void
        +getConverter(type) ValueObjectConverterInterface
        +hasConverter(type) bool
    }
    
    AbstractValueObject ..|> ValueObjectInterface
    Email --|> AbstractValueObject
    Money --|> AbstractValueObject
    UserId --|> AbstractValueObject
    DefaultValueObjectConverter ..|> ValueObjectConverterInterface
    ValueObjectConverterRegistry --> ValueObjectConverterInterface
```

## 6. Repository Classes

```mermaid
classDiagram
    class EntityRepository {
        -EntityManagerInterface em
        -string entityName
        -ClassMetadata metadata
        +find(id) object|null
        +findAll() array
        +findBy(criteria) array
        +findOneBy(criteria) object|null
        +count(criteria) int
        +getClassName() string
    }
    
    class EntityRepositoryFactory {
        -array repositories
        +getRepository(em, className) EntityRepository
    }
    
    class RepositoryFactoryInterface {
        <<interface>>
        +getRepository(em, className) ObjectRepository
    }
    
    EntityRepositoryFactory ..|> RepositoryFactoryInterface
    EntityRepositoryFactory --> EntityRepository
```

## 7. Event System Classes

```mermaid
classDiagram
    class EntityEvent {
        <<abstract>>
        -object entity
        +__construct(entity)
        +getEntity() object
    }
    
    class PrePersistEvent {
        +__construct(entity)
    }
    
    class PostPersistEvent {
        +__construct(entity)
    }
    
    class PreUpdateEvent {
        +__construct(entity)
    }
    
    class PostUpdateEvent {
        +__construct(entity)
    }
    
    class PreRemoveEvent {
        +__construct(entity)
    }
    
    class PostRemoveEvent {
        +__construct(entity)
    }
    
    class LifecycleCallbackHandlerTrait {
        <<trait>>
        +handleLifecycleCallbacks(entity, callbacks) void
    }
    
    PrePersistEvent --|> EntityEvent
    PostPersistEvent --|> EntityEvent
    PreUpdateEvent --|> EntityEvent
    PostUpdateEvent --|> EntityEvent
    PreRemoveEvent --|> EntityEvent
    PostRemoveEvent --|> EntityEvent
```

## 8. Cache Classes

```mermaid
classDiagram
    class SimpleCacheItem {
        -string key
        -mixed value
        -DateTimeInterface expiry
        -bool hit
        +getKey() string
        +get() mixed
        +isHit() bool
        +set(value) static
        +expiresAt(expiration) static
        +expiresAfter(time) static
    }
    
    class SimpleFileCache {
        -string cacheDir
        -int defaultTtl
        +getItem(key) CacheItemInterface
        +getItems(keys) array
        +hasItem(key) bool
        +clear() bool
        +deleteItem(key) bool
        +deleteItems(keys) bool
        +save(item) bool
        +saveDeferred(item) bool
        +commit() bool
    }
    
    class CacheCleaner {
        +cleanMetadataCache(cache) void
        +cleanQueryCache(cache) void
        +cleanAllCaches(cache) void
    }
    
    SimpleCacheItem ..|> CacheItemInterface
    SimpleFileCache ..|> CacheItemPoolInterface
    SimpleFileCache --> SimpleCacheItem
```

## 9. Factory Classes

```mermaid
classDiagram
    class EntityFactory {
        -ClassMetadata metadata
        +createEntity(data, metadata) object
        +hydrateEntity(entity, data, metadata) void
        +extractEntityData(entity, metadata) array
    }
    
    class ValueObjectAwareEntityFactory {
        -ValueObjectConverterRegistry registry
        -EntityFactory entityFactory
        +createEntity(data, metadata) object
        +hydrateEntity(entity, data, metadata) void
        +extractEntityData(entity, metadata) array
        -convertValueObjects(data, metadata) array
    }
    
    class MetadataSystemFactory {
        +createOptimized(config, provider, cache) array
        +createStandard(config, provider) array
    }
    
    ValueObjectAwareEntityFactory --> EntityFactory
    ValueObjectAwareEntityFactory --> ValueObjectConverterRegistry
```

## 10. Exception Classes

```mermaid
classDiagram
    class CommitFailedException {
        -Throwable previous
        +__construct(previous)
        +getPrevious() Throwable
    }
    
    Exception <|-- CommitFailedException
```

## Заключение

Эти диаграммы классов показывают:

- **Четкое разделение ответственности** между различными слоями архитектуры
- **Использование интерфейсов** для обеспечения гибкости и тестируемости
- **Паттерны проектирования**: Factory, Repository, Unit of Work, Value Object
- **Расширяемость** через абстрактные классы и интерфейсы
- **Инверсию зависимостей** для слабой связанности компонентов

Архитектура следует принципам SOLID и обеспечивает высокую модульность и возможность тестирования. 