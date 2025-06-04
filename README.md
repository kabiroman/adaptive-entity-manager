# Adaptive Entity Manager

A flexible PHP package implementing the Doctrine ObjectManager interface for seamlessly managing entities across different data sources: SQL databases, REST APIs, GraphQL endpoints, etc., using pluggable adapters.

## Overview

Adaptive Entity Manager (AEM) is a powerful entity management system that provides a unified interface for working with entities across multiple data sources. It implements Doctrine's ObjectManager interface while offering enhanced flexibility through its adapter-based architecture.

## Features

- Pluggable data source adapters
- Unified entity management interface
- Support for multiple data sources simultaneously
- Transaction management
- Lazy loading through proxy objects
- Flexible entity repository system
- Comprehensive metadata management
- Efficient unit of work implementation

## Requirements

- PHP 8.1 or higher
- Composer for dependency management

## Installation

Install the package via Composer:

```bash
composer require kabiroman/adaptive-entity-manager
```

## Dependencies

- doctrine/persistence: ^3.0 || ^4.0
- laminas/laminas-code: ^4.0
- psr/container: ^1.1 || ^2.0
- symfony/cache: ^6.0 || ^7.0
- symfony/string: ^6.0 || ^7.0

## Basic Usage

```php
// Create configuration
$config = new Config(
    entityFolder: "your_project_dir/src/Entity",
    entityNamespace: 'App\\Entity\\',
    cacheFolder: "your_project_dir/var/cache"
);

// Initialize the Entity Manager
$entityManager = new AdaptiveEntityManager(
    $config,
    new DefaultEntityMetadataProvider(),
    new DefaultEntityDataAdapterProvider()
);

// Work with entities
$entity = $entityManager->find(YourEntity::class, $id);
$entityManager->persist($entity);
$entityManager->flush();
```

## Key Components

### Entity Manager

The core component that manages entity operations:

```php
$entityManager->find(Entity::class, $id);      // Find an entity by ID
$entityManager->persist($entity);              // Stage an entity for persistence
$entityManager->remove($entity);               // Stage an entity for removal
$entityManager->flush();                       // Execute all staged operations
```

### Repositories

Custom repositories for entity-specific operations:

```php
$repository = $entityManager->getRepository(Entity::class);
$entities = $repository->findBy(['status' => 'active']);
```

### Unit of Work

Tracks entity states and manages transactions:

```php
$entityManager->beginTransaction();
try {
    // Perform operations
    $entityManager->flush();
    $entityManager->commit();
} catch (\Exception $e) {
    $entityManager->rollback();
    throw $e;
}
```

## Advanced Features

### Custom Data Adapters

Create custom adapters for different data sources by implementing the appropriate interfaces in the `DataAdapter` namespace.

### Event System

The Adaptive Entity Manager provides a flexible event system based on PSR-14, allowing you to hook into various stages of the entity lifecycle. This enables powerful extensibility and modularity, letting you execute custom logic before or after core entity operations.

**Key Events:**

- `PrePersistEvent`: Dispatched before an entity is persisted.
- `PostPersistEvent`: Dispatched after an entity has been persisted.
- `PreUpdateEvent`: Dispatched before an entity is updated.
- `PostUpdateEvent`: Dispatched after an entity has been updated.
- `PreRemoveEvent`: Dispatched before an entity is removed.
- `PostRemoveEvent`: Dispatched after an entity has been removed.

**Usage Example (Conceptual):**

To listen to events, you would implement a PSR-14 compatible event listener. For example, using a simple event dispatcher (or your framework's own, like Symfony's EventDispatcher):

