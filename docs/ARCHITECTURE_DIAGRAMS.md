# Architecture Diagrams

Этот документ содержит диаграммы архитектуры Adaptive Entity Manager.

## 1. Общая архитектура системы

```mermaid
graph TB
    Client["Client Code"]
    
    subgraph "Core Layer"
        AEM["AdaptiveEntityManager<br/>(EntityManagerInterface)"]
        UOW["UnitOfWork"]
        Config["Config"]
    end
    
    subgraph "Persistence Layer"
        EP["EntityPersister"]
        DA["Data Adapters"]
        TC["TransactionalConnection"]
    end
    
    subgraph "Metadata System"
        CMF["ClassMetadataFactory"]
        CMP["ClassMetadataProvider"]
        CM["ClassMetadata"]
    end
    
    subgraph "Repository Layer"
        RF["RepositoryFactory"]
        ER["EntityRepository"]
    end
    
    subgraph "Value Objects"
        VO["ValueObject Interface"]
        VOC["ValueObject Converter"]
        VOR["ValueObject Registry"]
    end
    
    subgraph "Events"
        ED["EventDispatcher"]
        PE["Persistence Events"]
        LC["Lifecycle Callbacks"]
    end
    
    subgraph "Caching"
        Cache["PSR Cache"]
        CC["CacheCleaner"]
    end
    
    Client --> AEM
    AEM --> UOW
    AEM --> CMF
    AEM --> RF
    AEM --> Config
    
    UOW --> EP
    EP --> DA
    EP --> TC
    
    CMF --> CMP
    CMF --> CM
    CMF --> Cache
    
    RF --> ER
    ER --> AEM
    
    AEM --> VOR
    VOR --> VOC
    VOC --> VO
    
    UOW --> ED
    ED --> PE
    UOW --> LC
    
    style AEM fill:#e1f5fe
    style UOW fill:#f3e5f5
    style EP fill:#e8f5e8
    style VO fill:#fff3e0
```

## 2. Entity Manager и его взаимодействия

```mermaid
sequenceDiagram
    participant Client
    participant AEM as AdaptiveEntityManager
    participant UOW as UnitOfWork
    participant EP as EntityPersister
    participant DA as DataAdapter
    participant ED as EventDispatcher
    
    Client->>AEM: find(className, id)
    AEM->>UOW: getEntityPersister(metadata)
    UOW->>EP: loadById(identifier)
    EP->>DA: fetchData(criteria)
    DA-->>EP: rawData
    EP-->>AEM: entity
    AEM-->>Client: entity
    
    Client->>AEM: persist(entity)
    AEM->>UOW: getEntityPersister(metadata)
    UOW->>EP: addInsert(entity)
    
    Client->>AEM: flush()
    AEM->>UOW: commit()
    UOW->>ED: dispatch(PrePersistEvent)
    UOW->>EP: insert(entity)
    EP->>DA: saveData(entity)
    UOW->>ED: dispatch(PostPersistEvent)
```

## 3. Unit of Work Pattern

```mermaid
stateDiagram-v2
    [*] --> New
    New --> Managed: persist()
    Managed --> Modified: modify entity
    Modified --> Managed: no changes
    Managed --> Removed: remove()
    Modified --> Removed: remove()
    
    state "Unit of Work" as UOW {
        [*] --> Tracking
        Tracking --> PreFlush: flush()
        PreFlush --> Flushing: commit()
        
        state Flushing {
            [*] --> ProcessInserts
            ProcessInserts --> ProcessUpdates
            ProcessUpdates --> ProcessDeletes
            ProcessDeletes --> [*]
        }
        
        Flushing --> Committed: success
        Flushing --> RolledBack: error
        Committed --> [*]
        RolledBack --> [*]
    }
    
    Managed --> Detached: detach()
    Removed --> Detached: flush()
    Modified --> Detached: detach()
```

## 4. Data Adapter System

```mermaid
graph TB
    subgraph "Data Adapter Layer"
        EDAP["EntityDataAdapterProvider"]
        EDAF["EntityDataAdapterFactory"]
        ADA["AbstractDataAdapter"]
        EDA["EntityDataAdapter"]
    end
    
    subgraph "Concrete Adapters"
        CDA1["Custom Data Adapter 1"]
        CDA2["Custom Data Adapter 2"]
        CDA3["Custom Data Adapter 3"]
    end
    
    subgraph "Data Sources"
        DB["Database"]
        API["REST API"]
        File["File System"]
        Cache2["Cache Layer"]
    end
    
    EDAP --> EDAF
    EDAF --> ADA
    ADA --> EDA
    EDA --> CDA1
    EDA --> CDA2
    EDA --> CDA3
    
    CDA1 --> DB
    CDA2 --> API
    CDA3 --> File
    
    CDA1 --> Cache2
    CDA2 --> Cache2
    CDA3 --> Cache2
    
    style EDAP fill:#e3f2fd
    style ADA fill:#f1f8e9
    style CDA1 fill:#fff8e1
    style CDA2 fill:#fff8e1
    style CDA3 fill:#fff8e1
```

