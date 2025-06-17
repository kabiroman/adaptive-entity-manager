<?php

namespace Kabiroman\AEM\ValueObject\Converter;

use Kabiroman\AEM\ValueObject\ValueObjectInterface;
use ReflectionClass;

/**
 * Default converter for Value Objects.
 * Uses the ValueObject's own fromPrimitive/toPrimitive methods.
 */
class DefaultValueObjectConverter implements ValueObjectConverterInterface
{
    public function supports(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);
        return $reflection->implementsInterface(ValueObjectInterface::class);
    }

    public function convertToPHP(mixed $value, string $className): ?ValueObjectInterface
    {
        if ($value === null) {
            return null;
        }

        if (!$this->supports($className)) {
            throw new \InvalidArgumentException(
                sprintf('Class "%s" does not implement ValueObjectInterface', $className)
            );
        }

        // Call static method fromPrimitive on the ValueObject class
        return $className::fromPrimitive($value);
    }

    public function convertToDatabase(?ValueObjectInterface $valueObject): mixed
    {
        return $valueObject?->toPrimitive();
    }
}
