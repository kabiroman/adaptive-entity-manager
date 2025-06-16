# Caching System for Adaptive Entity Manager

## Overview

The Adaptive Entity Manager now includes an optimized caching system for entity metadata that significantly improves performance by reducing file system scanning and metadata generation overhead.

## Features

- **Multi-level caching**: Runtime (in-memory) + Persistent (file/cache backend)
- **PSR-6 compatible**: Works with any PSR-6 cache implementation
- **Framework agnostic**: No dependencies on specific frameworks
- **Automatic cache invalidation**: Support for cache TTL and manual invalidation
- **Backward compatibility**: Can be disabled to use legacy system

## Architecture

### Components

1. **CachedEntityMetadataProvider**: Decorator that adds caching to any ClassMetadataProvider
2. **OptimizedEntityMetadataFactory**: Improved metadata factory with optimized scanning
3. **SimpleFileCache**: Basic PSR-6 implementation for when no external cache is available
4. **MetadataSystemFactory**: Factory for easy setup of optimized metadata system

### Caching Levels

1. **Runtime Cache**: Fast in-memory lookup for frequently accessed metadata
2. **Persistent Cache**: File-based or external cache backend for cross-request persistence
3. **Entity Scanning Cache**: Cached results of file system scanning

## Basic Usage

### Automatic Setup (Recommended)

```php
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\DefaultEntityMetadataProvider;

$config = new Config(
    entityFolder: '/path/to/entities',
    entityNamespace: 'App\\Entity\\',
    cacheFolder: '/path/to/cache'
);

// Optimized system is enabled by default
$entityManager = new AdaptiveEntityManager(
    $config,
    new DefaultEntityMetadataProvider(),
    $dataAdapterProvider
);
```

### Manual Setup with Custom Cache

```php
use Kabiroman\AEM\Metadata\MetadataSystemFactory;
use Symfony\Component\Cache\Adapter\RedisAdapter;

// Use Redis for persistent cache
$redisCache = new RedisAdapter($redisClient);

$metadataSystem = MetadataSystemFactory::createOptimized(
    $config,
    $baseProvider,
    $redisCache,
    3600 // TTL in seconds
);

$entityManager = new AdaptiveEntityManager(
    $config,
    $baseProvider,
    $dataAdapterProvider,
    metadataFactory: $metadataSystem['factory']
);
```

### Legacy Mode

```php
// Disable optimized caching
$entityManager = new AdaptiveEntityManager(
    $config,
    $classMetadataProvider,
    $dataAdapterProvider,
    useOptimizedMetadata: false
);
```

## Advanced Configuration

### Custom Cache Provider

```php
use Kabiroman\AEM\Metadata\CachedEntityMetadataProvider;

$cachedProvider = new CachedEntityMetadataProvider(
    $baseProvider,
    $customCache,
    cacheTtl: 7200 // 2 hours
);
```

### Cache Management

```php
// Warm up cache
$cachedProvider->warmUp(['Entity1', 'Entity2', 'Entity3']);

// Clear all cache
$cachedProvider->clearCache();

// Clear specific entity cache
$cachedProvider->clearCache('App\\Entity\\User');

// Get cache statistics
$stats = $cachedProvider->getStats();
echo "Runtime cache size: " . $stats['runtime_cache_size'];
```

### Factory-level Cache Control

```php
// Clear factory caches
$factory->clearCache();

// Manual warm-up
$factory->warmUp();
```

## Performance Benefits

### Before Optimization

- File system scan on every request
- Metadata parsing for each entity access
- No persistent storage of scan results

### After Optimization

- File system scan cached with TTL
- Multi-level metadata caching
- Persistent cache across requests
- Runtime cache for fastest access

### Expected Performance Gains

- **Cold start**: 50-70% faster metadata loading
- **Warm cache**: 90-95% faster subsequent requests
- **Memory usage**: Minimal increase due to runtime cache
- **Disk I/O**: Significantly reduced file system operations

## Cache Backends

### Built-in SimpleFileCache

```php
use Kabiroman\AEM\Cache\SimpleFileCache;

$cache = new SimpleFileCache('/path/to/cache/dir');
```

### External Cache Backends

Any PSR-6 compatible cache can be used:

```php
// Symfony Cache
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
$cache = new FilesystemAdapter();

// Redis
use Symfony\Component\Cache\Adapter\RedisAdapter;
$cache = new RedisAdapter($redis);

// APCu
use Symfony\Component\Cache\Adapter\ApcuAdapter;
$cache = new ApcuAdapter();

// Multi-tier
use Symfony\Component\Cache\Adapter\ChainAdapter;
$cache = new ChainAdapter([
    new ApcuAdapter(),      // Fast memory cache
    new RedisAdapter($redis) // Persistent cache
]);
```

## Best Practices

### Production Setup

1. **Use external cache backend** (Redis, Memcached) for multi-server setups
2. **Set appropriate TTL** based on your deployment frequency
3. **Implement cache warming** in deployment scripts
4. **Monitor cache hit rates** to optimize TTL values

### Development Setup

1. **Use SimpleFileCache** for local development
2. **Clear cache after entity changes** during development
3. **Use shorter TTL** to see changes quickly

### Cache Invalidation Strategy

```php
// Clear cache after entity schema changes
$cachedProvider->clearCache();

// Or clear specific entities
$cachedProvider->clearCache('App\\Entity\\ChangedEntity');

// Warm up after clearing
$cachedProvider->warmUp($allEntityNames);
```

## Troubleshooting

### Common Issues

1. **Cache not working**: Check file permissions on cache directory
2. **Stale metadata**: Reduce TTL or implement proper invalidation
3. **Memory issues**: Monitor runtime cache size with `getStats()`

### Debugging

```php
// Check if caching is active
$stats = $cachedProvider->getStats();
var_dump($stats);

// Force cache miss for testing
$cachedProvider->clearCache('TestEntity');
$metadata = $cachedProvider->getClassMetadata('TestEntity');
```

### Performance Monitoring

```php
// Measure cache effectiveness
$start = microtime(true);
$metadata = $provider->getClassMetadata('Entity');
$time = microtime(true) - $start;

echo "Metadata load time: " . ($time * 1000) . "ms";
```

## Migration Guide

### From Legacy System

1. **No code changes required** - optimized system is enabled by default
2. **Optional**: Configure external cache backend for better performance
3. **Optional**: Implement cache warming in deployment pipeline

### Backward Compatibility

The new system is fully backward compatible. To disable:

```php
$entityManager = new AdaptiveEntityManager(
    // ... other parameters
    useOptimizedMetadata: false
);
``` 