```php
use Kabiroman\AEM\Event\PrePersistEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class MyEventListener
{
    public function onPrePersist(PrePersistEvent $event): void
    {
        $entity = $event->getEntity();
        // Perform custom logic before persistence, e.g., set creation date
        if (method_exists($entity, 'setCreatedAt') && $entity->getCreatedAt() === null) {
            $entity->setCreatedAt(new \DateTimeImmutable());
        }
        // You can stop propagation of the event if needed
        // $event->stopPropagation();
    }
}

// Assuming you have a PSR-14 EventDispatcher instance
/** @var EventDispatcherInterface $eventDispatcher */
$eventDispatcher = /* ... your event dispatcher instance ... */;

// In a non-Symfony project, you might need a ListenerProvider:
// $listenerProvider = new \League\Event\ListenerProvider();
// $listenerProvider->addListener(PrePersistEvent::class, [new MyEventListener(), 'onPrePersist']);
// $eventDispatcher = new \League\Event\EventDispatcher($listenerProvider);

// Add your listener to the dispatcher
// The exact method depends on your PSR-14 implementation
$eventDispatcher->addListener(PrePersistEvent::class, [new MyEventListener(), 'onPrePersist']);

// When AdaptiveEntityManager (specifically UnitOfWork) dispatches PrePersistEvent,
// MyEventListener::onPrePersist will be called.
```

### Data Adapter Example

The Adaptive Entity Manager allows you to create custom data adapters for different data sources. Here's a simple example of implementing a REST API data adapter for a User entity.

#### Basic Implementation

```php
<?php

namespace Example\DataAdapter;

use Kabiroman\AEM\DataAdapter\AbstractDataAdapter;
use GuzzleHttp\ClientInterface;

class UserApiAdapter extends AbstractDataAdapter
{
    private const API_ENDPOINT = 'https://api.example.com/users';

    public function __construct(
        private readonly ClientInterface $httpClient
    ) {}

    public function loadById(array $identifier): ?array
    {
        $response = $this->httpClient->request('GET', self::API_ENDPOINT . '/' . $identifier['id']);
        $data = json_decode($response->getBody()->getContents(), true);
        
        if (!$data) {
            return null;
        }

        // Convert response fields from snake_case to camelCase
        $this->toCamelCaseParams($data);
        
        return $data;
    }

    public function insert(array $row): array
    {
        // Convert entity fields from camelCase to snake_case
        $this->toSnakeCaseParams($row);
        
        $response = $this->httpClient->request('POST', self::API_ENDPOINT, [
            'json' => $row
        ]);
        
        $data = json_decode($response->getBody()->getContents(), true);
        $this->toCamelCaseParams($data);
        
        return $data;
    }

    public function update(array $identifier, array $row)
    {
        $this->toSnakeCaseParams($row);
        
        $this->httpClient->request('PUT', self::API_ENDPOINT . '/' . $identifier['id'], [
            'json' => $row
        ]);
    }

    public function delete(array $identifier)
    {
        $this->httpClient->request('DELETE', self::API_ENDPOINT . '/' . $identifier['id']);
    }

    public function loadAll(
        array $criteria = [],
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): array {
        $query = http_build_query(array_filter([
            'filter' => $criteria,
            'sort' => $orderBy,
            'limit' => $limit,
            'offset' => $offset,
        ]));

        $response = $this->httpClient->request('GET', self::API_ENDPOINT . '?' . $query);
        $data = json_decode($response->getBody()->getContents(), true);

        foreach ($data as &$row) {
            $this->toCamelCaseParams($row);
        }

        return $data;
    }

    public function refresh(array $identifier): array
    {
        return $this->loadById($identifier) ?? 
            throw new \RuntimeException('Entity not found');
    }
}
```

#### Usage Example

Here's how to use the custom data adapter with the Adaptive Entity Manager:

```php
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Example\Entity\User;
use Example\DataAdapter\UserApiAdapter;

// Create HTTP client
$httpClient = new \GuzzleHttp\Client();

// Create data adapter
$userAdapter = new UserApiAdapter($httpClient);

// Configure entity manager
$config = new Config([
    'dataAdapters' => [
        User::class => $userAdapter
    ]
]);

// Create entity manager
$entityManager = new AdaptiveEntityManager($config);

// Use the entity manager
$user = $entityManager->find(User::class, 1);
$user->setEmail('new@example.com');

$entityManager->persist($user);
$entityManager->flush();
```

#### Key Features

