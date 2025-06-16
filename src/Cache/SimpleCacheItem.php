<?php

namespace Kabiroman\AEM\Cache;

use DateInterval;
use DateTime;
use DateTimeInterface;
use Psr\Cache\CacheItemInterface;

class SimpleCacheItem implements CacheItemInterface
{
    private mixed $value;
    private bool $hit;
    private ?int $expiresAt;

    public function __construct(private string $key, array $data)
    {
        $this->value = $data['value'];
        $this->hit = $data['hit'] ?? false;
        $this->expiresAt = $data['expires_at'] ?? null;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        if ($this->expiresAt !== null && $this->expiresAt < time()) {
            return false;
        }
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;
        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        if ($expiration === null) {
            $this->expiresAt = null;
        } else {
            $this->expiresAt = $expiration->getTimestamp();
        }
        return $this;
    }

    public function expiresAfter(DateInterval|int|null $time): static
    {
        if ($time === null) {
            $this->expiresAt = null;
        } elseif ($time instanceof DateInterval) {
            $dateTime = new DateTime();
            $dateTime->add($time);
            $this->expiresAt = $dateTime->getTimestamp();
        } else {
            $this->expiresAt = time() + $time;
        }
        return $this;
    }

    public function getExpiresAt(): ?int
    {
        return $this->expiresAt;
    }
}
