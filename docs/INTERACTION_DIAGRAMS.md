# Диаграммы взаимодействий и процессов

Этот документ содержит детальные диаграммы взаимодействий компонентов Adaptive Entity Manager.

## 1. Процесс инициализации Entity Manager

```mermaid
sequenceDiagram
    participant Client
    participant AEM as AdaptiveEntityManager
    participant Config
    participant MSF as MetadataSystemFactory
    participant CMF as ClassMetadataFactory
    participant RF as RepositoryFactory
    participant PF as PersisterFactory
    participant UOW as UnitOfWork
    participant Cache
    
    Client->>AEM: new AdaptiveEntityManager(config, ...)
    AEM->>Config: validate configuration
    Config-->>AEM: validated config
    
    alt useOptimizedMetadata
        AEM->>MSF: createOptimized(config, provider, cache)
        MSF->>CMF: create optimized factory
        MSF-->>AEM: metadata system
    else
        AEM->>CMF: new EntityMetadataFactory(config, provider)
        CMF-->>AEM: metadata factory
    end
    
    AEM->>RF: create repository factory
    RF-->>AEM: repository factory
    
    AEM->>PF: create persister factory
    PF-->>AEM: persister factory
    
    AEM->>UOW: new UnitOfWork(this, eventDispatcher)
    UOW-->>AEM: unit of work
    
    AEM-->>Client: initialized EntityManager
```

## 2. Жизненный цикл сущности

```mermaid
graph TB
    subgraph "Entity Lifecycle"
        New["`**New**
        Не известна EM
        Не имеет ID`"]
        
        Managed["`**Managed**
        Отслеживается EM
        Имеет ID
        Синхронизирована с БД`"]
        
        Detached["`**Detached**
        Не отслеживается EM
        Может иметь ID
        Не синхронизирована с БД`"]
        
        Removed["`**Removed**
        Отмечена для удаления
        Будет удалена при flush()`"]
    end
    
    subgraph "Operations"
        persist["`persist()`"]
        find["`find()`"]
        merge["`merge()`"]
        remove["`remove()`"]
        detach["`detach()`"]
        flush["`flush()`"]
        clear["`clear()`"]
    end
    
    New -->|persist| Managed
    New -->|merge| Managed
    Detached -->|find| Managed
    Detached -->|merge| Managed
    Managed -->|remove| Removed
    Managed -->|detach| Detached
    Managed -->|clear| Detached
    Removed -->|flush| Detached
    Removed -->|detach| Detached
    
    style New fill:#ffebee
    style Managed fill:#e8f5e8
    style Detached fill:#fff3e0
    style Removed fill:#fce4ec
```

## 3. Процесс выполнения запроса

```mermaid
sequenceDiagram
    participant Client
    participant AEM as AdaptiveEntityManager
    participant Repo as EntityRepository
    participant UOW as UnitOfWork
    participant EP as EntityPersister
    participant DA as DataAdapter
    participant Cache
    participant DB as Database
    
    Client->>AEM: getRepository(className)
    AEM->>Repo: create/get repository
    Repo-->>Client: repository instance
    
    Client->>Repo: findBy(criteria)
    Repo->>AEM: getClassMetadata(className)
    AEM-->>Repo: metadata
    
    Repo->>UOW: getEntityPersister(metadata)
    UOW->>EP: get/create persister
    EP-->>UOW: persister instance
    UOW-->>Repo: persister
    
    Repo->>EP: findBy(criteria)
    
    EP->>Cache: get cached results
    alt Cache Hit
        Cache-->>EP: cached entities
    else Cache Miss
        EP->>DA: fetchData(criteria)
        DA->>DB: execute query
        DB-->>DA: raw data
        DA-->>EP: structured data
        EP->>EP: hydrate entities
        EP->>Cache: store results
    end
    
    EP-->>Repo: entity collection
    Repo-->>Client: results
```

## 4. Процесс коммита (flush)

