<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Unit\Persistence;

use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Tests\Mock\Entity\AliasFieldEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class EntityPersisterLoadAllTest extends TestCase
{
    private static string $cacheDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$cacheDir = sys_get_temp_dir() . '/aem-loadall-' . uniqid('', true);
        mkdir(self::$cacheDir, 0777, true);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_dir(self::$cacheDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::$cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );
            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getPathname());
                } else {
                    unlink($file->getPathname());
                }
            }
            rmdir(self::$cacheDir);
        }
        parent::tearDownAfterClass();
    }

    public function testLoadAllMapsCriteriaFieldNamesToColumnsAndBooleanTrueToSourceValue(): void
    {
        $provider = new MockClassMetadataProvider();
        $config = new Config(
            __DIR__ . '/../../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            self::$cacheDir,
        );

        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $adapter->expects(self::once())->method('loadAll')->with(
            ['db_title' => 'hello', 'active' => 'Y'],
            null,
            null,
            null,
        )->willReturn([]);

        $em = new AdaptiveEntityManager(
            $config,
            $provider,
            new MockEntityDataAdapterProvider($adapter),
            null,
            new EntityMetadataFactory($config, $provider),
            null,
            null,
            null,
            false,
        );
        $em->clear();

        $persister = $em->getUnitOfWork()->getEntityPersister($em->getClassMetadata(AliasFieldEntity::class));
        $persister->loadAll(['name' => 'hello', 'active' => true]);
    }

    public function testLoadAllMapsBooleanFalseToSourceValue(): void
    {
        $provider = new MockClassMetadataProvider();
        $config = new Config(
            __DIR__ . '/../../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            self::$cacheDir,
        );

        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $adapter->expects(self::once())->method('loadAll')->with(
            ['active' => 'N'],
            null,
            null,
            null,
        )->willReturn([]);

        $em = new AdaptiveEntityManager(
            $config,
            $provider,
            new MockEntityDataAdapterProvider($adapter),
            null,
            new EntityMetadataFactory($config, $provider),
            null,
            null,
            null,
            false,
        );
        $em->clear();

        $persister = $em->getUnitOfWork()->getEntityPersister($em->getClassMetadata(AliasFieldEntity::class));
        $persister->loadAll(['active' => false]);
    }
}
