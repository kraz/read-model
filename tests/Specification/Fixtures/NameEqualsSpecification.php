<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Specification\Fixtures;

use Kraz\ReadModel\Specification\AbstractSpecification;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;

/** @phpstan-extends AbstractSpecification<PersonFixture> */
final class NameEqualsSpecification extends AbstractSpecification
{
    public function __construct(private readonly string $name)
    {
    }

    /** @phpstan-param PersonFixture|array<string, mixed> $item */
    public function isSatisfiedBy(object|array $item): bool
    {
        $satisfies = $item instanceof PersonFixture && $item->name === $this->name;

        return $this->inverted() ? ! $satisfies : $satisfies;
    }
}
