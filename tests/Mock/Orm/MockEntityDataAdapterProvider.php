<?php

namespace Kabiroman\AEM\Tests\Mock\Orm;


use Kabiroman\AEM\ClassMetadata;
use Kabiroman\AEM\DataAdapter\EntityDataAdapter;
use Kabiroman\AEM\DataAdapter\EntityDataAdapterProvider;

class MockEntityDataAdapterProvider implements EntityDataAdapterProvider
{
    private EntityDataAdapter $adapter;

    public function __construct(EntityDataAdapter $adapter = null)
    {
        $this->adapter = $adapter ?? new MockEntityDataAdapter();
    }

    public function getAdapter(ClassMetadata $metadata): EntityDataAdapter
    {
        return $this->adapter;
    }
}
