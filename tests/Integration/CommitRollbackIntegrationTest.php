<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration;

use DateTime;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\MockEntityMetadata;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\TransactionalConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CommitRollbackIntegrationTest extends TestCase
{
    public function testFlushRollsBackWhenInsertFails(): void
    {
        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $connection = $this->createMock(TransactionalConnection::class);

        $metadataFactory = $this->createMock(EntityMetadataFactory::class);
        $metadataFactory->method('getMetadataFor')->willReturn(new MockEntityMetadata());

        $em = new AdaptiveEntityManager(
            new Config(
                __DIR__ . '/../Mock/Entity',
                'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
                __DIR__ . '/../../var/cache',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider($adapter),
            $connection,
            $metadataFactory,
            null,
            null,
            null,
            false,
        );
        $em->clear();

        $connection->expects(self::once())->method('beginTransaction');
        $connection->expects(self::once())->method('rollbackTransaction');
        $connection->expects(self::never())->method('commitTransaction');

        $adapter->expects(self::once())->method('insert')->willThrowException(
            new RuntimeException('adapter insert failed'),
        );

        $this->expectException(CommitFailedException::class);

        $em->persist($entity = new MockEntity());
        $entity
            ->setActive(true)
            ->setName('n')
            ->setCreatedAt(new DateTime())
            ->setPrice(1.0)
            ->setNullable(null);

        $em->flush();
    }
}
