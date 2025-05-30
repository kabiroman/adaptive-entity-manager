<?php

namespace Kabiroman\AEM\Constant;

enum FieldTypeEnum: string
{
    case Integer = 'int';
    case String = 'string';
    case Boolean = 'bool';
    case DateTime = 'datetime';
    case Float = 'float';

    public static function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'int' => self::Integer->value,
            'string' => self::String->value,
            'bool', 'boolean' => self::Boolean->value,
            'datetime' => self::DateTime->value,
            'float' => self::Float->value,
            default => $type,
        };
    }
}
