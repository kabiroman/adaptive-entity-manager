<?php

namespace Kabiroman\AEM\Tests\Mock\Entity;

use DateTime;

class MockEntity
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

    public function setId(int $id): MockEntity
    {
        $this->id = $id;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): MockEntity
    {
        $this->name = $name;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): MockEntity
    {
        $this->active = $active;
        return $this;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTime $createdAt): MockEntity
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): MockEntity
    {
        $this->price = $price;
        return $this;
    }

    public function getNullable(): ?string
    {
        return $this->nullable;
    }

    public function setNullable(?string $nullable): MockEntity
    {
        $this->nullable = $nullable;
        return $this;
    }
}
