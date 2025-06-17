<?php

namespace Kabiroman\AEM\Tests\Mock\Entity;

use Kabiroman\AEM\ValueObject\Common\Email;
use Kabiroman\AEM\ValueObject\Common\Money;
use Kabiroman\AEM\ValueObject\Common\UserId;

/**
 * Test entity with Value Object properties.
 */
class ValueObjectEntity
{
    private int $id;
    private string $name;
    private Email $email;
    private Money $price;
    private UserId $createdBy;
    private ?Email $secondaryEmail = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function setEmail(Email $email): void
    {
        $this->email = $email;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    public function setPrice(Money $price): void
    {
        $this->price = $price;
    }

    public function getCreatedBy(): UserId
    {
        return $this->createdBy;
    }

    public function setCreatedBy(UserId $createdBy): void
    {
        $this->createdBy = $createdBy;
    }

    public function getSecondaryEmail(): ?Email
    {
        return $this->secondaryEmail;
    }

    public function setSecondaryEmail(?Email $secondaryEmail): void
    {
        $this->secondaryEmail = $secondaryEmail;
    }
}
