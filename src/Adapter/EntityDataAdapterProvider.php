<?php

namespace Kabiroman\AEM\Adapter;

use Kabiroman\AEM\ClassMetadata;

interface EntityDataAdapterProvider
{
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter;
}
