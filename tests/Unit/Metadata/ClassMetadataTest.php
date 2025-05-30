<?php

namespace Kabiroman\AEM\Tests\Unit\Metadata;

use Kabiroman\AEM\Constant\FieldTypeEnum;
use Kabiroman\AEM\Tests\Mock\Entity\MockEntity;
use Kabiroman\AEM\Tests\Mock\Metadata\MockEntityMetadata;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Repository\MockEntityRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class ClassMetadataTest extends TestCase
{
    private MockEntityMetadata $classMetadata;

    public function setUp(): void
    {
        $this->classMetadata = new MockEntityMetadata();
    }

    public function testGetName(): void
    {
        $this->assertEquals(MockEntity::class, $this->classMetadata->getName());
    }

    public function testGetIdentifier(): void
    {
        $this->assertEquals(['id'], $this->classMetadata->getIdentifier());
    }

    public function testGetIdentifierFieldNames(): void
    {
        $this->assertEquals(['id'], $this->classMetadata->getIdentifierFieldNames());
    }

    /**
     * @throws ReflectionException
     */
    public function testGetReflection(): void
    {
        $this->assertInstanceOf(ReflectionClass::class, $this->classMetadata->getReflectionClass());
    }

    public function testGetFieldNames(): void
    {
        $this->assertEquals(array(
            'id',
            'active',
            'name',
            'createdAt',
            'price',
            'nullable',
        ), $this->classMetadata->getFieldNames());
    }

    public function testGetTypeOfField(): void
    {
        $this->assertEquals(FieldTypeEnum::Integer->value, $this->classMetadata->getTypeOfField('id'));
        $this->assertEquals(FieldTypeEnum::Boolean->value, $this->classMetadata->getTypeOfField('active'));
        $this->assertEquals(FieldTypeEnum::String->value, $this->classMetadata->getTypeOfField('name'));
        $this->assertEquals(FieldTypeEnum::DateTime->value, $this->classMetadata->getTypeOfField('createdAt'));
        $this->assertEquals(FieldTypeEnum::Float->value, $this->classMetadata->getTypeOfField('price'));
    }

    public function testIsIdentifier(): void
    {
        $this->assertTrue($this->classMetadata->isIdentifier('id'));
        $this->assertFalse($this->classMetadata->isIdentifier('not_id'));
    }

    public function testGetSpecifiedRepositoryName(): void
    {
        $this->assertEquals(MockEntityRepository::class, $this->classMetadata->getSpecifiedRepositoryName());
    }

    public function testGetEntityDataAdapterClass(): void
    {
        $this->assertEquals(MockEntityDataAdapter::class, $this->classMetadata->getEntityDataAdapterClass());
    }

    public function testHasField(): void
    {
        $this->assertTrue($this->classMetadata->hasField('id'));
        $this->assertTrue($this->classMetadata->hasField('active'));
        $this->assertTrue($this->classMetadata->hasField('name'));
        $this->assertTrue($this->classMetadata->hasField('createdAt'));
        $this->assertTrue($this->classMetadata->hasField('price'));
        $this->assertFalse($this->classMetadata->hasField('non-existent-field'));
    }

    /**
     * @throws ReflectionException
     */
    public function testGetIdentifierValues(): void
    {
        $entity = new MockEntity();
        $entity->setId(12345);
        $this->assertEquals(['id' => 12345], $this->classMetadata->getIdentifierValues($entity));
    }
}
