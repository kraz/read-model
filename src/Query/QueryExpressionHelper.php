<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Collections\Criteria;
use Kraz\ReadModel\Collections\Expr\Comparison;
use Kraz\ReadModel\Collections\Expr\CompositeExpression;
use Kraz\ReadModel\Collections\ExpressionBuilder;
use Kraz\ReadModel\Collections\Order;
use Kraz\ReadModel\Collections\Selectable;
use Kraz\ReadModel\ReadModelDescriptor;
use RuntimeException;

use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function mb_strtolower;
use function mb_strtoupper;
use function reset;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * @phpstan-import-type FilterCompositeArrayItems from FilterExpression
 * @phpstan-type QueryExpressionHelperOptions = array{
 *     root_identifier?: string,
 *     root_alias?: string|string[],
 *     read_model_descriptor?: ReadModelDescriptor|string|null,
 *     field_map?: array<string, string>,
 *     expressions?: array<string, array{exp?: mixed}>,
 *     groups?: array<string, array{
 *         logic?: string,
 *         fields?: array<array-key, string>,
 *         filter?: array<string, mixed>,
 *         filters?: array<string, mixed>,
 *     }>
 * }
 * @phpstan-template T
 */
final class QueryExpressionHelper
{
    /** @phpstan-var string[] */
    private static array $operatorsNoValue = [
        'isnull',
        'isnotnull',
        'isempty',
        'isnullorempty',
        'isnotempty',
        'isnotnullorempty',
    ];

    /**
     * @phpstan-param Selectable<array-key, T> $data
     * @phpstan-param QueryExpressionHelperOptions $options
     */
    private function __construct(
        private readonly Selectable $data,
        private ReadModelDescriptor|null $descriptor,
        private readonly array $options = [],
    ) {
        if ($this->descriptor !== null) {
            return;
        }

        $readModelDescriptor = $this->options['read_model_descriptor'] ?? null;
        if (! ($readModelDescriptor instanceof ReadModelDescriptor)) {
            return;
        }

        $this->descriptor = $readModelDescriptor;
    }

    /** @phpstan-return array<string, string> */
    private function getFieldMap(): array
    {
        return $this->options['field_map'] ?? $this->descriptor->fieldMap ?? [];
    }

    /** @return string[] */
    private function getRootAliasList(): array
    {
        $rootAlias = $this->options['root_alias'] ?? null;

        return is_string($rootAlias) ? [$rootAlias] : [];
    }

    private function getRootIdentifier(): string
    {
        $rootIdentifier = $this->options['root_identifier'] ?? null;
        if (is_string($rootIdentifier)) {
            $rootIdentifier = [$rootIdentifier];
        }

        if (! is_array($rootIdentifier)) {
            throw new RuntimeException('Can not determine the root identifier. Did you missed the "root_identifier" option?');
        }

        if (count($rootIdentifier) > 1) {
            throw new RuntimeException('Composite root identifiers are not supported.');
        }

        $rootIdentifier = reset($rootIdentifier);

        if (! is_string($rootIdentifier) || $rootIdentifier === '') {
            throw new InvalidArgumentException('Can not determine the "root_identifier"!');
        }

        if (str_contains($rootIdentifier, '.')) {
            throw new RuntimeException('The "root_identifier" option must not contain "." symbol. Please use "root_alias" to specify the alias of the table which holds the identifier column!');
        }

        return $rootIdentifier;
    }

