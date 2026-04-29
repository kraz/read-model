<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use InvalidArgumentException;
use JsonSerializable;
use Kraz\ReadModel\ReadDataProviderInterface;
use Stringable;

use function array_column;
use function array_filter;
use function array_map;
use function array_merge;
use function array_push;
use function array_unique;
use function array_values;
use function base64_decode;
use function base64_encode;
use function count;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strtolower;
use function sprintf;

/**
 * @phpstan-import-type FilterItem from FilterExpression
 * @phpstan-import-type FilterComposite from FilterExpression
 * @phpstan-import-type SortItem from SortExpression
 * @phpstan-import-type SortComposite from SortExpression
 * @phpstan-type QueryExpressionComposite = array{
 *     filter?: FilterComposite|array<never, never>|null,
 *     sort?: SortComposite|array<never, never>|null,
 *     values?: list<int|string>|null
 * }
 * @phpstan-type QueryExpressionCompositeFull = array{
 *     filter?: FilterExpression|FilterComposite|array<never, never>|null,
 *     sort?: SortExpression|SortComposite|array<never, never>|null,
 *     values?: list<int|string>|null
 * }
 */
final class QueryExpression implements JsonSerializable, Stringable
{
    private FilterExpression|null $filter = null;
    private SortExpression|null $sort     = null;
    /** @phpstan-var list<int|string>|null */
    private array|null $values = null;

    /**
     * @phpstan-param FilterComposite|null       $filter
     * @phpstan-param SortComposite|null         $sort
     * @phpstan-param list<int|string|null>|null $values
     */
    private function __construct(array|null $filter, array|null $sort, array|null $values)
    {
        if ($filter !== null) {
            $this->filter = FilterExpression::create($filter);
        }

        if ($sort !== null) {
            $this->sort = SortExpression::create($sort);
        }

        if ($values === null) {
            return;
        }

        $values = array_map(static fn ($v) => is_string($v) || is_int($v) ? $v : null, $values);
        /** @phpstan-var list<int|string> $values */
        $values       = array_filter($values, static fn ($v) => $v !== null && $v !== '');
        $this->values = $values;
    }

    public function expr(): FilterExpression
    {
        return FilterExpression::create();
    }

    public function andWhere(FilterExpression ...$expr): self
    {
        $clone         = clone $this;
        $clone->filter = $this->expr()->andX(...$expr);

        return $clone;
    }

    public function orWhere(FilterExpression ...$expr): self
    {
        $clone         = clone $this;
        $clone->filter = $this->expr()->orX(...$expr);

        return $clone;
    }

    public function sortBy(string $field, string $dir = SortExpression::DIR_ASC): self
    {
        $clone         = clone $this;
        $clone->sort ??= SortExpression::create();
        $clone->sort   = match (mb_strtolower($dir)) {
            'asc' => $clone->sort->asc($field),
            'desc' => $clone->sort->desc($field),
            default => throw new InvalidArgumentException(sprintf('Invalid sort direction. Expected "ASC" or "DESC", but got "%s".', $dir)),
        };

        return $clone;
    }

    public function compactFilter(): self
    {
        $clone         = clone $this;
        $clone->filter = $clone->filter?->compact();

        return $clone;
    }

    public function wrapAnd(self ...$queryExpression): self
    {
        $clone = clone $this;
        foreach ($queryExpression as $item) {
            $clone = $clone->wrap($item, FilterExpression::LOGIC_AND);
        }

        return $clone;
    }

    public function wrapOr(self ...$queryExpression): self
    {
        $clone = clone $this;
        foreach ($queryExpression as $item) {
            $clone = $clone->wrap($item, FilterExpression::LOGIC_OR);
        }

        return $clone;
    }

    public function wrap(self $queryExpression, string $logicOperator = FilterExpression::LOGIC_AND): self
    {
        $filter     = [];
        $filterItem = $queryExpression->getFilter();
        if ($filterItem !== null) {
            $filter[] = clone $filterItem;
        }

        if ($this->filter !== null) {
            $filter[] = clone $this->filter;
        }

        $sort     = [];
        $sortItem = $queryExpression->getSort();
        if ($sortItem !== null) {
            $sort = array_merge($sort, $sortItem->items());
        }

        if ($this->sort !== null) {
            $fields = array_column($sort, 'field');
            $items  = array_filter($this->sort->items(), static fn ($f) => ! in_array($f, $fields, true));
            array_push($sort, ...$items);
        }

        $values     = [];
        $valuesItem = $queryExpression->getValues();
        if ($valuesItem !== null) {
            $values = array_values(array_unique(array_merge($values, $valuesItem)));
        }

        $clone = clone $this;

        $clone->filter = null;
        if (count($filter) > 0) {
            $clone->filter = match ($logicOperator) {
                FilterExpression::LOGIC_AND => $this->expr()->andX(...$filter),
                FilterExpression::LOGIC_OR => $this->expr()->orX(...$filter),
                default => throw new InvalidArgumentException(sprintf('Invalid logic operator "%s" for wrapping the query expression.', $logicOperator)),
            };
        }

        $clone->sort = null;
        foreach ($sort as ['field' => $field, 'dir' => $dir]) {
            $clone = $clone->sortBy($field, $dir);
        }

        $clone->values = null;
        if (count($values) > 0) {
            $clone->values = $values;
        }

        return $clone;
    }

    public function invert(): self
    {
        return self::create([
            'filter' => $this->getFilter()?->invert(),
            'sort' => $this->getSort()?->invert(),
            'values' => $this->getValues(),
        ]);
    }

    /** @return FilterItem[] */
    public function fieldFilters(string $field): array
    {
        return $this->filter?->fieldFilters($field) ?? [];
    }

