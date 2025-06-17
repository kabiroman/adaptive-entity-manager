<?php

namespace Kabiroman\AEM\ValueObject\Converter;

use Kabiroman\AEM\ValueObject\ValueObjectInterface;

/**
 * Interface for Value Object converters.
 * Converters handle the transformation between ValueObjects and database values.
 */
interface ValueObjectConverterInterface
{
    /**
     * Check if this converter can handle the given class.
     */
    public function supports(string $className): bool;

    /**
     * Convert database value to ValueObject.
     */
    public function convertToPHP(mixed $value, string $className): ?ValueObjectInterface;

    /**
     * Convert ValueObject to database value.
     */
    public function convertToDatabase(?ValueObjectInterface $valueObject): mixed;
}