## 5. Value Object System

```mermaid
graph TB
    subgraph "Value Object Core"
        VOI["ValueObjectInterface"]
        AVO["AbstractValueObject"]
    end
    
    subgraph "Concrete Value Objects"
        Email["Email"]
        Money["Money"]
        UserId["UserId"]
    end
    
    subgraph "Conversion System"
        VOCI["ValueObjectConverterInterface"]
        DVOC["DefaultValueObjectConverter"]
        VOCR["ValueObjectConverterRegistry"]
    end
    
    subgraph "Integration"
        VOAEF["ValueObjectAwareEntityFactory"]
        EntityFactory["EntityFactory"]
    end
    
    VOI --> AVO
    AVO --> Email
    AVO --> Money
    AVO --> UserId
    
    VOCI --> DVOC
    DVOC --> VOCR
    VOCR --> Email
    VOCR --> Money
    VOCR --> UserId
    
    VOCR --> VOAEF
    VOAEF --> EntityFactory
    
    note1["Immutable objects<br/>defined by values<br/>not identity"]
    note2["Automatic conversion<br/>between primitive<br/>and object forms"]
    
    VOI -.-> note1
    VOCR -.-> note2
    
    style VOI fill:#e8eaf6
    style VOCR fill:#f3e5f5
    style VOAEF fill:#e0f2f1
```

## 6. Event System и Lifecycle

```mermaid
graph TB
    subgraph "Event Types"
        PRE["Pre Events"]
        POST["Post Events"]
        
        subgraph PRE
            PrePersist["PrePersistEvent"]
            PreUpdate["PreUpdateEvent"]
            PreRemove["PreRemoveEvent"]
        end
        
        subgraph POST
            PostPersist["PostPersistEvent"]
            PostUpdate["PostUpdateEvent"]
            PostRemove["PostRemoveEvent"]
        end
    end
    
    subgraph "Event System"
        ED["EventDispatcher<br/>(PSR-14)"]
        EntityEvent["EntityEvent<br/>(Base Class)"]
    end
    
    subgraph "Lifecycle Integration"
        UOW2["UnitOfWork"]
        LCHT["LifecycleCallbackHandlerTrait"]
        CM2["ClassMetadata"]
    end
    
    EntityEvent --> PrePersist
    EntityEvent --> PreUpdate
    EntityEvent --> PreRemove
    EntityEvent --> PostPersist
    EntityEvent --> PostUpdate
    EntityEvent --> PostRemove
    
    UOW2 --> ED
    UOW2 --> LCHT
    LCHT --> CM2
    
    ED --> PRE
    ED --> POST
    
    style ED fill:#fff3e0
    style UOW2 fill:#f3e5f5
    style EntityEvent fill:#e8f5e8
```

## 7. Metadata System

```mermaid
graph TB
    subgraph "Metadata Core"
        CMF2["ClassMetadataFactory"]
        CM3["ClassMetadata"]
        ACM["AbstractClassMetadata"]
    end
    
    subgraph "Providers"
        CMP2["ClassMetadataProvider"]
        DEMP["DefaultEntityMetadataProvider"]
        CEMP["CachedEntityMetadataProvider"]
    end
    
    subgraph "Factory System"
        EMF["EntityMetadataFactory"]
        OEMF["OptimizedEntityMetadataFactory"]
        MSF["MetadataSystemFactory"]
    end
    
    subgraph "Caching Layer"
        CachePool["PSR CacheItemPool"]
        CacheCleaner["CacheCleaner"]
    end
    
    ACM --> CM3
    CMF2 --> CM3
    
    CMP2 --> DEMP
    DEMP --> CEMP
    CEMP --> CachePool
    
    EMF --> CMF2
    OEMF --> CMF2
    MSF --> EMF
    MSF --> OEMF
    
    CMF2 --> CMP2
    CachePool --> CacheCleaner
    
    style CMF2 fill:#e3f2fd
    style CachePool fill:#fff3e0
    style MSF fill:#f1f8e9
```

## Заключение

Adaptive Entity Manager представляет собой модульную архитектуру ORM с следующими ключевыми особенностями:

- **Адаптивность**: Поддержка различных источников данных через систему адаптеров
- **Value Objects**: Встроенная поддержка объектов-значений с автоматической конвертацией
- **Events**: Полная система событий жизненного цикла сущностей
- **Caching**: Многоуровневое кеширование метаданных и данных
- **Unit of Work**: Эффективное управление изменениями и транзакциями
- **Extensibility**: Возможность расширения через интерфейсы и фабрики 