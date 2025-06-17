<?php

namespace Kabiroman\AEM\ValueObject\Common;

use Kabiroman\AEM\ValueObject\AbstractValueObject;
use InvalidArgumentException;

/**
 * Email Value Object.
 * Represents a validated email address.
 */
final class Email extends AbstractValueObject
{
    public function __construct(
        private readonly string $email
    ) {
        $this->validate($email);
    }

    public static function fromPrimitive(mixed $value): static
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Email must be a string');
        }

        return new self($value);
    }

    public function toPrimitive(): string
    {
        return $this->email;
    }

    public function getValue(): string
    {
        return $this->email;
    }

    public function getDomain(): string
    {
        return substr($this->email, strpos($this->email, '@') + 1);
    }

    public function getLocalPart(): string
    {
        return substr($this->email, 0, strpos($this->email, '@'));
    }

    private function validate(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                sprintf('"%s" is not a valid email address', $email)
            );
        }
    }
}
