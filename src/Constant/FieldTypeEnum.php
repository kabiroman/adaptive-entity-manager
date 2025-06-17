<?php

namespace Kabiroman\AEM\Constant;

enum FieldTypeEnum: string
{
    case Integer = 'int';
    case String = 'string';
    case Boolean = 'bool';
    case DateTime = 'datetime';
    case Float = 'float';
    case ValueObject = 'value_object';

    public static function normalizeType(string $type): string
    {
        return match (strtolower($type)) {
            'integer', 'int' => self::Integer->value,
            'string' => self::String->value,
            'bool', 'boolean' => self::Boolean->value,
            'datetime', 'datetimeinterface', 'datetime_immutable', 'datetimeimmutable' => self::DateTime->value,
            'float' => self::Float->value,
            'value_object', 'valueobject' => self::ValueObject->value,
            default => $type,
        };
    }

    /**
     * Check if the given type is a primitive type.
     */
    public static function isPrimitive(string $type): bool
    {
        $normalized = self::normalizeType($type);
        return in_array($normalized, [
            self::Integer->value,
            self::String->value,
            self::Boolean->value,
            self::DateTime->value,
            self::Float->value,
        ]);
    }

    /**
     * Check if the given type is a Value Object type.
     */
    public static function isValueObject(string $type): bool
    {
        return self::normalizeType($type) === self::ValueObject->value;
    }
}