    public function fieldExpression(string $field): string
    {
        return $this->filter?->fieldExpression($field) ?? '';
    }

    public function sortDir(string $field): string|null
    {
        return $this->sort?->dir($field);
    }

    public function sortNum(string $field): int|null
    {
        return $this->sort?->num($field);
    }

    public function sortCount(): int
    {
        return $this->sort?->count() ?? 0;
    }

    public function getFilter(): FilterExpression|null
    {
        return $this->filter;
    }

    public function getSort(): SortExpression|null
    {
        return $this->sort;
    }

    /** @return list<int|string>|null */
    public function getValues(): array|null
    {
        return $this->values;
    }

    /** @phpstan-param list<int|string>|null $values */
    public function withValues(array|null $values): self
    {
        $clone         = clone $this;
        $clone->values = $values;

        return $clone;
    }

    public function isValuesQuery(): bool
    {
        return $this->values !== null && count($this->values) > 0;
    }

    public function resetFilter(string|null $field = null): self
    {
        $clone         = clone $this;
        $clone->filter = $clone->filter?->reset($field);

        return $clone;
    }

    public function resetSort(string|null $field = null): self
    {
        $clone       = clone $this;
        $clone->sort = $clone->sort?->reset($field);

        return $clone;
    }

    public function isEmpty(): bool
    {
        if ($this->filter !== null && ! $this->filter->isFilterEmpty()) {
            return false;
        }

        if ($this->sort !== null && ! $this->sort->isSortEmpty()) {
            return false;
        }

        return $this->values === null || count($this->values) <= 0;
    }

    /**
     * @phpstan-param ReadDataProviderInterface<T> $dataProvider
     *
     * @return ReadDataProviderInterface<T>
     *
     * @phpstan-template T of object
     */
    public function appendTo(ReadDataProviderInterface $dataProvider): ReadDataProviderInterface
    {
        if ($this->isEmpty()) {
            return $dataProvider;
        }

        return $dataProvider->withQueryExpression($this);
    }

    /** @phpstan-return QueryExpressionComposite */
    public function toArray(): array
    {
        return array_filter([
            'filter' => $this->filter !== null && ! $this->filter->isFilterEmpty() ? $this->filter->toArray() : null,
            'sort' => $this->sort !== null && ! $this->sort->isSortEmpty() ? $this->sort->toArray() : null,
            'values' => $this->values,
        ]);
    }

    public function __toString(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $items = $this->toArray();
        if (count($items) === 0) {
            return '';
        }

        $json = json_encode($items);

        return $json === false ? '' : $json;
    }

    /** @phpstan-return QueryExpressionComposite */
    public function __serialize(): array
    {
        return $this->toArray();
    }

    /** @phpstan-param QueryExpressionComposite $data */
    public function __unserialize(array $data): void
    {
        $filter       = $data['filter'] ?? null;
        $this->filter = $filter !== null ? FilterExpression::create($filter) : null;

        $sort       = $data['sort'] ?? null;
        $this->sort = $sort !== null ? SortExpression::create($sort) : null;

        $this->values = $data['values'] ?? null;
    }

    public function __clone()
    {
        if ($this->filter !== null) {
            $this->filter = clone $this->filter;
        }

        if ($this->sort === null) {
            return;
        }

        $this->sort = clone $this->sort;
    }

    /** @phpstan-return QueryExpressionComposite|null */
    public function jsonSerialize(): array|null
    {
        $items = $this->toArray();

        return count($items) > 0 ? $items : null;
    }

    public function encode(): string
    {
        $json = json_encode($this);
        if ($json === false) {
            throw new InvalidArgumentException('Can not encode the query expression. The payload is invalid!');
        }

        return base64_encode($json);
    }

    /**
     * @phpstan-param array<string, mixed>  $expression
     * @phpstan-param array<string, string> $mapping
     *
     * @return array<string, mixed>
     */
    public static function applyFieldMapping(array $expression, array $mapping): array
    {
        if (($expression['filter'] ?? null) !== null) {
            $expression['filter'] = FilterExpression::applyFieldMapping($expression['filter'], $mapping);
        }

        if (($expression['sort'] ?? null) !== null) {
            $expression['sort'] = SortExpression::applyFieldMapping($expression['sort'], $mapping);
        }

        return $expression;
    }

    public static function decode(string $expression): self
    {
        if ($expression === '') {
            throw new InvalidArgumentException('The query decode expression is empty!');
        }

        $exprJson = base64_decode($expression, true);
        if ($exprJson === false) {
            throw new InvalidArgumentException('Invalid query decode expression!');
        }

        if ($exprJson === '') {
            throw new InvalidArgumentException('The decoded query expression is invalid!');
        }

        return self::create($exprJson);
    }

    /** @phpstan-param QueryExpressionCompositeFull|string|null $expression */
    public static function create(string|array|null $expression = null): self
    {
        if (is_string($expression)) {
            /** @phpstan-var QueryExpressionComposite|false|null $data */
            $data = json_decode($expression, true);
            if ($data !== null && ! is_array($data)) {
                throw new InvalidArgumentException('Expected null or an array.');
            }

            $expression = $data;
        }

        $expression ??= [];

        /** @phpstan-var FilterExpression|FilterComposite|null $filter */
        $filter = $expression['filter'] ?? null;
        if ($filter instanceof FilterExpression) {
            $filter = $filter->toArray();
        }

        /** @phpstan-var SortExpression|SortComposite|null $sort */
        $sort = $expression['sort'] ?? null;
        if ($sort instanceof SortExpression) {
            $sort = $sort->toArray();
        }

        /** @phpstan-var list<int|string> $values */
        $values = $expression['values'] ?? null;

        return new self($filter, $sort, $values);
    }
}
