<?php

declare(strict_types=1);

namespace Examples\Ddd\Domain;

/**
 * Pure domain type: no Kabiroman\AEM classes or ValueObjectInterface.
 * Mapped from storage via metadata options {@see NewsletterSubscriberMetadata}.
 */
final class EmailAddress implements \Stringable
{
    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address: ' . $value);
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
