<?php

namespace Kabiroman\AEM\ValueObject\Common;

use Kabiroman\AEM\ValueObject\AbstractValueObject;
use InvalidArgumentException;

/**
 * Money Value Object.
 * Represents a monetary amount with currency.
 */
final class Money extends AbstractValueObject
{
    public function __construct(
        private readonly int $amount, // Amount in smallest currency unit (e.g., cents)
        private readonly string $currency
    ) {
        $this->validate($amount, $currency);
    }

    public static function fromPrimitive(mixed $value): static
    {
        if (is_string($value)) {
            $data = json_decode($value, true);
            if (!is_array($data) || !isset($data['amount'], $data['currency'])) {
                throw new InvalidArgumentException('Invalid money format');
            }
            return new self((int)$data['amount'], $data['currency']);
        }

        if (is_array($value) && isset($value['amount'], $value['currency'])) {
            return new self((int)$value['amount'], $value['currency']);
        }

        throw new InvalidArgumentException('Money must be an array or JSON string with amount and currency');
    }

    public function toPrimitive(): string
    {
        return json_encode([
            'amount' => $this->amount,
            'currency' => $this->currency
        ]);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFormattedAmount(): float
    {
        return $this->amount / 100; // Assuming 2 decimal places
    }

    public function add(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot add money with different currencies');
        }

        return new Money($this->amount + $other->amount, $this->currency);
    }

    public function subtract(Money $other): Money
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Cannot subtract money with different currencies');
        }

        return new Money($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): Money
    {
        return new Money($this->amount * $multiplier, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function __toString(): string
    {
        return sprintf('%.2f %s', $this->getFormattedAmount(), $this->currency);
    }

    private function validate(int $amount, string $currency): void
    {
        if (empty($currency) || strlen($currency) !== 3) {
            throw new InvalidArgumentException('Currency must be a 3-letter code');
        }

        if (!ctype_upper($currency)) {
            throw new InvalidArgumentException('Currency must be uppercase');
        }
    }
}
