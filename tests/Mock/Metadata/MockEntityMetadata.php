<?php

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;

class MockEntityMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        MockEntity::class => [
            'repositoryClass' => MockEntityRepository::class,
            'dataAdapterClass' => MockEntityDataAdapter::class,
            'id' => [
                'id' => [
                    'type' => 'int',
                ],
            ],
            'fields' => [
                'active' => [
                    'type' => 'bool'
                ],
                'name' => [
                    'type' => 'string'
                ],
                'createdAt' => [
                    'type' => 'datetime'
                ],
                'price' => [
                    'type' => 'float'
                ],
                'nullable' => [
                    'type' => 'string'
                ],
            ],
//            'hasOne' => [],
//            'hasMany' => [],
//            'oneToOne' => [],
//            'oneToMany' => [],
//            'manyToMany' => [],
        ]
    ];
}
