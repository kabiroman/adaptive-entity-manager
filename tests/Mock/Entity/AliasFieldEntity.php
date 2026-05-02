<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Entity;

use DateTime;

/**
 * Test entity with metadata that uses column aliases and boolean source values (see AliasFieldEntityMetadata).
 */
class AliasFieldEntity
{
    private int $id;

    private bool $active = false;

    private DateTime $createdAt;

    private string $name;

    private float $price;

    private ?string $nullable;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getNullable(): ?string
    {
        return $this->nullable;
    }

    public function setNullable(?string $nullable): self
    {
        $this->nullable = $nullable;

        return $this;
    }
}
