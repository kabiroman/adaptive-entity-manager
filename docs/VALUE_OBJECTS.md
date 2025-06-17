# Value Objects Support

The Adaptive Entity Manager supports Value Objects - immutable objects that are defined by their values rather than identity. This feature provides better type safety, validation, and domain modeling capabilities.

## What are Value Objects?

Value Objects are small objects that represent a simple entity whose equality is not based on identity but on the values of its attributes. Examples include:

- Money (amount + currency)
- Email addresses
- Phone numbers
- Postal codes
- User IDs

## Key Characteristics

1. **Immutable**: Once created, they cannot be changed
2. **Value-based equality**: Two objects are equal if their values are equal
3. **Self-validating**: They validate their data upon creation
4. **No identity**: They don't have an ID or primary key

## Usage

### 1. Creating Value Objects

All Value Objects must implement `ValueObjectInterface`:

```php
use Kabiroman\AEM\ValueObject\ValueObjectInterface;

class Email implements ValueObjectInterface
{
    public function __construct(private readonly string $email)
    {
        $this->validate($email);
    }

    public static function fromPrimitive(mixed $value): static
    {
        return new self($value);
    }

    public function toPrimitive(): string
    {
        return $this->email;
    }

    public function equals(ValueObjectInterface $other): bool
    {
        return $other instanceof self && $this->email === $other->email;
    }

    public function __toString(): string
    {
        return $this->email;
    }

    private function validate(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }
    }
}
```

### 2. Using Abstract Base Class

For convenience, you can extend `AbstractValueObject`:

```php
use Kabiroman\AEM\ValueObject\AbstractValueObject;

class UserId extends AbstractValueObject
{
    public function __construct(private readonly int $id)
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }
    }

    public static function fromPrimitive(mixed $value): static
    {
        return new self((int) $value);
    }

    public function toPrimitive(): int
    {
        return $this->id;
    }
}
```

### 3. Entity with Value Objects

```php
use Kabiroman\AEM\ValueObject\Common\Email;
use Kabiroman\AEM\ValueObject\Common\Money;

class Product
{
    private int $id;
    private string $name;
    private Email $contactEmail;
    private Money $price;

    // ... getters and setters
}
```

### 4. Metadata Configuration

Configure your entity metadata to use Value Objects:

```php
class ProductMetadata extends AbstractClassMetadata
{
    protected array $metadata = [
        Product::class => [
            'id' => [
                'id' => ['type' => 'int'],
            ],
            'fields' => [
                'name' => ['type' => 'string'],
                'contactEmail' => [
                    'type' => 'value_object',
                    'valueObjectClass' => Email::class,
                ],
                'price' => [
                    'type' => 'value_object', 
                    'valueObjectClass' => Money::class,
                    'column' => 'price_json', // Custom column name
                ],
            ],
        ]
    ];
}
```

### 5. Entity Manager Setup

Enable Value Object support in your Entity Manager:

```php
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;

$valueObjectRegistry = new ValueObjectConverterRegistry();

$entityManager = new AdaptiveEntityManager(
    config: $config,
    classMetadataProvider: $metadataProvider,
    entityDataAdapterProvider: $adapterProvider,
    valueObjectRegistry: $valueObjectRegistry // Enable ValueObject support
);

// Now you can work with entities containing Value Objects
$product = new Product();
$product->setContactEmail(Email::fromPrimitive('contact@example.com'));
$product->setPrice(Money::fromPrimitive(['amount' => 12500, 'currency' => 'USD']));

$entityManager->persist($product);
$entityManager->flush();
```

## Built-in Value Objects

The package provides several common Value Objects:

### Email
```php
$email = Email::fromPrimitive('user@example.com');
echo $email->getDomain(); // 'example.com'
echo $email->getLocalPart(); // 'user'
```

### Money
```php
$price = Money::fromPrimitive(['amount' => 10000, 'currency' => 'USD']);
echo $price->getFormattedAmount(); // 100.00
echo $price->getCurrency(); // 'USD'

$total = $price->add(Money::fromPrimitive(['amount' => 5000, 'currency' => 'USD']));
echo $total->getFormattedAmount(); // 150.00
```

### UserId
```php
$userId = UserId::fromPrimitive(42);
echo $userId->getValue(); // 42
```

## Custom Converters

You can create custom converters for complex Value Objects:

```php
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterInterface;

class CustomValueObjectConverter implements ValueObjectConverterInterface
{
    public function supports(string $className): bool
    {
        return $className === CustomValueObject::class;
    }

    public function convertToPHP(mixed $value, string $className): ?ValueObjectInterface
    {
        // Custom conversion logic
        return CustomValueObject::fromCustomFormat($value);
    }

    public function convertToDatabase(?ValueObjectInterface $valueObject): mixed
    {
        // Custom serialization logic
        return $valueObject?->toCustomFormat();
    }
}

// Register your converter
$valueObjectRegistry->addConverter(new CustomValueObjectConverter());
```

## Database Storage

Value Objects are automatically serialized for database storage:

- **Simple objects** (like Email, UserId): Stored as their primitive value
- **Complex objects** (like Money): Stored as JSON
- **Custom serialization**: Can be handled by custom converters

## Validation

Value Objects provide automatic validation:

```php
try {
    $email = Email::fromPrimitive('invalid-email');
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(); // "invalid-email is not a valid email address"
}

try {
    $userId = UserId::fromPrimitive(-1);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(); // "User ID must be a positive integer"
}
```

## Benefits

1. **Type Safety**: PHP's type system ensures you can't accidentally pass wrong types
2. **Validation**: Data is validated at creation time
3. **Immutability**: Prevents accidental data corruption
4. **Expressiveness**: Code becomes more readable and self-documenting
5. **Reusability**: Value Objects can be used across different entities
6. **Testing**: Easier to test business logic with well-defined value objects

## Performance Considerations

- Value Objects add minimal overhead
- Serialization/deserialization is handled efficiently
- Runtime caching minimizes conversion costs
- Consider using custom converters for performance-critical paths

## Migration from Primitives

You can gradually migrate from primitive types to Value Objects:

1. Add the Value Object class
2. Update entity properties to use the Value Object type
3. Update metadata configuration
4. Enable Value Object support in Entity Manager
5. Update your application code to work with Value Objects

The system maintains backward compatibility during migration. 