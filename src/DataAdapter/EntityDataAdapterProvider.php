<?php

namespace Kabiroman\AEM\DataAdapter;

use Kabiroman\AEM\ClassMetadata;

interface EntityDataAdapterProvider
{
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter;
}
