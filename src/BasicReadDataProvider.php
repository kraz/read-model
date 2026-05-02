<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use InvalidArgumentException;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Override;
use ReflectionClassConstant;
use ReflectionObject;

use function array_any;
use function array_filter;
use function array_flip;
use function array_intersect_key;
use function array_unshift;
use function array_values;
use function base64_decode;
use function count;
use function is_array;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

/** @phpstan-template-covariant T of object|array<string, mixed> */
trait BasicReadDataProvider
{
    /** @phpstan-return static<T> */
    abstract public function withQueryExpression(QueryExpression $queryExpression, bool $append = false): static;

    /** @phpstan-return static<T> */
    abstract public function withPagination(int $page, int $itemsPerPage): static;

    /** @phpstan-return static<T> */
    abstract public function withoutPagination(): static;

    #[Override]
    public function qry(): QueryExpression
    {
        return QueryExpression::create();
    }

    #[Override]
    public function expr(): FilterExpression
    {
        return FilterExpression::create();
    }

    #[Override]
    public function isValue(): bool
    {
        return array_any($this->queryExpressions(), static fn (QueryExpression $queryExpression) => count($queryExpression->getValues() ?? []) > 0);
    }

    /** @phpstan-return static<T> */
    #[Override]
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        // Load query

        $query     = null;
        $queryBase = $input['query'] ?? null;
        if ($queryBase !== null) {
            $queryJson = base64_decode($queryBase, true);
            if ($queryJson === false) {
                throw new InvalidArgumentException('Invalid query expression parameter!');
            }

            if ($queryJson === '') {
                throw new InvalidArgumentException('The query expression is empty!');
            }

            $query = QueryExpression::create($queryJson);
        }

        // Load filters

        $filters = [];
        $ref     = new ReflectionObject($this);
        $fields  = $ref->getConstants(ReflectionClassConstant::IS_PUBLIC);
        $fields  = array_filter($fields, static fn ($c) => str_starts_with($c, 'FIELD_'), ARRAY_FILTER_USE_KEY);
        $fields  = array_values($fields);
        foreach ($fields as $field) {
            $value = $input[$field] ?? null;
            if ($value === null) {
                continue;
            }

            $operator   = $fieldsOperator[$field] ?? FilterExpression::OP_EQ;
            $ignoreCase = $fieldsIgnoreCase[$field] ?? true;
            $filters[]  = FilterExpression::create()->valX($field, $operator, $value, $ignoreCase);
        }

        // Load values

        $value  = $input['value'] ?? null;
        $values = $input['values'] ?? [];
        $values = is_array($values) ? $values : [];
        if ($value !== null) {
            array_unshift($values, $value);
        }

        if (count($values) > 0) {
            $query ??= QueryExpression::create();
            /** @phpstan-ignore argument.type */
            $query = $query->withValues($values);
        }

        // Load order by

        $sort = $input['order'] ?? [];
        $sort = is_array($sort) ? $sort : [];
        $sort = array_intersect_key($sort, array_flip($fields));

        // Load pagination

        $page     = (int) ($input['page'] ?? 0);
        $pageSize = (int) ($input['pageSize'] ?? 0);

        // Apply

        if (count($filters) > 0) {
            $query ??= QueryExpression::create();
            $query   = $query->andWhere(...$filters);
        }

        foreach ($sort as $field => $dir) {
            $query ??= QueryExpression::create();
            $query   = $query->sortBy($field, $dir);
        }

        /** @phpstan-var static<T> $clone */
        $clone = clone $this;

        if ($page > 0 && $pageSize > 0) {
            $clone = $clone->withPagination($page, $pageSize);
        } else {
            $clone = $clone->withoutPagination();
        }

        return $query !== null ? $clone->withQueryExpression($query) : $clone;
    }
}