    /** @phpstan-param FilterExpression|FilterCompositeArrayItems $filter */
    private function createMatchingFilterExpression(ExpressionBuilder $expr, FilterExpression|array $filter): CompositeExpression|Comparison
    {
        if ($filter instanceof FilterExpression) {
            $filter = $filter->toArray();
        }

        if (! isset($filter['field'])) {
            throw new RuntimeException('Missing filter filed');
        }

        $fieldMap = $this->getFieldMap();
        $field    = (string) $filter['field'];
        $field    = $fieldMap[$field] ?? $field;
        foreach ($this->getRootAliasList() as $rootAlias) {
            if (str_starts_with($field, $rootAlias . '.')) {
                $field = substr($field, strlen($rootAlias) + 1);
                break;
            }
        }

        if (! isset($filter['operator'])) {
            throw new RuntimeException('Missing filter operator');
        }

        $operator = mb_strtolower((string) $filter['operator']);

        $ignoreCaseDefault = ! isset($filter['ignoreCase']);
        $ignoreCase        = ! isset($filter['ignoreCase']) || (bool) $filter['ignoreCase'];

        $paramValue = null;

        if (! in_array($operator, self::$operatorsNoValue, true)) {
            if (! isset($filter['value'])) {
                throw new RuntimeException('Missing filter value');
            }

            $paramValue = $filter['value'];
        }

        $fieldEx = $field;
        if (array_key_exists($field, $this->options['expressions'] ?? [])) {
            $fieldEx = $this->options['expressions'][$field]['exp'] ?? $field;
        }

        return match ($operator) {
            'eq' => $expr->eq($fieldEx, $paramValue, $ignoreCaseDefault || ! $ignoreCase),
            'neq' => $expr->neq($fieldEx, $paramValue, $ignoreCaseDefault || ! $ignoreCase),
            'isnull' => $expr->isNull($fieldEx),
            'isnotnull' => $expr->not($expr->isNull($fieldEx)),
            'lt' => $expr->lt($fieldEx, $paramValue),
            'lte' => $expr->lte($fieldEx, $paramValue),
            'gt' => $expr->gt($fieldEx, $paramValue),
            'gte' => $expr->gte($fieldEx, $paramValue),
            'startswith' => $expr->startsWith($fieldEx, $paramValue, ! $ignoreCase),
            'notstartswith', 'doesnotstartwith' => $expr->not($expr->startsWith($fieldEx, $paramValue, ! $ignoreCase)),
            'endswith' => $expr->endsWith($fieldEx, $paramValue, ! $ignoreCase),
            'notendswith', 'doesnotendwith' => $expr->not($expr->endsWith($fieldEx, $paramValue, ! $ignoreCase)),
            'contains' => $expr->contains($fieldEx, $paramValue, ! $ignoreCase),
            'notcontains', 'doesnotcontain' => $expr->not($expr->contains($fieldEx, $paramValue, ! $ignoreCase)),
            'isempty', 'isnullorempty' => $expr->orX($expr->isNull($fieldEx), $expr->eq($fieldEx, '')),
            'isnotempty', 'isnotnullorempty' => $expr->andX($expr->isNotNull($fieldEx), $expr->neq($fieldEx, '')),
            'inlist' => $expr->in($fieldEx, is_string($paramValue) ? array_map('trim', explode(',', $paramValue)) : $paramValue, ! $ignoreCase),
            'notinlist' => $expr->notIn($fieldEx, is_string($paramValue) ? array_map('trim', explode(',', $paramValue)) : $paramValue, ! $ignoreCase),
            default => throw new RuntimeException(sprintf('Unsupported filter operator: "%s"', $operator)),
        };
    }

    /** @phpstan-return Selectable<array-key, T> */
    public function apply(QueryExpression $queryExpression, int $includeData = QueryExpressionProviderInterface::INCLUDE_DATA_ALL): Selectable
    {
        $data               = clone $this->data;
        $criteriaExpression = null;
        $criteriaOrderings  = null;
        $params             = [];
        $includeFilter      = $includeData & QueryExpressionProviderInterface::INCLUDE_DATA_FILTER;
        $includeSort        = $includeData & QueryExpressionProviderInterface::INCLUDE_DATA_SORT;
        $includeValues      = $includeData & QueryExpressionProviderInterface::INCLUDE_DATA_VALUES;

        $filter = $queryExpression->getFilter();
        if ($includeFilter && $filter !== null && ! $filter->isFilterEmpty()) {
            $criteriaExpression = FilterExpression::normalize(new ExpressionBuilder(), $filter, $params, $this->createMatchingFilterExpression(...), $this->options);
        }

        $sort = $queryExpression->getSort();
        if ($includeSort && $sort !== null && ! $sort->isSortEmpty()) {
            foreach ($sort->items() as $entry) {
                $field = $entry['field'] ?? null;
                if ($field === null || $field === '') {
                    throw new RuntimeException('The sort rule must specify a field');
                }

                if (array_key_exists($field, $this->options['expressions'] ?? [])) {
                    $field = $this->options['expressions'][$field]['exp'] ?? $field;
                }

                $dir = $entry['dir'] ?? 'ASC';
                if (! in_array(mb_strtoupper($dir), ['ASC', 'DESC'], true)) {
                    throw new RuntimeException(sprintf('Invalid sort direction: "%s"', $dir));
                }

                $criteriaOrderings       ??= [];
                $criteriaOrderings[$field] = match (mb_strtoupper($dir)) {
                    'ASC' => Order::Ascending,
                    'DESC' => Order::Descending,
                    default => throw new InvalidArgumentException(sprintf('Invalid sorting direction: "%s"', $dir)),
                };
            }
        }

        $values = $queryExpression->getValues();
        if ($includeValues && $values !== null) {
            $field = $this->getRootIdentifier();
            $expr  = new ExpressionBuilder();
            if (count($values) === 1) {
                $valuesExpression = $expr->eq($field, reset($values));
            } else {
                $valuesExpression = $expr->in($field, $values);
            }

            $criteriaExpression = $criteriaExpression !== null ? $expr->andX($criteriaExpression, $valuesExpression) : $valuesExpression;
        }

        $criteria = null;
        if ($criteriaExpression !== null || $criteriaOrderings !== null) {
            $criteria = new Criteria($criteriaExpression, $criteriaOrderings);
        }

        if ($criteria === null) {
            return $data;
        }

        return $data->matching($criteria);
    }

    /**
     * @phpstan-param Selectable<array-key, T> $data
     * @phpstan-param QueryExpressionHelperOptions $options
     *
     * @phpstan-return QueryExpressionHelper<T>
     */
    public static function create(Selectable $data, ReadModelDescriptor|null $descriptor = null, array $options = []): QueryExpressionHelper
    {
        return new QueryExpressionHelper($data, $descriptor, $options);
    }
}
