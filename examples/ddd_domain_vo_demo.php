<?php

declare(strict_types=1);

/**
 * Domain-style value object without ValueObjectInterface: metadata `from` / `to`
 * on a `value_object` field.
 *
 * Run from package root (dev autoload includes Examples\Ddd\):
 *   composer install
 *   php examples/ddd_domain_vo_demo.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Examples\Ddd\Entity\NewsletterSubscriber;
use Kabiroman\AEM\AdaptiveEntityManager;
use Kabiroman\AEM\Config;
use Kabiroman\AEM\Metadata\EntityMetadataFactory;
use Kabiroman\AEM\Metadata\DefaultEntityMetadataProvider;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapter;
use Kabiroman\AEM\Tests\Mock\Orm\MockEntityDataAdapterProvider;
use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;

echo "=== DDD value object (no ValueObjectInterface) ===\n\n";

echo "Domain class Examples\\Ddd\\Domain\\EmailAddress has no AEM imports.\n";
echo "NewsletterSubscriber.email is mapped with type value_object + class + from + to.\n\n";

$cacheDir = __DIR__ . '/../var/cache/examples-ddd-domain-vo';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$config = new Config(
    entityFolder: __DIR__ . '/ddd_domain_vo/Entity',
    entityNamespace: 'Examples\\Ddd\\Entity\\',
    cacheFolder: $cacheDir,
);

$provider = new DefaultEntityMetadataProvider();
$adapter = new MockEntityDataAdapter([
    1 => [
        'name' => 'Ann',
        'email' => 'ann@example.com',
    ],
]);

$em = new AdaptiveEntityManager(
    config: $config,
    classMetadataProvider: $provider,
    entityDataAdapterProvider: new MockEntityDataAdapterProvider($adapter),
    transactionalConnection: null,
    metadataFactory: new EntityMetadataFactory($config, $provider),
    repositoryFactory: null,
    persisterFactory: null,
    metadataCache: null,
    useOptimizedMetadata: false,
    eventDispatcher: null,
    valueObjectRegistry: new ValueObjectConverterRegistry(),
);
$em->clear();

$subscriber = $em->find(NewsletterSubscriber::class, 1);
if (!$subscriber instanceof NewsletterSubscriber) {
    fwrite(STDERR, "Expected subscriber not loaded.\n");
    exit(1);
}

echo "Loaded from mock storage (email column is a plain string):\n";
echo "  id:    {$subscriber->getId()}\n";
echo "  name:  {$subscriber->getName()}\n";
echo "  email: {$subscriber->getEmail()->toString()} (";
echo get_class($subscriber->getEmail()) . ")\n\n";

echo "persist()/flush() and loadAll(criteria) use the same conversion; see ";
echo "tests/Integration/DomainVoMappingIntegrationTest.php and docs/VALUE_OBJECTS.md.\n";
echo "\nDone.\n";
