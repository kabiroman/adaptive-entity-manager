<?php

namespace Kabiroman\AEM\Metadata;

use Kabiroman\AEM\ClassMetadata;

interface ClassMetadataProvider
{
    public function getClassMetadata(string $entityName): ?ClassMetadata;
}
