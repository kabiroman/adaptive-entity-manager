<?php

namespace Kabiroman\AEM\Tests\Unit\Constant;

use Kabiroman\AEM\Constant\FieldTypeEnum;
use PHPUnit\Framework\TestCase;

class FieldTypeEnumTest extends TestCase
{
    /**
     * @dataProvider typeNormalizationProvider
     */
    public function testTypeNormalization(string $inputType, string $expectedType): void
    {
        $this->assertEquals($expectedType, FieldTypeEnum::normalizeType($inputType));
    }

    public static function typeNormalizationProvider(): array
    {
        return [
            'integer type' => ['integer', 'int'],
            'int type' => ['int', 'int'],
            'INTEGER uppercase' => ['INTEGER', 'int'],
            'Int mixed case' => ['Int', 'int'],
            'string type' => ['string', 'string'],
            'boolean type' => ['boolean', 'bool'],
            'bool type' => ['bool', 'bool'],
            'datetime type' => ['datetime', 'datetime'],
            'float type' => ['float', 'float'],
            'unknown type' => ['custom', 'custom'],
        ];
    }
}
