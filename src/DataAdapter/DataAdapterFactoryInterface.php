<?php

namespace Kabiroman\AEM\DataAdapter;

use Kabiroman\AEM\ClassMetadata;

interface DataAdapterFactoryInterface
{
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter;
}
