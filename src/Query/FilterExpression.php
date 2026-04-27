<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use RuntimeException;
use Stringable;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_replace_recursive;
use function array_values;
use function call_user_func;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function mb_strtoupper;
use function method_exists;
use function sprintf;
use function str_replace;
use function strtolower;

/**
 * @phpstan-type FilterItem = array{
 *     field?: string,
 *     operator?: string,
 *     value?: mixed,
 *     ignoreCase?: bool,
 *     grouping?: bool,
 *     not?: bool,
 * }
 * @phpstan-type FilterLogicItem = array{
 *     logic?: string,
 *     not?: bool,
 *     filters?: array<array-key, array<string, mixed>|FilterExpression|string>,
 * }
 * @phpstan-type FilterLogicItemArrayItems = array{
 *     logic?: string,
 *     not?: bool,
 *     filters?: array<array-key, array<string, mixed>>,
 * }
 * @phpstan-type FilterComposite = FilterLogicItem|FilterItem
 * @phpstan-type FilterCompositeArrayItems = FilterLogicItemArrayItems|FilterItem
 * @phpstan-type FilterOperatorDescription = array{
 *     name: string,
 *     operator: string,
 *     value_required: bool,
 *     expression: string,
 * }
 */
final class FilterExpression implements JsonSerializable, Stringable
{
    private const string LOGIC_AND = 'and';
    private const string LOGIC_OR  = 'or';

    public const string OP_EQ                  = 'eq'; // (equal to)
    public const string OP_NEQ                 = 'neq'; // (not equal to)
    public const string OP_IS_NULL             = 'isnull'; // (is equal to null)
    public const string OP_IS_NOT_NULL         = 'isnotnull'; // (is not equal to null)
    public const string OP_LT                  = 'lt'; // (less than)
    public const string OP_LTE                 = 'lte'; // (less than or equal to)
    public const string OP_GT                  = 'gt'; // (greater than)
    public const string OP_GTE                 = 'gte'; // (greater than or equal to)
    public const string OP_STARTS_WITH         = 'startswith';
    public const string OP_DOES_NOT_START_WITH = 'doesnotstartwith';
    public const string OP_ENDS_WITH           = 'endswith';
    public const string OP_DOES_NOT_END_WITH   = 'doesnotendwith';
    public const string OP_CONTAINS            = 'contains';
    public const string OP_DOES_NOT_CONTAIN    = 'doesnotcontain';
    public const string OP_IS_EMPTY            = 'isempty';
    public const string OP_IS_NOT_EMPTY        = 'isnotempty';
    public const string OP_IN_LIST             = 'inlist';
    public const string OP_NOT_IN_LIST         = 'notinlist';

    /** @phpstan-param FilterComposite|array<never, never> $filter */
    private function __construct(
        private array $filter = [],
    ) {
    }

    public function logicX(string $logic, self ...$x): self
    {
        $field = $this->field();
        if ($field !== null) {
            throw new LogicException(sprintf('You can not use logical "%s" expression on field "%s" function. Please use logical composition expression first.', $logic, $field));
        }

        $clone                      = clone $this;
        $clone->filter['logic']   ??= self::LOGIC_AND;
        $clone->filter['filters'] ??= [];
        $filters                    = $clone->filter['filters'];
        $inv                        = $logic === self::LOGIC_AND ? self::LOGIC_OR : self::LOGIC_AND;
        if ($clone->filter['logic'] === $inv && count($filters) === 0) {
            $clone->filter = ['logic' => $logic, 'filters' => []];
        }

        if ($clone->filter['logic'] === $logic) {
            $clone->filter['filters'] = array_merge($clone->filter['filters'], $x);
        } else {
            $clone->filter['filters'][] = [
                'logic' => $logic,
                'filters' => $x,
            ];
        }

        return $clone;
    }

    /** @phpstan-ignore missingType.iterableValue */
    public function valX(string $field, string $operator, string|int|float|array|null $value, bool $ignoreCase = true): self
    {
        $clone = clone $this;
        if (($value === null || $value === '' || (is_array($value) && count($value) === 0)) && self::operatorRequiresValue($operator)) {
            return $clone;
        }

        $filter = ['field' => $field, 'operator' => $operator];
        if ($value !== null && $value !== '' && (! is_array($value) || count($value) > 0)) {
            $filter['value'] = $value;
        }

        if (! $ignoreCase) {
            $filter['ignoreCase'] = false;
        }

        if ($this->logic() !== null) {
            $clone->filter['filters'][] = $filter;
        } else {
            $clone->filter = $filter;
        }

        return $clone;
    }

