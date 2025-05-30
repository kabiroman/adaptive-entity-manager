<?php

namespace Kabiroman\AEM;

use InvalidArgumentException;
use Kabiroman\AEM\Constant\FetchModeEnum;
use Kabiroman\AEM\Constant\FieldTypeEnum;
use Kabiroman\AEM\EntityProxy\EntityProxyFactory;
use ReflectionProperty;

class EntityFactory
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntityProxyFactory $entityProxyFactory
    ) {
    }

    public function makeEntity(ClassMetadata $classMetadata, array $row): object
    {
        $this->setIdentifierValue($entity = new ($classMetadata->getName())(), $classMetadata, $row);

        return $entity;
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
            if (strtolower($propertyType->getName()) !== FieldTypeEnum::normalizeType($typeOfField)) {
                throw new InvalidArgumentException(
                    'Type of property "' . $fieldName . '" does not match type of field "' . $typeOfField . '"'
                );
            }
            $property->setValue($entity, $row[$classMetadata->getColumnOfField($fieldName)]);
        }
    }

    public function fillEntity(object $entity, ClassMetadata $classMetadata, array $row, bool $withoutIdentifier = true): void
    {
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
            if (strtolower($propertyType->getName()) !== FieldTypeEnum::normalizeType($typeOfField)) {
                throw new InvalidArgumentException(
                    'Type of property "' . $fieldName . '" does not match type of field "' . $typeOfField . '"'
                );
            }
            $property->setValue($entity, $setNull ? null : $row[$classMetadata->getColumnOfField($fieldName)]);
        }
    }

    public function getEntityDataRow(object $entity, ClassMetadata $classMetadata): array
    {
        $row = [];
        $reflectionClass = $classMetadata->getReflectionClass();
        $fieldNames = $classMetadata->getFieldNames();
        $assocNames = $classMetadata->getAssociationNames();

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
                        throw new InvalidArgumentException('Join column property "' . $joinColumnName . '" does not exist.');
                    }
                    $joinColumnProperty = $reflectionClass->getProperty($joinColumnName);
                    $joinColumnProperty->setValue($entity, $referencedIdentifier[$referencedColumnName]);
                }
            }
        }

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
                $row[$classMetadata->getColumnOfField($fieldName)] = $property->getValue($entity);
            }
        }

        return $row;
    }

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
                if ($propertyValue instanceof PersistentCollection && $propertyValue->__isInitialized()) {
                    return;
                }
            }
            $this->prepareInverseSideAssociation($entity, $classMetadata, $fieldName, $property, true);
        }
    }

    private function prepareSingleValuedAssociation(
        object $entity,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property
    ): void {
        if ($classMetadata->isAssociationInverseSide($fieldName)) {
            $this->prepareInverseSideAssociation($entity, $classMetadata, $fieldName, $property);
        } else {
            $this->prepareDirectSideAssociation($entity, $classMetadata, $fieldName, $property);
        }
    }

    private function prepareDirectSideAssociation(
        object $entity,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property
    ): void {
        $targetClass = $classMetadata->getAssociationTargetClass($fieldName);
        $joinColumnName = $classMetadata->getJoinColumnName($fieldName);
        $referencedColumnName = $classMetadata->getReferencedColumnName($fieldName);
        $reflectionClass = $classMetadata->getReflectionClass();
        if (!$reflectionClass->hasProperty($joinColumnName)) {
            throw new InvalidArgumentException(
                'Property "' . $joinColumnName . '" does not exist.'
            );
        }
        $joinColumnProperty = $reflectionClass->getProperty($joinColumnName);
        $criteria = [$referencedColumnName => $joinColumnProperty->getValue($entity)];

        if ($fetch = $classMetadata->getAssociationFetchMode($fieldName) and $fetch === FetchModeEnum::EAGER->value) {
            $relatedEntity = $this->entityManager->getRepository($targetClass)->findOneBy($criteria);
        } else {
            $relatedEntity = $this->entityProxyFactory->createProxy($targetClass, $criteria);
        }

        $property->setValue($entity, $relatedEntity);
    }

    private function prepareInverseSideAssociation(
        object $entity,
        ClassMetadata $classMetadata,
        string $fieldName,
        ReflectionProperty $property,
        bool $is_collection = false
    ): void {
        $mappedByTargetField = $classMetadata->getAssociationMappedByTargetField($fieldName);
        $targetClass = $classMetadata->getAssociationTargetClass($fieldName);
        $targetClassMetadata = $this->entityManager->getClassMetadata($targetClass);

        if (!$targetClassMetadata->isSingleValuedAssociation($mappedByTargetField)) {
            throw new InvalidArgumentException(
                'Mapped target field "' . $mappedByTargetField . '" does not a single valued association.'
            );
        }
        if ($targetClassMetadata->isAssociationInverseSide($mappedByTargetField)) {
            throw new InvalidArgumentException(
                'Mapped target field "' . $mappedByTargetField . '" can`t be an inverse side.'
            );
        }

        if (!$mappedByTargetJoinColumnName = $targetClassMetadata->getJoinColumnName($mappedByTargetField)) {
            throw new InvalidArgumentException('Target join column "' . $mappedByTargetField . '" does not exist.');
        }
        if (!$mappedByTargetReferencedColumnName = $targetClassMetadata->getReferencedColumnName(
            $mappedByTargetField
        )) {
            throw new InvalidArgumentException(
                'Target reference column "' . $mappedByTargetField . '" does not exist.'
            );
        }
        $reflectionClass = $classMetadata->getReflectionClass();
        if (!$reflectionClass->hasProperty($mappedByTargetReferencedColumnName)) {
            throw new InvalidArgumentException(
                'Property "' . $mappedByTargetReferencedColumnName . '" does not exist.'
            );
        }
        $referenceProperty = $reflectionClass->getProperty($mappedByTargetReferencedColumnName);

        $criteria = [$mappedByTargetJoinColumnName => $referenceProperty->getValue($entity)];
        $repository = $this->entityManager->getRepository($targetClass);

        $callback = function () use ($repository, $criteria, $is_collection) {
            return $is_collection ? $repository->findBy($criteria) : $repository->findOneBy($criteria);
        };

        $fetch = $classMetadata->getAssociationFetchMode($fieldName);
        if (!$is_collection) {
            if ($fetch === FetchModeEnum::EAGER->value) {
                $related = $this->entityManager->getRepository($targetClass)->findOneBy($criteria);
            } else {
                $related = $this->entityProxyFactory->createProxy($targetClass, $criteria);
            }
        } else {
            $related = new PersistentCollection($callback);
            if ($fetch === FetchModeEnum::EAGER->value) {
                $related->initialize();
            }
        }
        $property->setValue($entity, $related);
    }
}
