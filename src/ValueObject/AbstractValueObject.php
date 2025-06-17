<?php

namespace Kabiroman\AEM\ValueObject;

/**
 * Abstract base class for Value Objects providing common functionality.
 */
abstract class AbstractValueObject implements ValueObjectInterface
{
    public function equals(ValueObjectInterface $other): bool
    {
        if (!$other instanceof static) {
            return false;
        }

        return $this->toPrimitive() === $other->toPrimitive();
    }

    public function __toString(): string
    {
        $primitive = $this->toPrimitive();
        return is_scalar($primitive) ? (string)$primitive : json_encode($primitive);
    }
}
