<?php

declare(strict_types=1);

namespace Kabiroman\AEM;

use Kabiroman\AEM\ValueObject\Converter\ValueObjectConverterRegistry;

/**
 * Entity managers that support optional ValueObjectConverterRegistry wiring.
 * Kept separate from {@see EntityManagerInterface} so third-party implementors
 * of the base interface are not forced to add VO methods in a minor release.
 */
interface ValueObjectAwareEntityManagerInterface extends EntityManagerInterface
{
    public function getValueObjectRegistry(): ?ValueObjectConverterRegistry;

    public function hasValueObjectSupport(): bool;
}
