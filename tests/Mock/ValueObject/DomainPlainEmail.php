<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\ValueObject;

/**
 * Domain-style VO without AEM ValueObjectInterface (for metadata from/to tests).
 */
final class DomainPlainEmail implements \Stringable
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if ($value === '' || !str_contains($value, '@')) {
            throw new \InvalidArgumentException('Invalid plain email.');
        }

        return new self($value);
    }

    /** Used by tests: factory returns a scalar instead of this class. */
    public static function fromStringReturnsScalar(string $value): string
    {
        return $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
