<?php

namespace Kabiroman\AEM\ValueObject\Converter;

use Kabiroman\AEM\ValueObject\ValueObjectInterface;

/**
 * Registry for Value Object converters.
 * Manages multiple converters and finds the appropriate one for each class.
 */
class ValueObjectConverterRegistry
{
    /** @var ValueObjectConverterInterface[] */
    private array $converters = [];

    private DefaultValueObjectConverter $defaultConverter;

    public function __construct()
    {
        $this->defaultConverter = new DefaultValueObjectConverter();
    }

    /**
     * Register a converter for specific Value Object types.
     */
    public function addConverter(ValueObjectConverterInterface $converter): void
    {
        $this->converters[] = $converter;
    }

    /**
     * Find appropriate converter for the given class.
     */
    public function getConverter(string $className): ValueObjectConverterInterface
    {
        foreach ($this->converters as $converter) {
            if ($converter->supports($className)) {
                return $converter;
            }
        }

        // Fall back to default converter
        if ($this->defaultConverter->supports($className)) {
            return $this->defaultConverter;
        }

        throw new \InvalidArgumentException(
            sprintf('No converter found for Value Object class "%s"', $className)
        );
    }

    /**
     * Convert database value to ValueObject.
     */
    public function convertToPHP(mixed $value, string $className): ?ValueObjectInterface
    {
        return $this->getConverter($className)->convertToPHP($value, $className);
    }

    /**
     * Convert ValueObject to database value.
     */
    public function convertToDatabase(?ValueObjectInterface $valueObject): mixed
    {
        if ($valueObject === null) {
            return null;
        }

        return $this->getConverter(get_class($valueObject))->convertToDatabase($valueObject);
    }

    /**
     * Check if the given class can be handled by any converter.
     */
    public function supports(string $className): bool
    {
        try {
            $this->getConverter($className);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
