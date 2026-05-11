<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use InvalidArgumentException;
use Kraz\ReadModel\Exception\MissingValuesException;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Tools\CollectionUtils;
use LogicException;
use Override;
use Traversable;

use function array_column;
use function array_diff;
use function array_values;
use function count;
use function iterator_to_array;
use function max;
use function sprintf;

/**
 * Provides basic behavior for accessing data in ReadDataProviderInterface.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 */
trait ReadDataProviderAccess
{
    #[Override]
    public function specificationsIterator(array $specifications, int|null $limit = null, int $offset = 0, int|null $batchSize = null): Traversable
    {
        if ($limit !== null && $limit <= 0) {
            throw new InvalidArgumentException(sprintf('The limit must be positive number, %d was given!', $limit));
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('The offset can not be negative number, %d was given!', $offset));
        }

        if ($limit !== null && $batchSize !== null && $batchSize < $limit + $offset) {
            throw new InvalidArgumentException(sprintf('The batch size can not be lower than %d', $limit + $offset));
        }

        if ($batchSize !== null && $batchSize < 1) {
            throw new InvalidArgumentException('The batch size can not be lower than 1');
        }

        $base = $this;
        foreach ($specifications as $specification) {
            $qe = $specification->getQueryExpression();
            if ($qe === null || $qe->isEmpty()) {
                continue;
            }

            $base = $base->withQueryExpression($qe, true);
        }

        $batchSize ??= max(1, ($limit ?? 0) + $offset);
        $collected   = 0;
        $skipped     = 0;
        $batchOffset = 0;

        while (true) {
            /** @phpstan-var ReadDataProviderInterface<T> $batchProvider */
            $batchProvider = $base->withLimit($batchSize, $batchOffset);
            $count         = 0;

            foreach ($batchProvider->getIterator() as $item) {
                $count++;
                foreach ($specifications as $specification) {
                    /** @phpstan-var T $item */
                    if (! $specification->isSatisfiedBy($item)) {
                        continue 2;
                    }
                }

                if ($skipped < $offset) {
                    $skipped++;

                    continue;
                }

                yield $item;

                $collected++;
                if ($limit !== null && $collected >= $limit) {
                    break 2;
                }
            }

            if ($count === 0) {
                break;
            }

            $batchOffset += $batchSize;
        }
    }

    #[Override]
    public function isEmpty(): bool
    {
        return $this->totalCount() === 0;
    }

    #[Override]
    public function data(): array
    {
        $data = iterator_to_array($this->getIterator());
        if ($this->isValue()) {
            $rootIdentifier = $this->getOrCreateQueryExpressionProvider()->requireSingleRootIdentifier();
            $values         = $this->collectInputValues();
            $found          = array_column($data, $rootIdentifier);
            $missingValues  = array_values(array_diff($values, $found));
            if (count($missingValues) > 0) {
                if ($this->pagination === null && $this->limit === null) {
                    throw new MissingValuesException($missingValues, $data);
                }

                if ($this->pagination !== null) {
                    [$page, $itemsPerPage] = $this->pagination;
                    if ($itemsPerPage > count($values) && $page === 1) {
                        throw new MissingValuesException($missingValues, $data);
                    }
                }

                if ($this->limit !== null) {
                    [$limitValue, $offsetValue] = $this->limit;
                    if ($limitValue > count($values) && ($offsetValue === null || $offsetValue === 0)) {
                        throw new MissingValuesException($missingValues, $data);
                    }
                }
            }

            return CollectionUtils::sortByIndex($data, $rootIdentifier, $values);
        }

        return $data;
    }

    #[Override]
    public function getResult(): array|ReadResponse
    {
        $data = $this->data();

        if ($this->isValue()) {
            return $data;
        }

        $page  = $this->isPaginated() ? ($this->paginator()?->getCurrentPage() ?? 1) : 1;
        $total = $this->totalCount();

        /** @phpstan-var ReadResponse<T> $result */
        $result = ReadResponse::create($data, $page, $total);

        return $result;
    }

    protected function getWrappedQueryExpression(): QueryExpression|null
    {
        if (count($this->queryExpressions()) === 0) {
            return null;
        }

        if (count($this->queryExpressions()) === 1) {
            return clone $this->queryExpressions()[0];
        }

        $base = QueryExpression::create();
        foreach ($this->queryExpressions() as $item) {
            $base = $base->wrap($item);
        }

        return $base;
    }

    private function assertNoSpecifications(): void
    {
        if (count($this->specifications()) > 0) {
            throw new LogicException('Cannot use this method when specifications are set. Use getIterator() or data() instead.');
        }
    }
}
