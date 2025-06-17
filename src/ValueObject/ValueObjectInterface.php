<?php

namespace Kabiroman\AEM\ValueObject;

/**
 * Base interface for all Value Objects.
 * Value Objects are immutable objects that are defined by their values rather than identity.
 */
interface ValueObjectInterface
{
    /**
     * Convert the value object to a primitive value for database storage.
     * This could be a string, array, or other serializable type.
     */
    public function toPrimitive(): mixed;

    /**
     * Create a value object instance from a primitive value.
     * This method should validate the input and throw an exception if invalid.
     */
    public static function fromPrimitive(mixed $value): static;

    /**
     * Check if this value object equals another value object.
     */
    public function equals(ValueObjectInterface $other): bool;

    /**
     * Get string representation of the value object.
     */
    public function __toString(): string;
}
