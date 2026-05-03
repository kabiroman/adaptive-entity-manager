<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Entity;

use Kabiroman\AEM\Tests\Mock\ValueObject\DomainPlainEmail;

/** Same shape as DomainVoEntity; metadata uses a broken factory return type. */
final class DomainVoBadFactoryEntity
{
    private int $id;
    private string $name;
    private DomainPlainEmail $plainEmail;

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

    public function getPlainEmail(): DomainPlainEmail
    {
        return $this->plainEmail;
    }

    public function setPlainEmail(DomainPlainEmail $plainEmail): void
    {
        $this->plainEmail = $plainEmail;
    }
}
