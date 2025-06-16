<?php

namespace Kabiroman\AEM\Metadata;

use Kabiroman\AEM\ClassMetadata;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class CachedEntityMetadataProvider implements ClassMetadataProvider
{
    private array $runtimeCache = [];
    
    public function __construct(
        private readonly ClassMetadataProvider $decoratedProvider,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cacheTtl = 3600
    ) {}

    public function getClassMetadata(string $entityName): ?ClassMetadata
    {
        // Runtime cache - fastest lookup
        if (isset($this->runtimeCache[$entityName])) {
            return $this->runtimeCache[$entityName];
        }

        // Persistent cache lookup
        try {
            $cacheKey = $this->getCacheKey($entityName);
            $cacheItem = $this->cache->getItem($cacheKey);
            
            if ($cacheItem->isHit()) {
                $metadata = $cacheItem->get();
                $this->runtimeCache[$entityName] = $metadata;
                return $metadata;
            }
        } catch (InvalidArgumentException $e) {
            // Fall through to decorated provider
        }

        // Load from decorated provider
        $metadata = $this->decoratedProvider->getClassMetadata($entityName);
        
        if ($metadata !== null) {
            // Store in both caches
            $this->runtimeCache[$entityName] = $metadata;
            $this->storePersistentCache($entityName, $metadata);
        }

        return $metadata;
    }

    public function warmUp(array $entityNames): void
    {
        foreach ($entityNames as $entityName) {
            $this->getClassMetadata($entityName);
        }
    }

    public function clearCache(?string $entityName = null): void
    {
        if ($entityName === null) {
            // Clear all caches
            $this->runtimeCache = [];
            $this->cache->clear();
        } else {
            // Clear specific entity cache
            unset($this->runtimeCache[$entityName]);
            try {
                $this->cache->deleteItem($this->getCacheKey($entityName));
            } catch (InvalidArgumentException $e) {
                // Ignore invalid cache key errors
            }
        }
    }

    public function getStats(): array
    {
        return [
            'runtime_cache_size' => count($this->runtimeCache),
            'runtime_cache_entities' => array_keys($this->runtimeCache)
        ];
    }

    private function getCacheKey(string $entityName): string
    {
        return 'aem_metadata_' . md5($entityName);
    }

    private function storePersistentCache(string $entityName, ClassMetadata $metadata): void
    {
        try {
            $cacheKey = $this->getCacheKey($entityName);
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($metadata);
            $cacheItem->expiresAfter($this->cacheTtl);
            $this->cache->save($cacheItem);
        } catch (InvalidArgumentException $e) {
            // Ignore cache storage errors - system should continue working
        }
    }
}
