<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration;

use DateTime;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Exception\CommitFailedException;
use Kabiroman\AEM\Metadata\OptimizedEntityMetadataFactory;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\TransactionalConnection;
use PHPUnit\Framework\TestCase;

final class OptimizedMetadataPersistenceIntegrationTest extends TestCase
{
    /**
     * @throws CommitFailedException
     */
    public function testFlushUsesDefaultOptimizedMetadataSystem(): void
    {
        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $connection = $this->createMock(TransactionalConnection::class);

        $config = new Config(
            __DIR__ . '/../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            __DIR__ . '/../../var/cache',
        );

        $em = new AdaptiveEntityManager(
            $config,
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider($adapter),
            $connection,
        );
        $em->clear();

        self::assertInstanceOf(OptimizedEntityMetadataFactory::class, $em->getMetadataFactory());

        $row = [
            'id' => 1,
            'active' => true,
            'name' => 'test',
            'createdAt' => new DateTime(),
            'price' => 25.65,
            'nullable' => null,
        ];
        $adapter->method('loadById')->willReturn($row);
        $adapter->method('insert')->willReturn(['id' => 1]);
        $adapter->method('update')->withAnyParameters();
        $adapter->method('delete')->withAnyParameters();

        $adapter->expects(self::once())->method('insert');
        $adapter->expects(self::once())->method('update');
        $adapter->expects(self::once())->method('delete');
        $connection->expects(self::atMost(3))->method('beginTransaction');
        $connection->expects(self::atMost(3))->method('commitTransaction');

        $em->persist($entity = new MockEntity());
        $entity
            ->setActive(true)
            ->setName('name')
            ->setCreatedAt(new DateTime())
            ->setNullable(null)
            ->setPrice(25.65);
        $em->flush();

        $entity->setActive(false);
        $em->flush();

        $em->remove($entity);
        $em->flush();
    }
}
