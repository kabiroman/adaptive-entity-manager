<?php

declare(strict_types=1);

namespace Examples\Ddd\Metadata;

/**
 * Demo metadata only: uses the same mock repository/adapter classes as package integration tests;
 * in your app, point `repositoryClass` / `dataAdapterClass` at your own classes.
 */

use Examples\Ddd\Domain\EmailAddress;
use Examples\Ddd\Entity\NewsletterSubscriber;
use Kabiroman\AEM\Metadata\AbstractClassMetadata;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;

final class NewsletterSubscriberMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        NewsletterSubscriber::class => [
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
                'email' => [
                    'type' => 'value_object',
                    'class' => EmailAddress::class,
                    'from' => 'fromString',
                    'to' => '__toString',
                ],
            ],
        ],
    ];
}
