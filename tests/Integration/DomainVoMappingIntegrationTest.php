<?php

declare(strict_types=1);

namespace Kabiroman\AEM\Tests\Integration;

use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Metadata\DefaultEntityMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Entity\DomainVoBadFactoryEntity;
use Kabiroman\AEM\Tests\Mock\Entity\DomainVoConflictEntity;
use Kabiroman\AEM\Tests\Mock\Entity\DomainVoEntity;
use Kabiroman\AEM\Tests\Mock\Entity\DomainVoUnionEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\Tests\Mock\ValueObject\DomainPlainEmail;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainVoMappingIntegrationTest extends TestCase
{
    private static string $cacheDir;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$cacheDir = sys_get_temp_dir() . '/aem-domain-vo-' . uniqid('', true);
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

    public function testHydratesDomainPlainEmailViaMetadataFrom(): void
    {
        $adapter = new MockEntityDataAdapter([
            1 => [
                'name' => 'Product',
                'plainEmail' => 'buyer@example.org',
            ],
        ]);
        $em = $this->createManager($adapter);
        $entity = $em->find(DomainVoEntity::class, 1);
        self::assertInstanceOf(DomainVoEntity::class, $entity);
        self::assertSame('buyer@example.org', $entity->getPlainEmail()->getValue());
    }

    public function testInsertConvertsDomainPlainEmailToStringForAdapter(): void
    {
        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $adapter->expects(self::once())->method('insert')->with(
            self::callback(static function (array $row): bool {
                return isset($row['plainEmail'])
                    && $row['plainEmail'] === 'persist@example.com'
                    && $row['name'] === 'N';
            }),
        )->willReturn(['id' => 99]);

        $adapter->expects(self::once())->method('loadById')->willReturn([
            'id' => 99,
            'name' => 'N',
            'plainEmail' => 'persist@example.com',
        ]);

        $em = $this->createManagerFromMockAdapter($adapter);

        $entity = new DomainVoEntity();
        $entity->setName('N');
        $entity->setPlainEmail(DomainPlainEmail::fromString('persist@example.com'));
        $em->persist($entity);
        $em->flush();
    }

    public function testCriteriaRejectsObjectOfWrongType(): void
    {
        $provider = new DefaultEntityMetadataProvider();
        $config = new Config(
            __DIR__ . '/../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            self::$cacheDir,
        );

        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $wrong = new class implements \Stringable {
            public function __toString(): string
            {
                return 'not-a-domain-vo@example.com';
            }
        };

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
            null,
            new ValueObjectConverterRegistry(),
        );
        $em->clear();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an instance of');

        $persister = $em->getUnitOfWork()->getEntityPersister($em->getClassMetadata(DomainVoEntity::class));
        $persister->loadAll(['plainEmail' => $wrong]);
    }

    public function testMetadataClassAndValueObjectClassMustNotConflict(): void
    {
        $adapter = new MockEntityDataAdapter([
            1 => [
                'name' => 'X',
                'plainEmail' => 'a@b.com',
            ],
        ]);
        $em = $this->createManager($adapter);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('conflict');

        $em->find(DomainVoConflictEntity::class, 1);
    }

    public function testLoadAllMapsCriteriaValueObjectToPrimitive(): void
    {
        $provider = new DefaultEntityMetadataProvider();
        $config = new Config(
            __DIR__ . '/../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            self::$cacheDir,
        );

        $adapter = $this->createMock(MockEntityDataAdapter::class);
        $vo = DomainPlainEmail::fromString('criteria@example.com');
        $adapter->expects(self::once())->method('loadAll')->with(
            ['plainEmail' => 'criteria@example.com'],
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
            null,
            new ValueObjectConverterRegistry(),
        );
        $em->clear();

        $persister = $em->getUnitOfWork()->getEntityPersister($em->getClassMetadata(DomainVoEntity::class));
        $persister->loadAll(['plainEmail' => $vo]);
    }

    public function testStaticFactoryMustReturnInstanceOfValueObjectClass(): void
    {
        $adapter = new MockEntityDataAdapter([
            1 => [
                'name' => 'Bad',
                'plainEmail' => 'bad@example.com',
            ],
        ]);
        $em = $this->createManager($adapter);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must return');

        $em->find(DomainVoBadFactoryEntity::class, 1);
    }

    public function testUnionTypedPropertyIsRejectedForValueObjectMapping(): void
    {
        $adapter = new MockEntityDataAdapter([
            1 => [
                'plainEmail' => 'u@example.com',
            ],
        ]);
        $em = $this->createManager($adapter);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Union types');

        $em->find(DomainVoUnionEntity::class, 1);
    }

    private function createManagerFromMockAdapter(MockEntityDataAdapter $adapter): AdaptiveEntityManager
    {
        $provider = new DefaultEntityMetadataProvider();
        $config = new Config(
            __DIR__ . '/../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            self::$cacheDir,
        );

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
            null,
            new ValueObjectConverterRegistry(),
        );
        $em->clear();

        return $em;
    }

    private function createManager(MockEntityDataAdapter $adapter): AdaptiveEntityManager
    {
        $provider = new DefaultEntityMetadataProvider();
        $config = new Config(
            __DIR__ . '/../Mock/Entity',
            'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            self::$cacheDir,
        );

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
            null,
            new ValueObjectConverterRegistry(),
        );
        $em->clear();

        return $em;
    }
}
