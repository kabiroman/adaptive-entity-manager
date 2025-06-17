<?php

namespace Kabiroman\AEM\ValueObject\Common;

use Kabiroman\AEM\ValueObject\AbstractValueObject;
use InvalidArgumentException;

/**
 * User ID Value Object.
 * Represents a validated user identifier.
 */
final class UserId extends AbstractValueObject
{
    public function __construct(
        private readonly int $id
    ) {
        $this->validate($id);
    }

    public static function fromPrimitive(mixed $value): static
    {
        if (is_string($value)) {
            $value = (int)$value;
        }

        if (!is_int($value)) {
            throw new InvalidArgumentException('User ID must be an integer');
        }

        return new self($value);
    }

    public function toPrimitive(): int
    {
        return $this->id;
    }

    public function getValue(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return (string)$this->id;
    }

    private function validate(int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('User ID must be a positive integer');
        }
    }
}
