<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\AliasFieldEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;

/**
 * Metadata with a string field mapped to a DB column alias and boolean "values" for adapter criteria.
 */
class AliasFieldEntityMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        AliasFieldEntity::class => [
            'repositoryClass' => MockEntityRepository::class,
            'dataAdapterClass' => MockEntityDataAdapter::class,
            'id' => [
                'id' => [
                    'type' => 'int',
                ],
            ],
            'fields' => [
                'active' => [
                    'type' => 'bool',
                    'values' => [
                        'Y' => true,
                        'N' => false,
                    ],
                ],
                'name' => [
                    'type' => 'string',
                    'column' => 'db_title',
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
