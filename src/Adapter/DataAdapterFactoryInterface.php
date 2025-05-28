<?php

namespace Kabiroman\AEM\Adapter;

use Kabiroman\AEM\ClassMetadata;

interface DataAdapterFactoryInterface
{
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter;
}