    public function not(self $restriction): self
    {
        $clone                  = clone $restriction;
        $clone->filter['not'] ??= false;
        $clone->filter['not']   = ! $clone->filter['not'];
        if ($clone->filter['not'] === false) {
            unset($clone->filter['not']);
        }

        return $clone;
    }

    public function inverted(): bool
    {
        return $this->filter['not'] ?? false;
    }

    public function logic(): string|null
    {
        return $this->filter['logic'] ?? null;
    }

    /** @return array<array-key, FilterExpression> */
    public function filters(): array
    {
        return array_values(array_filter(array_map(static function ($filter) {
            if (is_array($filter) || is_string($filter)) {
                $filter = static::create($filter);
            }

            if (! $filter instanceof FilterExpression) {
                $filter = null;
            }

            return $filter;
        }, $this->filter['filters'] ?? [])));
    }

    public function field(): string|null
    {
        return $this->filter['field'] ?? null;
    }

    public function operator(): string|null
    {
        return $this->filter['operator'] ?? null;
    }

    public function value(): mixed
    {
        return $this->filter['value'] ?? null;
    }

    public function ignoreCase(): bool
    {
        return $this->filter['ignoreCase'] ?? true;
    }

    public function useLogicGrouping(bool $value): self
    {
        $this->filter['grouping'] = $value;

        return $this;
    }

    public function isUsingLogicGrouping(): bool
    {
        return $this->filter['grouping'] ?? true;
    }

    public function isFilterEmpty(): bool
    {
        if (count($this->filter) === 0 || $this->field() === null || $this->operator() === null) {
            return count($this->filters()) === 0;
        }

        return false;
    }

    public function expression(): string|null
    {
        $operator = $this->operator();
        if ($operator === null) {
            return null;
        }

        $description = static::getOperatorsDescription()[$operator] ?? null;
        if ($description === null) {
            return null;
        }

        $expression = $description['expression'] ?? null;
        if ($expression === null) {
            return null;
        }

        $value = $this->value() ?? '';
        $value = $this->ignoreCase() ? mb_strtoupper((string) $value) : $value;

        return str_replace('%value%', (string) $value, $expression);
    }

    public function fieldExpression(string $field): string|null
    {
        return $this->fieldExpressionAt($field, 0);
    }

    private function fieldExpressionAt(string $field, int $level): string|null
    {
        if ($field === $this->field()) {
            return $this->expression();
        }

        $children = array_filter(array_map(static fn (FilterExpression $filter) => $filter->fieldExpressionAt($field, $level + 1), $this->filters()));
        $result   = implode(count($children) > 1 ? ' ' . $this->logic() . ' ' : '', $children);

        return $level > 0 && count($children) > 1 ? '(' . $result . ')' : $result;
    }

    /** @return array<array-key, FilterItem> */
    public function fieldFilters(string $field): array
    {
        $result = [];
        if ($field === ($this->filter['field'] ?? null)) {
            /** @phpstan-var FilterItem $item */
            $item     = $this->filter;
            $result[] = $item;
        }

        foreach ($this->filters() as $filter) {
            $result = array_merge($result, $filter->fieldFilters($field));
        }

        return $result;
    }

    private function removeFiltersForField(string $field): self
    {
        $clone = clone $this;
        if ($field === $clone->field()) {
            $clone->filter = [];
        }

        if (count($clone->filter['filters'] ?? []) > 0) {
            $clone->filter['filters'] = array_map(static fn (FilterExpression $filter) => $filter->removeFiltersForField($field), $clone->filters());
        }

        return $clone->compact();
    }

    public function compact(): self
    {
        $clone = clone $this;
        if (count($clone->filter['filters'] ?? []) > 0) {
            $clone->filter['filters'] = array_map(static fn (FilterExpression $filter) => $filter->compact(), $clone->filters());
            $clone->filter['filters'] = array_values(array_filter(array_map(static fn (FilterExpression $filter) => $filter->isFilterEmpty() ? null : $filter, $clone->filters())));
            if (count($clone->filter['filters']) === 0) {
                unset($clone->filter['filters']);
            }
        }

        if (count($clone->filter['filters'] ?? []) === 0) {
            if ($clone->isFilterEmpty()) {
                $clone->filter = [];
            }
        }

        return $clone;
    }

