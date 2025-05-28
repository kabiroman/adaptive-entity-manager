<?php

namespace Kabiroman\AEM;

interface DataAdapterFactoryInterface
{
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter;
}
