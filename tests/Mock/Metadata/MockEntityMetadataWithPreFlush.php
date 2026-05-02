<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\PreFlushTracker;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;

/**
 * Same as MockEntityMetadata with a single preFlush lifecycle callback for tests.
 *
 * @internal
 */
class MockEntityMetadataWithPreFlush extends AbstractClassMetadata
{
    protected array $metadata = [
        MockEntity::class => [
            'repositoryClass' => MockEntityRepository::class,
            'dataAdapterClass' => MockEntityDataAdapter::class,
            'lifecycleCallbacks' => [
                'preFlush' => [
                    [PreFlushTracker::class, 'touch'],
                ],
            ],
            'id' => [
                'id' => [
                    'type' => 'int',
                ],
            ],
            'fields' => [
                'active' => [
                    'type' => 'bool',
                ],
                'name' => [
                    'type' => 'string',
                ],
                'createdAt' => [
                    'type' => 'datetime',
                ],
                'price' => [
                    'type' => 'float',
                ],
                'nullable' => [
                    'type' => 'string',
                ],
            ],
        ],
    ];
}
