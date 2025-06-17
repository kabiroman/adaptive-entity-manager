<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\DataAdapter\DefaultEntityDataAdapterProvider;
use Kabiroman\AEM\Metadata\DefaultEntityMetadataProvider;
use Kabiroman\AEM\ValueObject\Common\Email;
use Kabiroman\AEM\ValueObject\Common\Money;
use Kabiroman\AEM\ValueObject\Common\UserId;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;

echo "=== Adaptive Entity Manager - Value Objects Demo ===\n\n";

// === 1. Basic Value Object Creation ===
echo "1. Creating Value Objects:\n";

try {
    $email = Email::fromPrimitive('user@example.com');
    echo "✓ Email: {$email}\n";
    echo "  - Domain: {$email->getDomain()}\n";
    echo "  - Local part: {$email->getLocalPart()}\n";
} catch (Exception $e) {
    echo "✗ Email error: {$e->getMessage()}\n";
}

try {
    $money = Money::fromPrimitive(['amount' => 12500, 'currency' => 'USD']);
    echo "✓ Money: {$money}\n";
    echo "  - Amount: {$money->getAmount()} cents\n";
    echo "  - Formatted: \${$money->getFormattedAmount()}\n";
    echo "  - Currency: {$money->getCurrency()}\n";
} catch (Exception $e) {
    echo "✗ Money error: {$e->getMessage()}\n";
}

try {
    $userId = UserId::fromPrimitive(42);
    echo "✓ User ID: {$userId}\n";
    echo "  - Value: {$userId->getValue()}\n";
} catch (Exception $e) {
    echo "✗ User ID error: {$e->getMessage()}\n";
}

echo "\n";

// === 2. Value Object Operations ===
echo "2. Value Object Operations:\n";

$price1 = Money::fromPrimitive(['amount' => 10000, 'currency' => 'USD']);
$price2 = Money::fromPrimitive(['amount' => 5000, 'currency' => 'USD']);

echo "Price 1: {$price1}\n";
echo "Price 2: {$price2}\n";

$total = $price1->add($price2);
echo "Total: {$total}\n";

$discount = $price1->subtract($price2);
echo "Difference: {$discount}\n";

$doubled = $price1->multiply(2);
echo "Doubled: {$doubled}\n";

echo "Is positive: " . ($price1->isPositive() ? 'Yes' : 'No') . "\n";
echo "Is zero: " . ($price1->isZero() ? 'Yes' : 'No') . "\n";

echo "\n";

// === 3. Value Object Equality ===
echo "3. Value Object Equality:\n";

$email1 = Email::fromPrimitive('test@example.com');
$email2 = Email::fromPrimitive('test@example.com');
$email3 = Email::fromPrimitive('different@example.com');

echo "Email1: {$email1}\n";
echo "Email2: {$email2}\n";
echo "Email3: {$email3}\n";

echo "Email1 equals Email2: " . ($email1->equals($email2) ? 'Yes' : 'No') . "\n";
echo "Email1 equals Email3: " . ($email1->equals($email3) ? 'Yes' : 'No') . "\n";

echo "\n";

// === 4. Validation Examples ===
echo "4. Validation Examples:\n";

// Invalid email
try {
    $invalidEmail = Email::fromPrimitive('not-an-email');
    echo "✗ This should not appear\n";
} catch (InvalidArgumentException $e) {
    echo "✓ Invalid email caught: {$e->getMessage()}\n";
}

// Invalid user ID
try {
    $invalidUserId = UserId::fromPrimitive(-1);
    echo "✗ This should not appear\n";
} catch (InvalidArgumentException $e) {
    echo "✓ Invalid user ID caught: {$e->getMessage()}\n";
}

// Invalid currency
try {
    $invalidMoney = Money::fromPrimitive(['amount' => 1000, 'currency' => 'INVALID']);
    echo "✗ This should not appear\n";
} catch (InvalidArgumentException $e) {
    echo "✓ Invalid currency caught: {$e->getMessage()}\n";
}

echo "\n";

// === 5. Converter Registry Demo ===
echo "5. Converter Registry:\n";

$registry = new ValueObjectConverterRegistry();

echo "Supports Email: " . ($registry->supports(Email::class) ? 'Yes' : 'No') . "\n";
echo "Supports Money: " . ($registry->supports(Money::class) ? 'Yes' : 'No') . "\n";
echo "Supports UserId: " . ($registry->supports(UserId::class) ? 'Yes' : 'No') . "\n";
echo "Supports stdClass: " . ($registry->supports(\stdClass::class) ? 'Yes' : 'No') . "\n";

// Convert through registry
$emailViaRegistry = $registry->convertToPHP('test@registry.com', Email::class);
echo "Email via registry: {$emailViaRegistry}\n";

$primitiveValue = $registry->convertToDatabase($emailViaRegistry);
echo "Back to primitive: {$primitiveValue}\n";

echo "\n";

// === 6. Entity Manager with Value Objects ===
echo "6. Entity Manager Configuration:\n";

try {
    $config = new Config(
        entityFolder: __DIR__ . '/../tests/Mock/Entity',
        entityNamespace: 'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
        cacheFolder: __DIR__ . '/../var/cache'
    );

    $valueObjectRegistry = new ValueObjectConverterRegistry();
    
    $entityManager = new AdaptiveEntityManager(
        config: $config,
        classMetadataProvider: new DefaultEntityMetadataProvider(),
        entityDataAdapterProvider: new DefaultEntityDataAdapterProvider(),
        valueObjectRegistry: $valueObjectRegistry
    );

    echo "✓ Entity Manager created with Value Object support\n";
    echo "  - Has Value Object support: " . ($entityManager->hasValueObjectSupport() ? 'Yes' : 'No') . "\n";
    
} catch (Exception $e) {
    echo "✗ Entity Manager error: {$e->getMessage()}\n";
}

echo "\n";

// === 7. Serialization Demo ===
echo "7. Serialization:\n";

$email = Email::fromPrimitive('serialize@example.com');
$money = Money::fromPrimitive(['amount' => 15000, 'currency' => 'EUR']);
$userId = UserId::fromPrimitive(123);

echo "Email primitive: " . var_export($email->toPrimitive(), true) . "\n";
echo "Money primitive: " . var_export($money->toPrimitive(), true) . "\n";
echo "UserId primitive: " . var_export($userId->toPrimitive(), true) . "\n";

echo "\n=== Demo Complete ===\n";
