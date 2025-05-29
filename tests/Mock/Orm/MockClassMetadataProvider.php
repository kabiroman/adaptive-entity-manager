<?php

namespace Kabiroman\AEM\Tests\Mock\Orm;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\ClassMetadata;
use Kabiroman\AEM\Metadata\ClassMetadataProvider;
use RuntimeException;

class MockClassMetadataProvider implements ClassMetadataProvider
{

    public function getClassMetadata(string $entityName): ?ClassMetadata
    {
        if ($entityName !== MockEntity::class) {
            throw new RuntimeException('Class metadata not found');
        }

        return new MockEntityMetadata();
    }
}
