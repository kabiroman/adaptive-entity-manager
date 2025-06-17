<?php

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\ValueObjectEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;
use Kabiroman\AEM\ValueObject\Common\Email;
use Kabiroman\AEM\ValueObject\Common\Money;
use Kabiroman\AEM\ValueObject\Common\UserId;

class ValueObjectEntityMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        ValueObjectEntity::class => [
            'repositoryClass' => MockEntityRepository::class,
            'dataAdapterClass' => MockEntityDataAdapter::class,
            'id' => [
                'id' => [
                    'type' => 'int',
                ],
            ],
            'fields' => [
                'name' => [
                    'type' => 'string'
                ],
                'email' => [
                    'type' => 'value_object',
                    'valueObjectClass' => Email::class,
                ],
                'price' => [
                    'type' => 'value_object',
                    'valueObjectClass' => Money::class,
                    'column' => 'price_json', // Store as JSON in database
                ],
                'createdBy' => [
                    'type' => 'value_object',
                    'valueObjectClass' => UserId::class,
                    'column' => 'created_by_id',
                ],
                'secondaryEmail' => [
                    'type' => 'value_object',
                    'valueObjectClass' => Email::class,
                    'column' => 'secondary_email',
                    'nullable' => true,
                ],
            ],
        ]
    ];
}
