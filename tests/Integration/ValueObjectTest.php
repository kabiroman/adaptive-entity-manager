<?php

namespace Kabiroman\AEM\Tests\Integration;

use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Tests\Mock\Entity\ValueObjectEntity;
use Kabiroman\AEM\ValueObject\Common\Email;
use Kabiroman\AEM\ValueObject\Common\Money;
use Kabiroman\AEM\ValueObject\Common\UserId;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;
use Kabiroman\AEM\DataAdapter\DefaultEntityDataAdapterProvider;
use Kabiroman\AEM\Metadata\DefaultEntityMetadataProvider;
use PHPUnit\Framework\TestCase;

class ValueObjectTest extends TestCase
{
    private AdaptiveEntityManager $entityManager;
    private ValueObjectConverterRegistry $converterRegistry;

    protected function setUp(): void
    {
        $config = new Config(
            entityFolder: __DIR__ . '/../Mock/Entity',
            entityNamespace: 'Kabiroman\\AEM\\Tests\\Mock\\Entity\\',
            cacheFolder: __DIR__ . '/../../var/cache'
        );

        $this->converterRegistry = new ValueObjectConverterRegistry();

        $this->entityManager = new AdaptiveEntityManager(
            config: $config,
            classMetadataProvider: new DefaultEntityMetadataProvider(),
            entityDataAdapterProvider: new DefaultEntityDataAdapterProvider(),
            valueObjectRegistry: $this->converterRegistry
        );
    }

    public function testCreateValueObjectEntity(): void
    {
        $entity = new ValueObjectEntity();
        $entity->setId(1);
        $entity->setName('Test Product');
        $entity->setEmail(Email::fromPrimitive('test@example.com'));
        $entity->setPrice(Money::fromPrimitive(['amount' => 12500, 'currency' => 'USD']));
        $entity->setCreatedBy(UserId::fromPrimitive(42));
        $entity->setSecondaryEmail(Email::fromPrimitive('secondary@example.com'));

        $this->assertInstanceOf(ValueObjectEntity::class, $entity);
        $this->assertEquals('test@example.com', $entity->getEmail()->getValue());
        $this->assertEquals(12500, $entity->getPrice()->getAmount());
        $this->assertEquals('USD', $entity->getPrice()->getCurrency());
        $this->assertEquals(42, $entity->getCreatedBy()->getValue());
        $this->assertEquals('secondary@example.com', $entity->getSecondaryEmail()->getValue());
    }

    public function testValueObjectConversion(): void
    {
        // Test Email conversion
        $email = Email::fromPrimitive('test@example.com');
        $this->assertEquals('test@example.com', $email->toPrimitive());
        $this->assertEquals('example.com', $email->getDomain());
        $this->assertEquals('test', $email->getLocalPart());

        // Test Money conversion
        $money = Money::fromPrimitive(['amount' => 10000, 'currency' => 'EUR']);
        $this->assertEquals('{"amount":10000,"currency":"EUR"}', $money->toPrimitive());
        $this->assertEquals(10000, $money->getAmount());
        $this->assertEquals('EUR', $money->getCurrency());
        $this->assertEquals(100.0, $money->getFormattedAmount());

        // Test UserId conversion
        $userId = UserId::fromPrimitive(123);
        $this->assertEquals(123, $userId->toPrimitive());
        $this->assertEquals(123, $userId->getValue());
    }

    public function testValueObjectEquality(): void
    {
        $email1 = Email::fromPrimitive('test@example.com');
        $email2 = Email::fromPrimitive('test@example.com');
        $email3 = Email::fromPrimitive('different@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));

        $money1 = Money::fromPrimitive(['amount' => 5000, 'currency' => 'USD']);
        $money2 = Money::fromPrimitive(['amount' => 5000, 'currency' => 'USD']);
        $money3 = Money::fromPrimitive(['amount' => 5000, 'currency' => 'EUR']);

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testMoneyOperations(): void
    {
        $money1 = Money::fromPrimitive(['amount' => 5000, 'currency' => 'USD']);
        $money2 = Money::fromPrimitive(['amount' => 3000, 'currency' => 'USD']);

        $sum = $money1->add($money2);
        $this->assertEquals(8000, $sum->getAmount());
        $this->assertEquals('USD', $sum->getCurrency());

        $diff = $money1->subtract($money2);
        $this->assertEquals(2000, $diff->getAmount());

        $multiplied = $money1->multiply(2);
        $this->assertEquals(10000, $multiplied->getAmount());

        $this->assertTrue($money1->isPositive());
        $this->assertFalse($money1->isZero());
        $this->assertFalse($money1->isNegative());
    }

    public function testValueObjectValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid email address');
        Email::fromPrimitive('invalid-email');
    }

    public function testUserIdValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be a positive integer');
        UserId::fromPrimitive(-1);
    }

    public function testMoneyValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter code');
        Money::fromPrimitive(['amount' => 1000, 'currency' => 'INVALID']);
    }

    public function testConverterRegistry(): void
    {
        $this->assertTrue($this->converterRegistry->supports(Email::class));
        $this->assertTrue($this->converterRegistry->supports(Money::class));
        $this->assertTrue($this->converterRegistry->supports(UserId::class));
        $this->assertFalse($this->converterRegistry->supports('NonExistentClass'));

        // Test conversion through registry
        $email = $this->converterRegistry->convertToPHP('test@example.com', Email::class);
        $this->assertInstanceOf(Email::class, $email);
        $this->assertEquals('test@example.com', $email->getValue());

        $primitiveValue = $this->converterRegistry->convertToDatabase($email);
        $this->assertEquals('test@example.com', $primitiveValue);
    }
}
