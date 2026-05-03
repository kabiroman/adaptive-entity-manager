<?php

namespace Kabiroman\AEM;

use InvalidArgumentException;
use Kabiroman\AEM\Constant\FetchModeEnum;
use Kabiroman\AEM\Constant\FieldTypeEnum;
use Kabiroman\AEM\EntityProxy\EntityProxyFactory;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;
use Kabiroman\AEM\ValueObject\ValueObjectInterface;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionClass;
use ReflectionUnionType;

/**
 * Enhanced EntityFactory with Value Object support.
 * Extends base EntityFactory to handle ValueObject properties.
 */
class ValueObjectAwareEntityFactory extends EntityFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityProxyFactory $entityProxyFactory,
        private readonly ValueObjectConverterRegistry $valueObjectRegistry
    ) {
        parent::__construct($entityManager, $entityProxyFactory);
    }

    public function setIdentifierValue(object $entity, ClassMetadata $classMetadata, array $row): void
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $fieldNames = $classMetadata->getFieldNames();

        foreach ($fieldNames as $fieldName) {
            if (!$classMetadata->isIdentifier($fieldName)) {
                continue;
            }

            if (!$reflectionClass->hasProperty($fieldName)) {
                throw new InvalidArgumentException(
                    sprintf('Property "%s" does not exist in class "%s".', $fieldName, $reflectionClass->getName())
                );
            }

            $property = $reflectionClass->getProperty($fieldName);
            $propertyType = $property->getType();

            if (!key_exists($column = $classMetadata->getColumnOfField($fieldName), $row)) {
                throw new InvalidArgumentException(sprintf('Row key "%s" does not exist', $column));
            }

            if (!$typeOfField = $classMetadata->getTypeOfField($fieldName)) {
                throw new InvalidArgumentException('Type of field "' . $fieldName . '" does not exist.');
            }

            $value = $row[$classMetadata->getColumnOfField($fieldName)];

            // Handle ValueObject properties
            if ($this->isValueObjectProperty($property, $typeOfField)) {
                $value = $this->convertToValueObject($value, $classMetadata, $fieldName, $property);
            } else {
                // Convert standard types
                $value = $this->applyBooleanMapIfNeeded($classMetadata, $fieldName, $typeOfField, $value);
                $value = $this->convertStandardType($value, $propertyType, $typeOfField, $fieldName);
            }

            $property->setValue($entity, $value);
        }
    }

    public function fillEntity(
        object $entity,
        ClassMetadata $classMetadata,
        array $row,
        bool $withoutIdentifier = true
    ): void {
        $reflectionClass = $classMetadata->getReflectionClass();
        $fieldNames = $classMetadata->getFieldNames();

        foreach ($fieldNames as $fieldName) {
            if ($withoutIdentifier && $classMetadata->isIdentifier($fieldName)) {
                continue;
            }

            $setNull = false;
            if (!$reflectionClass->hasProperty($fieldName)) {
                throw new InvalidArgumentException(
                    sprintf('Property "%s" does not exist in class "%s".', $fieldName, $reflectionClass->getName())
                );
            }

            $property = $reflectionClass->getProperty($fieldName);
            $propertyType = $property->getType();

            if ($classMetadata->hasAssociation($fieldName)) {
                $this->prepareAssociation($entity, $classMetadata, $fieldName, $property);
                continue;
            }

            if (!key_exists($column = $classMetadata->getColumnOfField($fieldName), $row)) {
                if (!$classMetadata->isFieldNullable($fieldName)) {
                    throw new InvalidArgumentException(sprintf('Row key "%s" does not exist', $column));
                }
                $setNull = true;
            }

            if (!$typeOfField = $classMetadata->getTypeOfField($fieldName)) {
                throw new InvalidArgumentException('Type of field "' . $fieldName . '" does not exist.');
            }

            $value = $setNull ? null : $row[$classMetadata->getColumnOfField($fieldName)];

            // Handle ValueObject properties
            if (!$setNull && $this->isValueObjectProperty($property, $typeOfField)) {
                $value = $this->convertToValueObject($value, $classMetadata, $fieldName, $property);
            } elseif (!$setNull) {
                // Convert standard types
                $value = $this->applyBooleanMapIfNeeded($classMetadata, $fieldName, $typeOfField, $value);
                $value = $this->convertStandardType($value, $propertyType, $typeOfField, $fieldName);
            }

            $property->setValue($entity, $value);
        }
    }

    public function getEntityDataRow(object $entity, ClassMetadata $classMetadata): array
    {
        $row = [];
        $reflectionClass = $classMetadata->getReflectionClass();
        $fieldNames = $classMetadata->getFieldNames();
        $assocNames = $classMetadata->getAssociationNames();

        // Handle associations (same as parent)
        foreach ($assocNames as $fieldName) {
            if (!$reflectionClass->hasProperty($fieldName)) {
                throw new InvalidArgumentException(
                    sprintf('Property "%s" does not exist in class "%s".', $fieldName, $reflectionClass->getName())
                );
            }
            $property = $reflectionClass->getProperty($fieldName);
            if (
                $classMetadata->isSingleValuedAssociation($fieldName)
                && !$classMetadata->isAssociationInverseSide($fieldName)
                && is_object($related = $property->getValue($entity))
            ) {
                if ($related instanceof ProxyInterface) {
                    $related = $related->__getOriginal();
                }
                $referencedColumnName = $classMetadata->getReferencedColumnName($fieldName);
                $joinColumnName = $classMetadata->getJoinColumnName($fieldName);
                $referencedMetadata = $this->entityManager->getClassMetadata(get_class($related));
                if ($referencedMetadata->isIdentifier($referencedColumnName)) {
                    $referencedIdentifier = $referencedMetadata->getIdentifierValues($related);
                    if (!$reflectionClass->hasProperty($joinColumnName)) {
                        throw new InvalidArgumentException(
                            'Join column property "' . $joinColumnName . '" does not exist.'
                        );
                    }
                    $joinColumnProperty = $reflectionClass->getProperty($joinColumnName);
                    $joinColumnProperty->setValue($entity, $referencedIdentifier[$referencedColumnName]);
                }
            }
        }

        // Handle regular fields with ValueObject support
        foreach ($fieldNames as $fieldName) {
            if (in_array($fieldName, $assocNames)) {
                continue;
            }
            if (!$reflectionClass->hasProperty($fieldName)) {
                throw new InvalidArgumentException(
                    sprintf('Property "%s" does not exist in class "%s".', $fieldName, $reflectionClass->getName())
                );
            }
            $property = $reflectionClass->getProperty($fieldName);
            if ($property->isInitialized($entity)) {
                $value = $property->getValue($entity);

                $typeOfField = $classMetadata->getTypeOfField($fieldName) ?? '';
                if ($value !== null && is_object($value)
                    && ($value instanceof ValueObjectInterface || FieldTypeEnum::isValueObject($typeOfField))
                ) {
                    $value = $this->convertValueObjectToStorage(
                        $value,
                        $classMetadata,
                        $fieldName,
                        $property,
                        $typeOfField
                    );
                }

                // Map boolean PHP value to source representation if configured
                if (FieldTypeEnum::normalizeType($typeOfField) === FieldTypeEnum::Boolean->value) {
                    $value = $this->mapBooleanValueToSource($classMetadata, $fieldName, $value);
                }

                $row[$classMetadata->getColumnOfField($fieldName)] = $value;
            }
        }

        return $row;
    }

    /**
     * Convert a domain / metadata-mapped value object to a storage scalar (criteria, adapters).
     */
    public function convertFieldValueToStorage(
        object $value,
        ClassMetadata $classMetadata,
        string $fieldName
    ): mixed {
        $reflectionClass = $classMetadata->getReflectionClass();
        if (!$reflectionClass->hasProperty($fieldName)) {
            throw new InvalidArgumentException(
                sprintf('Property "%s" does not exist in class "%s".', $fieldName, $reflectionClass->getName())
            );
        }
        $property = $reflectionClass->getProperty($fieldName);
        $typeOfField = $classMetadata->getTypeOfField($fieldName) ?? '';

        return $this->convertValueObjectToStorage(
            $value,
            $classMetadata,
            $fieldName,
            $property,
            $typeOfField
        );
    }

    /**
     * Check if a property should be treated as a ValueObject.
     */
    private function isValueObjectProperty(ReflectionProperty $property, string $typeOfField): bool
    {
        if (FieldTypeEnum::isValueObject($typeOfField)) {
            return true;
        }

        // Check if property type implements ValueObjectInterface
        $propertyType = $property->getType();
        if ($propertyType instanceof ReflectionNamedType && !$propertyType->isBuiltin()) {
            $className = $propertyType->getName();

            return $this->valueObjectRegistry->supports($className);
        }

        return false;
    }

    /**
     * Convert database value to a value object instance (metadata `from` or registry).
     *
     * @return ($value is null ? null : object)
     */
    private function convertToValueObject(
        mixed $value,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property
    ): ?object {
        if ($value === null) {
            return null;
        }

        $from = $classMetadata->getFieldOption($fieldName, 'from');
        if ($from !== null && $from !== '') {
            try {
                $namedType = $this->getReflectionNamedType($property, $fieldName);
                $propertyTypeName = $namedType->getName();
                $targetClass = $this->resolveMetadataTargetClass($classMetadata, $fieldName, $propertyTypeName);

                return $this->invokeStaticValueObjectFactory($value, $targetClass, (string) $from, $fieldName);
            } catch (\Exception $e) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Failed to convert value for field "%s" to mapped value object "%s": %s',
                        $fieldName,
                        $this->valueObjectPropertyTypeHint($property, $fieldName),
                        $e->getMessage()
                    ),
                    0,
                    $e
                );
            }
        }

        try {
            $namedType = $this->getReflectionNamedType($property, $fieldName);
            $propertyTypeName = $namedType->getName();

            return $this->valueObjectRegistry->convertToPHP($value, $propertyTypeName);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                sprintf(
                    'Failed to convert value for field "%s" to value object "%s": %s',
                    $fieldName,
                    $this->valueObjectPropertyTypeHint($property, $fieldName),
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    private function valueObjectPropertyTypeHint(ReflectionProperty $property, string $fieldName): string
    {
        $type = $property->getType();
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return sprintf('(see property "%s")', $fieldName);
    }

    private function getReflectionNamedType(ReflectionProperty $property, string $fieldName): ReflectionNamedType
    {
        $type = $property->getType();
        if ($type === null) {
            throw new InvalidArgumentException(
                sprintf('Property "%s" must have a type for value object mapping.', $fieldName)
            );
        }
        if ($type instanceof ReflectionUnionType) {
            throw new InvalidArgumentException(
                sprintf(
                    'Union types on property "%s" are not supported for value object mapping in this version.',
                    $fieldName
                )
            );
        }
        if ($type instanceof ReflectionIntersectionType) {
            throw new InvalidArgumentException(
                sprintf(
                    'Intersection types on property "%s" are not supported for value object mapping in this version.',
                    $fieldName
                )
            );
        }
        if (!$type instanceof ReflectionNamedType) {
            throw new InvalidArgumentException(
                sprintf('Unsupported reflection type on property "%s" for value object mapping.', $fieldName)
            );
        }

        return $type;
    }

    /**
     * @phpstan-param class-string $propertyTypeName
     *
     * @return class-string
     */
    private function resolveMetadataTargetClass(
        ClassMetadata $classMetadata,
        string $fieldName,
        string $propertyTypeName
    ): string {
        $classOpt = $classMetadata->getFieldOption($fieldName, 'class');
        $vocOpt = $classMetadata->getFieldOption($fieldName, 'valueObjectClass');

        $classOpt = is_string($classOpt) && $classOpt !== '' ? $classOpt : null;
        $vocOpt = is_string($vocOpt) && $vocOpt !== '' ? $vocOpt : null;

        if ($classOpt !== null && $vocOpt !== null && $classOpt !== $vocOpt) {
            throw new InvalidArgumentException(
                sprintf(
                    'Field "%s": metadata "class" (%s) and "valueObjectClass" (%s) conflict when both are set.',
                    $fieldName,
                    $classOpt,
                    $vocOpt
                )
            );
        }

        $metaClass = $classOpt ?? $vocOpt;

        if ($metaClass !== null && $metaClass !== $propertyTypeName) {
            throw new InvalidArgumentException(
                sprintf(
                    'Metadata value object class "%s" does not match property type "%s" for field "%s".',
                    $metaClass,
                    $propertyTypeName,
                    $fieldName
                )
            );
        }

        return $propertyTypeName;
    }

    /**
     * @phpstan-param class-string $targetClass
     */
    private function invokeStaticValueObjectFactory(mixed $raw, string $targetClass, string $from, string $fieldName): object
    {
        if (!class_exists($targetClass)) {
            throw new InvalidArgumentException(sprintf('Value object class "%s" does not exist.', $targetClass));
        }

        if (!method_exists($targetClass, $from)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Static factory "%s::%s" for field "%s" does not exist.',
                    $targetClass,
                    $from,
                    $fieldName
                )
            );
        }

        $method = new ReflectionMethod($targetClass, $from);
        if (!$method->isStatic()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Value object factory "%s::%s" for field "%s" must be static.',
                    $targetClass,
                    $from,
                    $fieldName
                )
            );
        }
        if ($method->getNumberOfRequiredParameters() > 1) {
            throw new InvalidArgumentException(
                sprintf(
                    'Static factory "%s::%s" for field "%s" must require at most one parameter.',
                    $targetClass,
                    $from,
                    $fieldName
                )
            );
        }

        $result = $method->invoke(null, $raw);
        if (!is_object($result)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Static factory "%s::%s" for field "%s" must return an object instance of %s.',
                    $targetClass,
                    $from,
                    $fieldName,
                    $targetClass
                )
            );
        }
        if (!$result instanceof $targetClass) {
            throw new InvalidArgumentException(
                sprintf(
                    'Static factory "%s::%s" for field "%s" must return %s, got %s.',
                    $targetClass,
                    $from,
                    $fieldName,
                    $targetClass,
                    $result::class
                )
            );
        }

        return $result;
    }

    private function convertValueObjectToStorage(
        object $value,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property,
        string $typeOfField
    ): mixed {
        if ($value instanceof ValueObjectInterface) {
            return $this->valueObjectRegistry->convertToDatabase($value);
        }

        $namedType = $this->getReflectionNamedType($property, $fieldName);
        $targetClass = $this->resolveMetadataTargetClass(
            $classMetadata,
            $fieldName,
            $namedType->getName()
        );

        if (!is_a($value, $targetClass, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Value for field "%s" must be an instance of %s for storage conversion, got %s.',
                    $fieldName,
                    $targetClass,
                    $value::class
                )
            );
        }

        $to = $classMetadata->getFieldOption($fieldName, 'to');
        if ($to !== null && $to !== '') {
            return $this->invokeValueObjectToStorageMethod($value, (string) $to, $fieldName);
        }

        if (FieldTypeEnum::isValueObject($typeOfField) && $value instanceof \Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(
            sprintf(
                'Cannot convert value object for field "%s" to storage: set metadata option "to", implement %s, or use a Stringable value object class.',
                $fieldName,
                ValueObjectInterface::class
            )
        );
    }

    private function invokeValueObjectToStorageMethod(object $value, string $method, string $fieldName): mixed
    {
        if (!method_exists($value, $method)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Instance method "%s::%s" for field "%s" does not exist.',
                    $value::class,
                    $method,
                    $fieldName
                )
            );
        }
        $ref = new ReflectionMethod($value, $method);
        if ($ref->isStatic()) {
            throw new InvalidArgumentException(
                sprintf(
                    'Storage extractor "%s::%s" for field "%s" must not be static.',
                    $value::class,
                    $method,
                    $fieldName
                )
            );
        }
        if ($ref->getNumberOfRequiredParameters() > 0) {
            throw new InvalidArgumentException(
                sprintf(
                    'Storage extractor "%s::%s" for field "%s" must not require parameters.',
                    $value::class,
                    $method,
                    $fieldName
                )
            );
        }

        return $ref->invoke($value);
    }

    /**
     * Convert database value to standard PHP type.
     */
    private function convertStandardType(
        mixed $value,
        ?\ReflectionType $propertyType,
        string $typeOfField,
        string $fieldName
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (!$propertyType) {
            return $value;
        }

        // Get the property type name
        $propertyTypeName = $propertyType->getName();

        // Validate type compatibility
        if (FieldTypeEnum::normalizeType(strtolower($propertyTypeName)) !== FieldTypeEnum::normalizeType(
                $typeOfField
            )) {
            throw new InvalidArgumentException(
                'Type of property "' . $fieldName . '" does not match type of field "' . $typeOfField . '"'
            );
        }

        // Convert datetime types
        if (in_array(strtolower($propertyTypeName), ['datetime', 'datetimeimmutable', 'datetimeinterface'])) {
            if (is_string($value)) {
                try {
                    if ($propertyTypeName === 'DateTimeImmutable') {
                        return new \DateTimeImmutable($value);
                    } elseif ($propertyTypeName === 'DateTime') {
                        return new \DateTime($value);
                    }
                } catch (\Exception $e) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Failed to convert value "%s" to %s for field "%s": %s',
                            $value,
                            $propertyTypeName,
                            $fieldName,
                            $e->getMessage()
                        ),
                        0,
                        $e
                    );
                }
            }
        }

        return $value;
    }

    private function applyBooleanMapIfNeeded(ClassMetadata $classMetadata, string $fieldName, string $typeOfField, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (FieldTypeEnum::normalizeType($typeOfField) !== FieldTypeEnum::Boolean->value) {
            return $value;
        }

        // Read mapping from 'values' key only
        $map = $classMetadata->getFieldOption($fieldName, 'values');
        if (!is_array($map) || $map === []) {
            return $value;
        }

        $key = is_string($value) ? $value : (is_int($value) || is_float($value) ? (string)$value : $value);
        if (!is_string($key)) {
            return $value;
        }

        // exact match first
        if (array_key_exists($key, $map)) {
            return (bool)$map[$key];
        }
        // case-insensitive fallback for string keys
        foreach ($map as $k => $v) {
            if (is_string($k) && strcasecmp($k, $key) === 0) {
                return (bool)$v;
            }
        }

        return $value;
    }

    private function mapBooleanValueToSource(ClassMetadata $classMetadata, string $fieldName, mixed $value): mixed
    {
        if (!is_bool($value)) {
            return $value;
        }
        $map = $classMetadata->getFieldOption($fieldName, 'values');
        if (!is_array($map) || $map === []) {
            return $value;
        }
        $mapTrue = null;
        $mapFalse = null;
        foreach ($map as $k => $v) {
            if ((bool)$v === true && $mapTrue === null) {
                $mapTrue = $k;
            }
            if ((bool)$v === false && $mapFalse === null) {
                $mapFalse = $k;
            }
        }
        return $value ? ($mapTrue ?? $value) : ($mapFalse ?? $value);
    }

    /**
     * Prepare association for entity property.
     * Copied from parent class since the original method is private.
     */
    private function prepareAssociation(
        object $entity,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property
    ): void {
        if ($classMetadata->isSingleValuedAssociation($fieldName)) {
            if ($property->isInitialized($entity)) {
                $propertyValue = $property->getValue($entity);
                if (is_object($propertyValue)) {
                    return;
                }
            }
            $this->prepareSingleValuedAssociation($entity, $classMetadata, $fieldName, $property);
        } elseif ($classMetadata->isCollectionValuedAssociation($fieldName)) {
            if ($property->isInitialized($entity)) {
                $propertyValue = $property->getValue($entity);
                if (is_object($propertyValue) && method_exists($propertyValue, '__isInitialized') && $propertyValue->__isInitialized()) {
                    return;
                }
            }
            // For collection associations, we'll skip for now
            // This would need full implementation of collection handling
        }
    }

    /**
     * Prepare single-valued association.
     */
    private function prepareSingleValuedAssociation(
        object $entity,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property
    ): void {
        // For now, we'll implement basic association handling
        // Full implementation would require proxy creation and lazy loading
        
        if ($classMetadata->isAssociationInverseSide($fieldName)) {
            // Inverse side - typically handled by the owning side
            return;
        }
        
        // Owning side - would need to load related entity
        // For now, we'll skip this to avoid complexity
        return;
    }
}
