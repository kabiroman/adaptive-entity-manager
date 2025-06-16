<?php

namespace Kabiroman\AEM\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class SimpleFileCache implements CacheItemPoolInterface
{
    private array $deferredItems = [];
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function getItem(string $key): CacheItemInterface
    {
        $this->validateKey($key);
        return new SimpleCacheItem($key, $this->loadFromFile($key));
    }

    public function getItems(array $keys = []): iterable
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }
        return $items;
    }

    public function hasItem(string $key): bool
    {
        $this->validateKey($key);
        return file_exists($this->getFilePath($key));
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*.cache');
        if ($files === false) {
            return true;
        }
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }
        return true;
    }

    public function deleteItem(string $key): bool
    {
        $this->validateKey($key);
        $filePath = $this->getFilePath($key);
        return !file_exists($filePath) || unlink($filePath);
    }

    public function deleteItems(array $keys): bool
    {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->deleteItem($key)) {
                $success = false;
            }
        }
        return $success;
    }

    public function save(CacheItemInterface $item): bool
    {
        $filePath = $this->getFilePath($item->getKey());
        $data = [
            'value' => $item->get(),
            'expires_at' => $item instanceof SimpleCacheItem ? $item->getExpiresAt() : null,
            'hit' => true
        ];
        
        return file_put_contents($filePath, serialize($data)) !== false;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->deferredItems[$item->getKey()] = $item;
        return true;
    }

    public function commit(): bool
    {
        $success = true;
        foreach ($this->deferredItems as $item) {
            if (!$this->save($item)) {
                $success = false;
            }
        }
        $this->deferredItems = [];
        return $success;
    }

    private function loadFromFile(string $key): array
    {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return ['value' => null, 'hit' => false, 'expires_at' => null];
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return ['value' => null, 'hit' => false, 'expires_at' => null];
        }

        $data = unserialize($content);
        if ($data === false) {
            return ['value' => null, 'hit' => false, 'expires_at' => null];
        }

        // Check expiration
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            unlink($filePath);
            return ['value' => null, 'hit' => false, 'expires_at' => null];
        }

        return $data;
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    private function validateKey(string $key): void
    {
        if (preg_match('/[{}\(\)\/\\\\@:]/', $key)) {
            throw new class('Invalid cache key') extends \InvalidArgumentException implements InvalidArgumentException {};
        }
    }
}
