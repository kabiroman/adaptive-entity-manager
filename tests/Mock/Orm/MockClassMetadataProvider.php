<?php

namespace Kabiroman\AEM\Tests\Mock\Orm;

use Kabiroman\AEM\ClassMetadata;
use Kabiroman\AEM\Metadata\ClassMetadataProvider;
use RuntimeException;

class MockClassMetadataProvider implements ClassMetadataProvider
{

    public function getClassMetadata(string $entityName): ?ClassMetadata
    {
        if (!class_exists($class = str_replace('\\Entity\\', '\\Metadata\\', $entityName.'Metadata'))) {
            throw new RuntimeException('Class metadata not found');
        }

        return new $class;
    }
}
