<?php

namespace Kabiroman\AEM\Metadata;

use Kabiroman\AEM\Cache\SimpleFileCache;
use Kabiroman\AEM\Config;
use Psr\Cache\CacheItemPoolInterface;

class MetadataSystemFactory
{
    public static function createOptimized(
        Config $config,
        ?ClassMetadataProvider $baseProvider = null,
        ?CacheItemPoolInterface $cache = null,
        int $cacheTtl = 3600
    ): array {
        // Use default provider if none provided
        $baseProvider = $baseProvider ?? new DefaultEntityMetadataProvider();
        
        // Create default cache if none provided
        if ($cache === null) {
            $cacheDir = $config->getCacheFolder() . '/metadata';
            $cache = new SimpleFileCache($cacheDir);
        }

        // Create cached provider
        $cachedProvider = new CachedEntityMetadataProvider($baseProvider, $cache, $cacheTtl);
        
        // Create optimized factory
        $factory = new OptimizedEntityMetadataFactory($config, $cachedProvider, $cache, $cacheTtl);

        return [
            'provider' => $cachedProvider,
            'factory' => $factory,
            'cache' => $cache
        ];
    }

    public static function createLegacy(
        Config $config,
        ClassMetadataProvider $provider
    ): EntityMetadataFactory {
        return new EntityMetadataFactory($config, $provider);
    }

    public static function createProvider(
        ClassMetadataProvider $baseProvider,
        ?CacheItemPoolInterface $cache = null,
        string $cacheDir = '/tmp/aem_cache',
        int $cacheTtl = 3600
    ): ClassMetadataProvider {
        if ($cache === null) {
            $cache = new SimpleFileCache($cacheDir);
        }

        return new CachedEntityMetadataProvider($baseProvider, $cache, $cacheTtl);
    }
}
