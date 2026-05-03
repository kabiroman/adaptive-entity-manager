<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Entity;

use Kabiroman\AEM\Tests\Mock\ValueObject\DomainPlainEmail;

final class DomainVoUnionEntity
{
    private int $id;
    /** @var DomainPlainEmail|\stdClass Union-typed property for unsupported mapping test. */
    private DomainPlainEmail|\stdClass $plainEmail;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /** @return DomainPlainEmail|\stdClass */
    public function getPlainEmail(): DomainPlainEmail|\stdClass
    {
        return $this->plainEmail;
    }

    /** @param DomainPlainEmail|\stdClass $plainEmail */
    public function setPlainEmail(DomainPlainEmail|\stdClass $plainEmail): void
    {
        $this->plainEmail = $plainEmail;
    }
}
