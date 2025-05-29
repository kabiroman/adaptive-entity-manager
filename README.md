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

### Entity Proxies

The system supports lazy loading through proxy objects, automatically generating proxy classes when needed.

### Metadata Management

Comprehensive metadata handling for entity mapping and relationship management.

### Metadata Management

Comprehensive metadata handling for entity mapping and relationship management. Here's an example of entity metadata configuration:

Comprehensive example:
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
## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the MIT license.

## Author

- **Ruslan Kabirov** - [kabirovruslan@gmail.com](mailto:kabirovruslan@gmail.com)

## Support

For issues, questions, or contributions, please use the GitHub issue tracker.
