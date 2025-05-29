<?php

namespace Kabiroman\AEM\Tests\Unit\Repository;

use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Orm\MockClassMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

class EntityRepositoryTest extends TestCase
{
    private MockEntityDataAdapter $adapter;
    private AdaptiveEntityManager $em;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->em = new AdaptiveEntityManager(
            new Config(
                __DIR__.'/../Entity',
                'App\\Tests\\Entity\\',
            ),
            new MockClassMetadataProvider(),
            new MockEntityDataAdapterProvider(
                $this->adapter = $this->createMock(MockEntityDataAdapter::class)
            )
        );
        $this->em->clear();
    }

    public function testGetClassName(): void
    {
        $className = $this->em->getRepository(MockEntity::class)->getClassName();
        $this->assertEquals(MockEntity::class, $className);
    }

    public function testFind(): void
    {
        $this->adapter->method('loadById')->willReturn(null);
        $this->adapter->expects($this->once())->method('loadById');

        $this->em->getRepository(MockEntity::class)->find(1);
    }

    public function testFindOneBy(): void
    {
        $this->adapter->expects($this->once())->method('loadAll');
        $this->em->getRepository(MockEntity::class)->findOneBy(['id' => 1]);
    }

    public function testFindBy(): void
    {
        $this->adapter->expects($this->once())->method('loadAll');
        $this->em->getRepository(MockEntity::class)->findBy(['id' => 1]);
    }

    public function testFindAll(): void
    {
        $this->adapter->expects($this->once())->method('loadAll');
        $this->em->getRepository(MockEntity::class)->findAll();
    }
}