```mermaid
flowchart TB
    Start(["`flush() called`"])
    
    subgraph "Transaction Management"
        BeginTx["`Begin Transaction`"]
        CommitTx["`Commit Transaction`"]
        RollbackTx["`Rollback Transaction`"]
    end
    
    subgraph "Pre-Processing"
        PreFlush["`Execute preFlush callbacks`"]
        CollectChanges["`Collect all changes from persisters`"]
    end
    
    subgraph "Insert Phase"
        ProcessInserts["`Process Inserts`"]
        PrePersistEvent["`Dispatch PrePersistEvent`"]
        ExecuteInsert["`Execute Insert`"]
        PostPersistEvent["`Dispatch PostPersistEvent`"]
    end
    
    subgraph "Update Phase"
        ProcessUpdates["`Process Updates`"]
        PreUpdateEvent["`Dispatch PreUpdateEvent`"]
        ExecuteUpdate["`Execute Update`"]
        PostUpdateEvent["`Dispatch PostUpdateEvent`"]
    end
    
    subgraph "Delete Phase"
        ProcessDeletes["`Process Deletes`"]
        PreRemoveEvent["`Dispatch PreRemoveEvent`"]
        ExecuteDelete["`Execute Delete`"]
        PostRemoveEvent["`Dispatch PostRemoveEvent`"]
        DetachEntity["`Detach Entity`"]
    end
    
    Error["`Error Occurred`"]
    Success["`Success`"]
    End(["`End`"])
    
    Start --> BeginTx
    BeginTx --> PreFlush
    PreFlush --> CollectChanges
    CollectChanges --> ProcessInserts
    
    ProcessInserts --> PrePersistEvent
    PrePersistEvent --> ExecuteInsert
    ExecuteInsert --> PostPersistEvent
    PostPersistEvent --> ProcessUpdates
    
    ProcessUpdates --> PreUpdateEvent
    PreUpdateEvent --> ExecuteUpdate
    ExecuteUpdate --> PostUpdateEvent
    PostUpdateEvent --> ProcessDeletes
    
    ProcessDeletes --> PreRemoveEvent
    PreRemoveEvent --> ExecuteDelete
    ExecuteDelete --> PostRemoveEvent
    PostRemoveEvent --> DetachEntity
    DetachEntity --> CommitTx
    
    CommitTx --> Success
    Success --> End
    
    ExecuteInsert -->|Error| Error
    ExecuteUpdate -->|Error| Error
    ExecuteDelete -->|Error| Error
    Error --> RollbackTx
    RollbackTx --> End
    
    style Start fill:#e8f5e8
    style Success fill:#c8e6c9
    style Error fill:#ffcdd2
    style End fill:#f5f5f5
```

## 5. Value Object Conversion Flow

```mermaid
sequenceDiagram
    participant Entity
    participant EF as EntityFactory
    participant VOAEF as ValueObjectAwareEntityFactory
    participant VOR as ValueObjectRegistry
    participant VOC as ValueObjectConverter
    participant VO as ValueObject
    participant DA as DataAdapter
    
    Note over Entity,DA: Entity Creation from Raw Data
    
    DA->>EF: createEntity(rawData, metadata)
    EF->>VOAEF: create entity with value objects
    
    loop For each Value Object field
        VOAEF->>VOR: getConverter(fieldType)
        VOR-->>VOAEF: converter instance
        
        VOAEF->>VOC: fromPrimitive(rawValue)
        VOC->>VO: create value object
        VO-->>VOC: value object instance
        VOC-->>VOAEF: value object
        
        VOAEF->>Entity: setField(valueObject)
    end
    
    VOAEF-->>EF: entity with value objects
    EF-->>DA: hydrated entity
    
    Note over Entity,DA: Entity Persistence
    
    Entity->>EF: persist entity
    EF->>VOAEF: extract data for persistence
    
    loop For each Value Object field
        VOAEF->>Entity: getField()
        Entity-->>VOAEF: value object
        
        VOAEF->>VOC: toPrimitive(valueObject)
        VOC->>VO: toPrimitive()
        VO-->>VOC: primitive value
        VOC-->>VOAEF: primitive value
    end
    
    VOAEF-->>EF: primitive data
    EF-->>DA: data for storage
```

