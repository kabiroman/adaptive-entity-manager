<?php

namespace Kabiroman\AEM\Metadata;

use Kabiroman\AEM\ClassMetadata;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

abstract class AbstractClassMetadata implements ClassMetadata
{
    protected array $metadata;

    public function getName(): string
    {
        foreach ($this->metadata as $class => $value) {
            return $class;
        }
        throw new RuntimeException('The fully-qualified class name of this persistent class is not specified!');
    }

    public function isIdentifier(string $fieldName): bool
    {
        return in_array($fieldName, $this->getIdentifier(), true);
    }

    public function getEntityDataAdapterClass(): string
    {
        return $this->metadata[$this->getName()]['dataAdapterClass']
            ?? throw new RuntimeException('The fully-qualified class name of data adapter class is not specified!');
    }

    public function getSpecifiedRepositoryName(): string|null
    {
        return $this->metadata[$this->getName()]['repositoryClass'] ?? null;
    }

    public function getIdentifier(): array
    {
        $identifier = [];
        foreach ($this->getIdentifierParams() as $name => $value) {
            $identifier[] = $name;
        }

        return $identifier;
    }

    /**
     * @throws ReflectionException
     */
    public function getReflectionClass(): ReflectionClass
    {
        return new ReflectionClass($this->getName());
    }

    public function hasField(string $fieldName): bool
    {
        return in_array($fieldName, $this->getFieldNames());
    }

    public function getFieldNames(): array
    {
        $fieldNames = [];
        foreach ($this->getFieldsParams() as $fieldName => $field) {
            $fieldNames[] = $fieldName;
        }
        $identifierFieldNames = $this->getIdentifierFieldNames();
        $associationNames = $this->getAssociationNames();

        return array_merge($identifierFieldNames, $fieldNames, $associationNames);
    }

    public function getIdentifierFieldNames(): array
    {
        return array_values($this->getIdentifier());
    }

    public function getTypeOfField(string $fieldName): string|null
    {
        $params = array_merge($this->getIdentifierParams(), $this->getFieldsParams());

        return $params[$fieldName]['type'] ?? null;
    }

    public function getColumnOfField(string $fieldName): string
    {
        $params = array_merge($this->getIdentifierParams(), $this->getFieldsParams());

        return $params[$fieldName]['column'] ?? $fieldName;
    }

    public function isFieldNullable(string $fieldName): bool
    {
        $params = array_merge($this->getIdentifierParams(), $this->getFieldsParams(), $this->getAssociationNames());
        if (isset($params[$fieldName]['nullable']) && $params[$fieldName]['nullable'] === true) {
            return true;
        }

        return false;
    }

    /**
     * @throws ReflectionException
     */
    public function getIdentifierValues(object $object): array
    {
        $identifier = [];
        $identifierNames = $this->getIdentifier();
        $reflectionClass = $this->getReflectionClass();

        foreach ($identifierNames as $identifierName) {
            if (!$reflectionClass->hasProperty($identifierName)) {
                throw new RuntimeException('Identifier "' . $identifierName . '" does not exist.', 0);
            }
            $identifier[$identifierName] = $reflectionClass->getProperty($identifierName)->getValue($object);
        }
        if (empty($identifier)) {
            throw new RuntimeException('Identifier for "' . $this->getName() . '" does not exist.', 0);
        }

        return $identifier;
    }

    public function hasAssociation(string $fieldName): bool
    {
        return $this->isSingleValuedAssociation($fieldName)
            || $this->isCollectionValuedAssociation($fieldName);
    }

    public function isSingleValuedAssociation(string $fieldName): bool
    {
        return isset($this->metadata[$this->getName()]['hasOne'][$fieldName]);
    }

    public function isCollectionValuedAssociation(string $fieldName): bool
    {
        return isset($this->metadata[$this->getName()]['hasMany'][$fieldName]);
    }

    public function getAssociationNames(): array
    {
        $result = [];
        foreach (($this->metadata[$this->getName()]['hasOne'] ?? []) as $fieldName => $field) {
            $result[] = $fieldName;
        }
        foreach (($this->metadata[$this->getName()]['hasMany'] ?? []) as $fieldName => $field) {
            $result[] = $fieldName;
        }

        return $result;
    }

    public function getAssociationTargetClass(string $assocName): string|null
    {
        foreach (['hasOne', 'hasMany'] as $assocType) {
            if ($targetEntity = $this->metadata[$this->getName()][$assocType][$assocName]['targetEntity'] ?? null) {
                return $targetEntity;
            }
        }

        return null;
    }

    public function isAssociationInverseSide(string $assocName): bool
    {
        foreach (['hasOne', 'hasMany'] as $assocType) {
            $mappedBy = $this->metadata[$this->getName()][$assocType][$assocName]['mappedBy'] ?? null;
        }

        return $mappedBy !== null;
    }

    public function getAssociationMappedByTargetField(string $assocName): string
    {
        foreach (['hasOne', 'hasMany'] as $assocType) {
            $mappedBy = $this->metadata[$this->getName()][$assocType][$assocName]['mappedBy'] ?? null;
        }

        return $mappedBy ?? throw new RuntimeException(
            'Association "' . $assocName . '" [mappedBy] param does not exist.'
        );
    }

    private function getIdentifierParams(): array
    {
        return $this->metadata[$this->getName()]['id'] ??
            throw new RuntimeException('The identifier parameter is not specified!');
    }

    private function getFieldsParams(): array
    {
        return $this->metadata[$this->getName()]['fields'] ??
            throw new RuntimeException('Field list parameter not specified!');
    }

    private function getAssociationParams(): array
    {
        $hasOneParams = $this->metadata[$this->getName()]['hasOne'] ?? [];
        $hasManyParams = $this->metadata[$this->getName()]['hasMany'] ?? [];

        return array_merge($hasOneParams, $hasManyParams);
    }

    public function getJoinColumnName(string $assocName): ?string
    {
        $params = $this->getAssociationParams();

        return $params[$assocName]['joinColumn']['name'] ?? null;
    }

    public function getReferencedColumnName(string $assocName): ?string
    {
        $params = $this->getAssociationParams();

        return $params[$assocName]['joinColumn']['referencedColumnName'] ?? null;
    }

    public function getAssociationFetchMode(string $assocName): string|null
    {
        $params = $this->getAssociationParams();

        return $params[$assocName]['fetch'] ?? null;
    }

    public function hasLifecycleCallbacks(string $event): bool
    {
        return isset($this->metadata[$this->getName()]['lifecycleCallbacks'][$event]);
    }

    public function getLifecycleCallbacks(string $event): array
    {
        return $this->metadata[$this->getName()]['lifecycleCallbacks'][$event];
    }

    public function getFieldOption(string $fieldName, string $optionKey): mixed
    {
        $params = array_merge($this->getIdentifierParams(), $this->getFieldsParams());
        if (!isset($params[$fieldName])) {
            return null;
        }

        return $params[$fieldName][$optionKey] ?? null;
    }
}
