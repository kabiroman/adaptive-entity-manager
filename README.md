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

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This package is open-sourced software licensed under the MIT license.

## Author

- **Ruslan Kabirov** - [kabirovruslan@gmail.com](mailto:kabirovruslan@gmail.com)

## Support

For issues, questions, or contributions, please use the GitHub issue tracker.
