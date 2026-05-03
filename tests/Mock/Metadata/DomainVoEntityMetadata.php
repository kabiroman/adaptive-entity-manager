<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\DomainVoEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;
use Kabiroman\AEM\Tests\Mock\ValueObject\DomainPlainEmail;

final class DomainVoEntityMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        DomainVoEntity::class => [
            'repositoryClass' => MockEntityRepository::class,
            'dataAdapterClass' => MockEntityDataAdapter::class,
            'id' => [
                'id' => [
                    'type' => 'int',
                ],
            ],
            'fields' => [
                'name' => [
                    'type' => 'string',
                ],
                'plainEmail' => [
                    'type' => 'value_object',
                    'class' => DomainPlainEmail::class,
                    'from' => 'fromString',
                    'to' => '__toString',
                ],
            ],
        ],
    ];
}
