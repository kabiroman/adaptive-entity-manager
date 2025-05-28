<?php

namespace Kabiroman\AEM;

interface ClassMetadataProvider
{
    public function getClassMetadata(string $entityName): ?ClassMetadata;
}
