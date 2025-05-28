<?php

namespace Kabiroman\AEM;

interface ClassMetadata extends \Doctrine\Persistence\Mapping\ClassMetadata
{
    /**
     * @return string The fully qualified name of the entity data adapter class.
     */
    public function getEntityDataAdapterClass(): string;

    /**
     * @return string|null The fully qualified name of the entity repository class.
     */
    public function getSpecifiedRepositoryName(): string|null;

    /**
     * @return string The column name of this field.
     */
    public function getColumnOfField(string $fieldName): string;

    /**
     * @return bool The field is nullable.
     */
    public function isFieldNullable(string $fieldName): bool;

    public function getJoinColumnName(string $assocName): string|null;

    public function getReferencedColumnName(string $assocName): string|null;

    public function getAssociationFetchMode(string $assocName): string|null;

    public function hasLifecycleCallbacks(string $event): bool;

    public function getLifecycleCallbacks(string $event): array;
}
