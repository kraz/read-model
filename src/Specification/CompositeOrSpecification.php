<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Specification;

use Kraz\ReadModel\Query\QueryExpression;
use Override;

use function array_any;
use function count;

/**
 * @phpstan-template T of object|array<string, mixed>
 * @phpstan-extends CompositeSpecification<T>
 */
class CompositeOrSpecification extends CompositeSpecification
{
    public function isSatisfiedBy(object|array $item): bool
    {
        if (array_any($this->specifications(), static fn ($specification) => $specification->isSatisfiedBy($item))) {
            return ! $this->inverted();
        }

        return $this->inverted();
    }

    #[Override]
    protected function buildQueryExpression(): QueryExpression|null
    {
        $items = [];
        foreach ($this->specifications() as $spec) {
            $qe = $spec->getQueryExpression();
            if ($qe === null || $qe->isEmpty()) {
                return null;
            }

            $items[] = $qe;
        }

        if (count($items) === 0) {
            return null;
        }

        return QueryExpression::create()->wrapOr(...$items);
    }
}