- **REST API Integration**: Simple implementation for RESTful APIs
- **Automatic Case Conversion**: Handles conversion between snake_case (API) and camelCase (entities)
- **CRUD Operations**: Complete set of Create, Read, Update, and Delete operations
- **Query Support**: Built-in support for filtering, sorting, and pagination
- **Clean Implementation**: Extends AbstractDataAdapter for common functionality

#### Available Methods

| Method | Description |
|--------|-------------|
| `loadById()` | Fetches a single entity by its identifier |
| `insert()` | Creates a new entity |
| `update()` | Updates an existing entity |
| `delete()` | Removes an entity |
| `loadAll()` | Retrieves multiple entities with optional filtering |
| `refresh()` | Reloads entity data from the data source |

#### Helper Methods

The `AbstractDataAdapter` provides useful case conversion methods:

- `toCamelCaseParams()`: Converts array keys to camelCase
- `toSnakeCaseParams()`: Converts array keys to snake_case

#### Tips

1. Always handle data transformation between your storage format and entity format
2. Implement proper error handling for API responses
3. Use type hints and return types for better code reliability
4. Consider implementing caching for frequently accessed data
5. Follow RESTful conventions for API endpoints

#### Next Steps

- Implement custom query builders for complex filtering
- Add caching layer for better performance
- Implement batch operations for multiple entities
- Add logging for debugging purposes
- Implement retry mechanisms for failed API calls

### Metadata Management

Comprehensive metadata handling for entity mapping and relationship management.

### Metadata example

```php
<?php

namespace App\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use App\Entity\User;
use App\Entity\Role;
use App\Entity\Post;
use App\DataAdapter\UserDataAdapter;

class UserMetadata extends AbstractClassMetadata
{
    public function __construct()
    {
        $this->metadata = [
            User::class => [
                // Specify the data adapter for this entity
                'dataAdapterClass' => UserDataAdapter::class,
                
                // Define identifier fields
                'id' => [
                    'id' => [
                        'type' => 'integer',
                        'column' => 'user_id',
                        'generator' => 'AUTO'
                    ]
                ],
                
                // Define regular fields
                'fields' => [
                    'email' => [
                        'type' => 'string',
                        'column' => 'email_address',
                        'nullable' => false
                    ],
                    'username' => [
                        'type' => 'string',
                        'column' => 'username',
                        'nullable' => false
                    ],
                    'createdAt' => [
                        'type' => 'datetime',
                        'column' => 'created_at',
                        'nullable' => false
                    ]
                ],
                
                // Define relationships
                'hasOne' => [
                    'role' => [
                        'targetEntity' => Role::class,
                        'joinColumn' => [
                            'name' => 'role_id',
                            'referencedColumnName' => 'id'
                        ],
                        'fetch' => 'LAZY'
                    ]
                ],
                'hasMany' => [
                    'posts' => [
                        'targetEntity' => Post::class,
                        'mappedBy' => 'author',
                        'fetch' => 'LAZY'
                    ]
                ],
                
                // Define lifecycle callbacks
                'lifecycleCallbacks' => [
                    'prePersist' => ['setCreatedAt']
                ]
            ]
        ];
    }
}
```

This metadata configuration provides:
- Clear mapping between database columns and entity properties
- Relationship management (One-to-One, One-to-Many)
- Automatic lifecycle event handling
- Type safety through explicit type definitions
- Flexible data adapter integration

User entity class that this metadata would describe:

```php
<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

class User
{
    private ?int $id = null;
    private string $email;
    private string $username;
    private string $password;
    private DateTime $createdAt;
    private bool $isActive = true;
    
    private Role $role;
    private Collection $posts;
    
    public function __construct()
    {
        $this->posts = new ArrayCollection();
    }
    
    // Lifecycle callback
    public function setCreatedAt(): void
    {
        $this->createdAt = new DateTime();
    }
    
    // Getters and setters...
}
```

### Entity Proxies

The system supports lazy loading through proxy objects, automatically generating proxy classes when needed.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the MIT license.

## Author

- **Ruslan Kabirov** - [kabirovruslan@gmail.com](mailto:kabirovruslan@gmail.com)

## Support

For issues, questions, or contributions, please use the GitHub issue tracker.
