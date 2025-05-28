<?php

namespace Kabiroman\AEM;

interface EntityDataAdapterProvider
{
    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter;
}
