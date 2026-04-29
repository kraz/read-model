<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Specification;

use Kraz\ReadModel\Query\QueryExpression;
use Override;

use function array_any;
use function array_filter;
use function array_map;
use function array_values;

/**
 * @phpstan-template T of object|array<string, mixed>
 * @phpstan-extends CompositeSpecification<T>
 */
class CompositeAndSpecification extends CompositeSpecification
{
    public function isSatisfiedBy(object|array $item): bool
    {
        if (array_any($this->specifications(), static fn ($specification) => ! $specification->isSatisfiedBy($item))) {
            return $this->inverted();
        }

        return ! $this->inverted();
    }

    #[Override]
    protected function buildQueryExpression(): QueryExpression|null
    {
        $items = array_values(array_filter(array_map(
            static fn (SpecificationInterface $spec) => $spec->getQueryExpression(),
            $this->specifications(),
        )));

        return QueryExpression::create()->wrapAnd(...$items);
    }
}
