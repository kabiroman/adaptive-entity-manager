<?php

namespace Kabiroman\AEM\Tests\Metadata;

use Kabiroman\AEM\Cache\SimpleFileCache;
use Kabiroman\AEM\ClassMetadata;
use Kabiroman\AEM\Metadata\CachedEntityMetadataProvider;
use Kabiroman\AEM\Metadata\ClassMetadataProvider;
use PHPUnit\Framework\TestCase;

class CachedEntityMetadataProviderTest extends TestCase
{
    private CachedEntityMetadataProvider $provider;
    private ClassMetadataProvider $decoratedProvider;
    private SimpleFileCache $cache;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/aem_test_cache_' . uniqid();
        $this->cache = new SimpleFileCache($this->cacheDir);
        
        $this->decoratedProvider = $this->createMock(ClassMetadataProvider::class);
        $this->provider = new CachedEntityMetadataProvider(
            $this->decoratedProvider,
            $this->cache,
            3600
        );
    }

    protected function tearDown(): void
    {
        // Clean up cache directory
        if (is_dir($this->cacheDir)) {
            $files = glob($this->cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->cacheDir);
        }
    }

    public function testGetClassMetadataFirstTime(): void
    {
        $entityName = 'TestEntity';
        $metadata = $this->createMock(ClassMetadata::class);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with($entityName)
            ->willReturn($metadata);

        $result = $this->provider->getClassMetadata($entityName);

        $this->assertSame($metadata, $result);
    }

    public function testGetClassMetadataFromRuntimeCache(): void
    {
        $entityName = 'TestEntity';
        $metadata = $this->createMock(ClassMetadata::class);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with($entityName)
            ->willReturn($metadata);

        // First call - loads from decorated provider
        $result1 = $this->provider->getClassMetadata($entityName);
        
        // Second call - should load from runtime cache
        $result2 = $this->provider->getClassMetadata($entityName);

        $this->assertSame($metadata, $result1);
        $this->assertSame($metadata, $result2);
    }

    public function testGetClassMetadataFromPersistentCache(): void
    {
        $entityName = 'TestEntity';
        $metadata = $this->createMock(ClassMetadata::class);

        $this->decoratedProvider
            ->expects($this->once())
            ->method('getClassMetadata')
            ->with($entityName)
            ->willReturn($metadata);

        // Load metadata and let it cache
        $this->provider->getClassMetadata($entityName);

        // Create new provider instance (simulating new request)
        $newProvider = new CachedEntityMetadataProvider(
            $this->decoratedProvider,
            $this->cache,
            3600
        );

        // This should load from persistent cache, not call decorated provider again
        $result = $newProvider->getClassMetadata($entityName);

        $this->assertInstanceOf(ClassMetadata::class, $result);
    }

    public function testWarmUp(): void
    {
        $entityNames = ['Entity1', 'Entity2', 'Entity3'];
        $metadata = $this->createMock(ClassMetadata::class);

        $this->decoratedProvider
            ->expects($this->exactly(3))
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $this->provider->warmUp($entityNames);

        // Check runtime cache
        $stats = $this->provider->getStats();
        $this->assertEquals(3, $stats['runtime_cache_size']);
        $this->assertEquals($entityNames, $stats['runtime_cache_entities']);
    }

    public function testClearCache(): void
    {
        $entityName = 'TestEntity';
        $metadata = $this->createMock(ClassMetadata::class);

        $this->decoratedProvider
            ->expects($this->exactly(2))
            ->method('getClassMetadata')
            ->with($entityName)
            ->willReturn($metadata);

        // Load metadata
        $this->provider->getClassMetadata($entityName);

        // Verify it's cached
        $stats = $this->provider->getStats();
        $this->assertEquals(1, $stats['runtime_cache_size']);

        // Clear cache
        $this->provider->clearCache();

        // Verify runtime cache is empty
        $stats = $this->provider->getStats();
        $this->assertEquals(0, $stats['runtime_cache_size']);

        // Next call should hit decorated provider again
        $this->provider->getClassMetadata($entityName);
    }

    public function testClearSpecificEntityCache(): void
    {
        $entityName1 = 'Entity1';
        $entityName2 = 'Entity2';
        $metadata = $this->createMock(ClassMetadata::class);

        $this->decoratedProvider
            ->expects($this->exactly(3))
            ->method('getClassMetadata')
            ->willReturn($metadata);

        // Load both entities
        $this->provider->getClassMetadata($entityName1);
        $this->provider->getClassMetadata($entityName2);

        // Clear only entity1
        $this->provider->clearCache($entityName1);

        $stats = $this->provider->getStats();
        $this->assertEquals(1, $stats['runtime_cache_size']);
        $this->assertEquals([$entityName2], $stats['runtime_cache_entities']);

        // Entity1 should load from decorated provider again
        $this->provider->getClassMetadata($entityName1);
    }
}
