<?php

namespace Kabiroman\AEM\Metadata;

use Kabiroman\AEM\ClassMetadata;

class DefaultEntityMetadataProvider implements ClassMetadataProvider
{

    public function getClassMetadata(string $entityName): ?ClassMetadata
    {
        if (class_exists($class = $this->getMetadataClassName($entityName))) {
            return new $class();
        }

        return null;
    }

    private function getMetadataClassName(string $entityName): string
    {
        return str_replace('\\Entity\\', '\\Metadata\\', $entityName) . 'Metadata';
    }
}
