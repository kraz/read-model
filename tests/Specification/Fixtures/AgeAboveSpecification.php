<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Specification\Fixtures;

use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Specification\AbstractSpecification;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;

/** @phpstan-extends AbstractSpecification<PersonFixture> */
final class AgeAboveSpecification extends AbstractSpecification
{
    public function __construct(private readonly int $minAge)
    {
    }

    /** @phpstan-param PersonFixture|array<string, mixed> $item */
    public function isSatisfiedBy(object|array $item): bool
    {
        $satisfies = $item instanceof PersonFixture && $item->age > $this->minAge;

        return $this->inverted() ? ! $satisfies : $satisfies;
    }

    protected function buildQueryExpression(): QueryExpression
    {
        return QueryExpression::create()->andWhere(
            FilterExpression::create()->greaterThan('age', $this->minAge),
        );
    }
}