    public function reset(string|null $field = null): self
    {
        $clone = clone $this;
        if ($field !== null) {
            $clone = $clone->removeFiltersForField($field);
        } else {
            $clone->filter = [];
        }

        return $clone;
    }

    public function andX(self ...$x): self
    {
        return $this->logicX(self::LOGIC_AND, ...$x);
    }

    public function orX(self ...$x): self
    {
        return $this->logicX(self::LOGIC_OR, ...$x);
    }

    public function equalTo(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_EQ, $value, $ignoreCase);
    }

    public function notEqualTo(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_NEQ, $value, $ignoreCase);
    }

    public function lowerThan(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_LT, $value, $ignoreCase);
    }

    public function lowerThanOrEqual(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_LTE, $value, $ignoreCase);
    }

    public function greaterThan(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_GT, $value, $ignoreCase);
    }

    public function greaterThanOrEqual(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_GTE, $value, $ignoreCase);
    }

    public function startsWith(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_STARTS_WITH, $value, $ignoreCase);
    }

    public function doesNotStartWith(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_DOES_NOT_START_WITH, $value, $ignoreCase);
    }

    public function endsWith(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_ENDS_WITH, $value, $ignoreCase);
    }

    public function doesNotEndWith(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_DOES_NOT_END_WITH, $value, $ignoreCase);
    }

    public function contains(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_CONTAINS, $value, $ignoreCase);
    }

    public function doesNotContain(string $field, string|int|float|null $value, bool $ignoreCase = true): self
    {
        return $this->valX($field, self::OP_DOES_NOT_CONTAIN, $value, $ignoreCase);
    }

    public function isNull(string $field): self
    {
        return $this->valX($field, self::OP_IS_NULL, null);
    }

    public function isNotNull(string $field): self
    {
        return $this->valX($field, self::OP_IS_NOT_NULL, null);
    }

    public function isEmpty(string $field): self
    {
        return $this->valX($field, self::OP_IS_EMPTY, null);
    }

    public function isNotEmpty(string $field): self
    {
        return $this->valX($field, self::OP_IS_NOT_EMPTY, null);
    }

    /** @phpstan-param scalar[] $list */
    public function inList(string $field, array $list): self
    {
        return $this->valX($field, self::OP_IN_LIST, $list);
    }

    /** @phpstan-param scalar[] $list */
    public function notInList(string $field, array $list): self
    {
        return $this->valX($field, self::OP_NOT_IN_LIST, $list);
    }

    /** @return FilterCompositeArrayItems */
    public function toArray(): array
    {
        $filter = $this->filter;
        if (count($filter['filters'] ?? []) > 0) {
            $filter['filters'] = array_map(static fn (FilterExpression $f) => $f->toArray(), $this->filters());
        }

        return $filter;
    }

    public function __toString(): string
    {
        if ($this->isFilterEmpty()) {
            return '';
        }

        $json = json_encode($this->filter);

        return $json === false ? '' : $json;
    }

    /** @return array{filter: FilterCompositeArrayItems} */
    public function __serialize(): array
    {
        return ['filter' => $this->toArray()];
    }

    /** @phpstan-param array{filter?: FilterComposite|array<never, never>} $data */
    public function __unserialize(array $data): void
    {
        $this->filter = $data['filter'] ?? [];
    }

    public function __clone()
    {
        if (! (count($this->filter['filters'] ?? []) > 0)) {
            return;
        }

        $this->filter['filters'] = array_map(static fn (FilterExpression $filter) => $filter->toArray(), $this->filters());
        $this->filter['filters'] = $this->filters();
    }

    /** @phpstan-return FilterCompositeArrayItems|null */
    public function jsonSerialize(): array|null
    {
        $items = array_filter($this->toArray());

        return count($items) > 0 ? $items : null;
    }

    public static function operatorRequiresValue(string $operator): bool
    {
        return static::getOperatorsDescription()[$operator]['value_required'] ?? false;
    }

    /** @return array<string, FilterOperatorDescription> */
    public static function getOperatorsDescription(): array
    {
        return [
            self::OP_EQ => [
                'name' => 'Is equal to',
                'operator' => self::OP_EQ,
                'value_required' => true,
                'expression' => '=%value%',
            ],
            self::OP_NEQ => [
                'name' => 'Is not equal to',
                'operator' => self::OP_NEQ,
                'value_required' => true,
                'expression' => '!=%value%',
            ],
            self::OP_IS_NULL => [
                'name' => 'Is null',
                'operator' => self::OP_IS_NULL,
                'value_required' => false,
                'expression' => 'IS NULL',
            ],
            self::OP_IS_NOT_NULL => [
                'name' => 'Is not null',
                'operator' => self::OP_IS_NOT_NULL,
                'value_required' => false,
                'expression' => 'IS NOT NULL',
            ],
            self::OP_LT => [
                'name' => 'Is lower than',
                'operator' => self::OP_LT,
                'value_required' => true,
                'expression' => '<%value%',
            ],
            self::OP_LTE => [
                'name' => 'Is lower then or equal',
                'operator' => self::OP_LTE,
                'value_required' => true,
                'expression' => '<=%value%',
            ],
            self::OP_GT => [
                'name' => 'Is greater than',
                'operator' => self::OP_GT,
                'value_required' => true,
                'expression' => '>%value%',
            ],
            self::OP_GTE => [
                'name' => 'Is greater than or equal',
                'operator' => self::OP_GTE,
                'value_required' => true,
                'expression' => '>=%value%',
            ],
            self::OP_STARTS_WITH => [
                'name' => 'Is starting with',
                'operator' => self::OP_STARTS_WITH,
                'value_required' => true,
                'expression' => '^%value%',
            ],
            self::OP_DOES_NOT_START_WITH => [
                'name' => 'Is not starting with',
                'operator' => self::OP_DOES_NOT_START_WITH,
                'value_required' => true,
                'expression' => '!^%value%',
            ],
            self::OP_ENDS_WITH => [
                'name' => 'Is ending with',
                'operator' => self::OP_ENDS_WITH,
                'value_required' => true,
                'expression' => '%value%$',
            ],
            self::OP_DOES_NOT_END_WITH => [
                'name' => 'Is not ending with',
                'operator' => self::OP_DOES_NOT_END_WITH,
                'value_required' => true,
                'expression' => '%value%!$',
            ],
            self::OP_CONTAINS => [
                'name' => 'Contains',
                'operator' => self::OP_CONTAINS,
                'value_required' => true,
                'expression' => '%value%',
            ],
            self::OP_DOES_NOT_CONTAIN => [
                'name' => 'Does not contain',
                'operator' => self::OP_DOES_NOT_CONTAIN,
                'value_required' => true,
                'expression' => '!%value%',
            ],
            self::OP_IS_EMPTY => [
                'name' => 'Is empty',
                'operator' => self::OP_IS_EMPTY,
                'value_required' => false,
                'expression' => 'IS EMPTY',
            ],
            self::OP_IS_NOT_EMPTY => [
                'name' => 'Is not empty',
                'operator' => self::OP_IS_NOT_EMPTY,
                'value_required' => false,
                'expression' => 'IS NOT EMPTY',
            ],
            self::OP_IN_LIST => [
                'name' => 'Is in list',
                'operator' => self::OP_IN_LIST,
                'value_required' => true,
                'expression' => 'IN(%value%)',
            ],
            self::OP_NOT_IN_LIST => [
                'name' => 'Is not in list',
                'operator' => self::OP_NOT_IN_LIST,
                'value_required' => true,
                'expression' => '!IN(%value%)',
            ],
        ];
    }

    /**
     * @phpstan-param array<string, mixed>  $filter
     * @phpstan-param array<string, string> $mapping
     *
     * @return array<string, mixed>
     */
    public static function applyFieldMapping(array $filter, array $mapping): array
    {
        $field = $filter['field'] ?? null;
        if ($field !== null && array_key_exists($field, $mapping)) {
            $filter['field'] = $mapping[$field];
        }

        if (count($filter['filters'] ?? []) > 0) {
            $filter['filters'] = array_map(static fn (array $item) => self::applyFieldMapping($item, $mapping), $filter['filters']);
        }

        return $filter;
    }

    /**
     * @phpstan-param array<string, mixed> $filter
     *
     * @return array<string, mixed>
     */
    public static function walkFieldValues(array $filter, callable $callback): array
    {
        $field = $filter['field'] ?? null;
        if ($field !== null && isset($filter['value'])) {
            $val = call_user_func($callback, $filter['field'], $filter['value']);
            if ($val !== null && $val !== $filter['value']) {
                $filter['value'] = $val;
            }
        }

        if (count($filter['filters'] ?? []) > 0) {
            $filter['filters'] = array_map(static fn (array $item) => self::walkFieldValues($item, $callback), $filter['filters']);
        }

        return $filter;
    }

    /**
     * @phpstan-param FilterExpression|FilterCompositeArrayItems $filter
     * @phpstan-param array<string, mixed> $params
     * @phpstan-param array<string, mixed> $options
     */
    public static function normalize(object $expr, FilterExpression|array $filter, array &$params, callable $normalizer, array $options, bool $useFilterGroups = true): mixed
    {
        if (! method_exists($expr, 'andX')) {
            throw new InvalidArgumentException('Expected the method "andX" to exist.');
        }

        if (! method_exists($expr, 'orX')) {
            throw new InvalidArgumentException('Expected the method "orX" to exist.');
        }

        if (! method_exists($expr, 'not')) {
            throw new InvalidArgumentException('Expected the method "not" to exist.');
        }

        if ($filter instanceof FilterExpression) {
            $filter = $filter->toArray();
        }

        $logic      = $filter['logic'] ?? 'and';
        $isInverted = (bool) ($filter['not'] ?? false);

        $whereItems = [];

        $filters = isset($filter['filters']) && is_array($filter['filters']) ? $filter['filters'] : [];

        if (isset($filter['field'])) {
            $filterGroups = [];
            if (! isset($filter['grouping']) || in_array(mb_strtoupper((string) $filter['grouping']), ['1', 'TRUE'], true)) {
                // Determine if the filter field is part of a filter group
                if (array_key_exists($filter['field'], $options['groups'] ?? [])) {
                    $filterGroups = [$options['groups'][$filter['field']]];
                } else {
                    $filterGroups = array_filter($options['groups'] ?? [], static function ($group) use ($filter) {
                        return is_array($group)
                            && isset($group['fields'])
                            && is_array($group['fields'])
                            && in_array($filter['field'], $group['fields'], true);
                    });
                }
            }

            if ($useFilterGroups && count($filterGroups) > 0) {
                // The field is part of filter group, so modify the current filter with new one
                $newFilter = [
                    'logic' => 'or', // When field is part of more than one group - any matching group must return result
                    'filters' => [],
                ];
                foreach ($filterGroups as $group) {
                    $group = array_replace_recursive([
                        'logic' => 'or',    // default logic operator used for the group
                        'fields' => [],      // group fields
                        'filter' => $filter, // default filter config (initially - original as is)
                        'filters' => [],      // filter config by field (overrides default filter config)
                    ], $group);
                    if (count($group['fields']) < 1) {
                        continue;
                    }

                    $groupFilter = ['logic' => $group['logic'], 'filters' => []];
                    foreach ($group['fields'] as $groupField) {
                        $groupFilter['filters'][] = array_replace_recursive(
                            $group['filter'],
                            array_key_exists($groupField, $group['filters']) ? $group['filters'][$groupField] : [],
                            ['field' => $groupField],
                        );
                    }

                    $newFilter['filters'][] = $groupFilter;
                }

                if (count($newFilter['filters']) > 0) {
                    $useFilterGroups = false;
                    // Keep current filters constraints
                    $filters      = [
                        [
                            'logic' => 'and',
                            'filters' => [
                                $newFilter,
                                ['logic' => $logic, 'filters' => $filters],
                            ],
                        ],
                    ];
                    $whereItems[] = $expr->andX();
                }
            } else {
                $whereItems[] = $normalizer($expr, $filter, $params);
            }
        }

        foreach ($filters as $nestedFilter) {
            if ($nestedFilter instanceof FilterExpression) {
                $nestedFilter = $nestedFilter->toArray();
            }

            $nestedExpr = self::normalize($expr, $nestedFilter, $params, $normalizer, $options, $useFilterGroups);
            if (! $nestedExpr) {
                continue;
            }

            $whereItems[] = $nestedExpr;
        }

        if (count($whereItems) === 0) {
            return null;
        }

        $where = match (strtolower($logic)) {
            'and' => $expr->andX(...$whereItems),
            'or' => $expr->orX(...$whereItems),
            default => throw new RuntimeException(sprintf('Unsupported filter logic: "%s"', $logic)),
        };

        if ($isInverted) {
            $where = $expr->not($where);
        }

        return $where;
    }

    /** @phpstan-param FilterComposite|array<never, never>|string $filter */
    public static function create(string|array $filter = []): self
    {
        if (is_string($filter)) {
            /** @phpstan-var FilterComposite|array<never, never>|false|null $data */
            $data = json_decode($filter, true);
            if ($data !== null && ! is_array($data)) {
                throw new InvalidArgumentException('Expected null or an array.');
            }

            $filter = $data;
        }

        return new self(is_array($filter) ? $filter : []);
    }
}
