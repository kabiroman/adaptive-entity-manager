<?php

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\IntegerTypeEntity;

class IntegerTypeEntityMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        IntegerTypeEntity::class => [
            'id' => [
                'id' => [
                    'type' => 'integer',
                ],
            ],
            'fields' => [],
        ]
    ];
}
