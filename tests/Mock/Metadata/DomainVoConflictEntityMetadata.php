<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Mock\Metadata;

use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Entity\DomainVoConflictEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;
use Kabiroman\AEM\Tests\Mock\ValueObject\DomainPlainEmail;
use Kabiroman\AEM\ValueObject\Common\Email;

final class DomainVoConflictEntityMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        DomainVoConflictEntity::class => [
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
                    'valueObjectClass' => Email::class,
                    'from' => 'fromString',
                    'to' => '__toString',
                ],
            ],
        ],
    ];
}