## 6. Caching Strategy

```mermaid
graph TB
    subgraph "Cache Layers"
        L1["`**L1 Cache**
        Entity Cache
        (Identity Map)`"]
        
        L2["`**L2 Cache**
        Query Result Cache
        (PSR-6 Compatible)`"]
        
        L3["`**L3 Cache**
        Metadata Cache
        (Class Metadata)`"]
    end
    
    subgraph "Cache Operations"
        Read["`Read Operation`"]
        Write["`Write Operation`"]
        Invalidate["`Cache Invalidation`"]
        Cleanup["`Cache Cleanup`"]
    end
    
    subgraph "Cache Keys"
        EntityKey["`Entity Key:
        class_name:id`"]
        
        QueryKey["`Query Key:
        class_name:criteria_hash`"]
        
        MetadataKey["`Metadata Key:
        class_name:version`"]
    end
    
    Read --> L1
    L1 -->|Miss| L2
    L2 -->|Miss| L3
    L3 -->|Miss| Database[(Database)]
    
    Write --> L1
    Write --> L2
    Write --> L3
    
    Invalidate --> L1
    Invalidate --> L2
    Invalidate --> L3
    
    Cleanup --> CacheCleaner["`CacheCleaner Utility`"]
    CacheCleaner --> L1
    CacheCleaner --> L2
    CacheCleaner --> L3
    
    L1 -.-> EntityKey
    L2 -.-> QueryKey
    L3 -.-> MetadataKey
    
    style L1 fill:#e3f2fd
    style L2 fill:#f1f8e9
    style L3 fill:#fff3e0
    style Database fill:#ffebee
```

## 7. Error Handling и Exception Flow

```mermaid
graph TB
    subgraph "Exception Hierarchy"
        BaseEx["`Base Exception`"]
        CommitEx["`CommitFailedException`"]
        DataEx["`DataAdapter Exceptions`"]
        MetadataEx["`Metadata Exceptions`"]
        ValidationEx["`Value Object Validation`"]
    end
    
    subgraph "Error Sources"
        DB["`Database Errors`"]
        Network["`Network Errors`"]
        Validation["`Validation Errors`"]
        Config["`Configuration Errors`"]
    end
    
    subgraph "Error Handling"
        Rollback["`Transaction Rollback`"]
        Retry["`Retry Logic`"]
        Logging["`Error Logging`"]
        Recovery["`Recovery Strategies`"]
    end
    
    subgraph "Recovery Actions"
        ClearCache["`Clear Cache`"]
        ResetConnection["`Reset Connection`"]
        Detach["`Detach Entities`"]
        Notify["`Notify Listeners`"]
    end
    
    DB --> DataEx
    Network --> CommitEx
    Validation --> ValidationEx
    Config --> MetadataEx
    
    BaseEx --> CommitEx
    BaseEx --> DataEx
    BaseEx --> MetadataEx
    BaseEx --> ValidationEx
    
    CommitEx --> Rollback
    DataEx --> Retry
    MetadataEx --> Recovery
    ValidationEx --> Logging
    
    Rollback --> ClearCache
    Retry --> ResetConnection
    Recovery --> Detach
    Logging --> Notify
    
    style CommitEx fill:#ffcdd2
    style DataEx fill:#ffe0b2
    style MetadataEx fill:#f8bbd9
    style ValidationEx fill:#d1c4e9
```

## Заключение

Эти диаграммы показывают детальные взаимодействия компонентов Adaptive Entity Manager:

- **Инициализация**: Пошаговый процесс создания и настройки EntityManager
- **Жизненный цикл**: Состояния сущностей и переходы между ними
- **Запросы**: Полный цикл выполнения запросов с кешированием
- **Коммит**: Транзакционный процесс сохранения изменений
- **Value Objects**: Автоматическая конвертация между примитивами и объектами
- **Кеширование**: Многоуровневая стратегия кеширования
- **Обработка ошибок**: Комплексная система обработки исключений

Эта архитектура обеспечивает надежность, производительность и расширяемость ORM системы. 