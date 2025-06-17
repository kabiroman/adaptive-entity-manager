<?php

namespace Kabiroman\AEM;

use InvalidArgumentException;
use Kabiroman\AEM\Constant\FetchModeEnum;
use Kabiroman\AEM\Constant\FieldTypeEnum;
use Kabiroman\AEM\EntityProxy\EntityProxyFactory;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;
use Kabiroman\AEM\ValueObject\ValueObjectInterface;
use ReflectionProperty;
use ReflectionClass;

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
                $value = $this->convertToValueObject($value, $propertyType->getName(), $fieldName);
            } else {
                // Convert standard types
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
                $value = $this->convertToValueObject($value, $propertyType->getName(), $fieldName);
            } elseif (!$setNull) {
                // Convert standard types
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

                // Convert ValueObject to database value
                if ($value instanceof ValueObjectInterface) {
                    $value = $this->valueObjectRegistry->convertToDatabase($value);
                }

                $row[$classMetadata->getColumnOfField($fieldName)] = $value;
            }
        }

        return $row;
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
        if ($propertyType && !$propertyType->isBuiltin()) {
            $className = $propertyType->getName();
            return $this->valueObjectRegistry->supports($className);
        }

        return false;
    }

    /**
     * Convert database value to ValueObject.
     */
    private function convertToValueObject(mixed $value, string $className, string $fieldName): ?ValueObjectInterface
    {
        if ($value === null) {
            return null;
        }

        try {
            return $this->valueObjectRegistry->convertToPHP($value, $className);
        } catch (\Exception $e) {
            throw new InvalidArgumentException(
                sprintf(
                    'Failed to convert value for field "%s" to ValueObject "%s": %s',
                    $fieldName,
                    $className,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
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
