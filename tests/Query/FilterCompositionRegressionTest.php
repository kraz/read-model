<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Collections\Criteria;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionHelper;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Tests\Query\Fixtures\WarehouseNodeFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_column;
use function json_encode;

/**
 * Regression coverage for a bug reported from a live grid.
 *
 * A grid mixed a text filter (emitted as a "query" expression) with a select filter (emitted as a
 * flat request parameter). {@see QueryRequest::assemble()} folds the flat parameters into the query
 * expression with {@see QueryExpression::andWhere()}, but that method used to compose the new
 * restrictions on top of a *fresh* filter, silently dropping the filters the query expression
 * already carried. The grid therefore returned every row instead of the constrained subset.
 *
 * The same report noted that boolean filter values are rejected outright, which forced the client
 * to stringify them before folding them into the expression.
 */
#[CoversClass(QueryExpression::class)]
#[CoversClass(QueryRequest::class)]
#[CoversClass(FilterExpression::class)]
final class FilterCompositionRegressionTest extends TestCase
{
    /** @return ArrayCollection<int, WarehouseNodeFixture> */
    private function nodes(): ArrayCollection
    {
        return new ArrayCollection([
            new WarehouseNodeFixture(id: 1, name: 'Aisle Alpha', warehouseId: 'wh-1', parentId: null, active: true),
            new WarehouseNodeFixture(id: 2, name: 'Aisle Bravo', warehouseId: 'wh-1', parentId: 1, active: true),
            new WarehouseNodeFixture(id: 3, name: 'Aisle Charlie', warehouseId: 'wh-2', parentId: null, active: false),
            new WarehouseNodeFixture(id: 4, name: 'Aisle Delta', warehouseId: 'wh-2', parentId: 3, active: true),
        ]);
    }

    /**
     * @phpstan-param ArrayCollection<int, WarehouseNodeFixture> $data
     *
     * @return list<int>
     */
    private function applyAndGetIds(ArrayCollection $data, QueryExpression $query, int $includeData = QueryExpressionProviderInterface::INCLUDE_DATA_ALL): array
    {
        $ids = [];
        foreach (QueryExpressionHelper::create($data)->apply($query, $includeData)->matching(Criteria::create()) as $node) {
            $ids[] = $node->id;
        }

        return $ids;
    }

    /** The "parentId IS NULL" restriction carried by the query expression must survive. */
    public function testAssembleKeepsQueryExpressionFiltersWhenFlatFiltersArePresent(): void
    {
        $request = QueryRequest::assemble(
            [
                'query' => json_encode([
                    'filter' => [
                        'logic' => 'and',
                        'filters' => [['field' => 'parentId', 'operator' => FilterExpression::OP_IS_NULL]],
                    ],
                ]),
                'warehouseId' => 'wh-1',
            ],
            ['warehouseId', 'parentId'],
        );

        $query = $request->getQuery();
        self::assertNotNull($query);

        self::assertCount(1, $query->fieldFilters('parentId'));
        self::assertCount(1, $query->fieldFilters('warehouseId'));

        self::assertSame([1], $this->applyAndGetIds($this->nodes(), $query));
    }

    /** The same scenario, but with the query expression holding a single, non composed filter. */
    public function testAssembleKeepsSingleQueryExpressionFilterWhenFlatFiltersArePresent(): void
    {
        $request = QueryRequest::assemble(
            [
                'query' => json_encode(['filter' => ['field' => 'name', 'operator' => FilterExpression::OP_CONTAINS, 'value' => 'Aisle']]),
                'warehouseId' => 'wh-2',
            ],
            ['warehouseId'],
        );

        $query = $request->getQuery();
        self::assertNotNull($query);

        self::assertCount(1, $query->fieldFilters('name'));
        self::assertCount(1, $query->fieldFilters('warehouseId'));

        self::assertSame([3, 4], $this->applyAndGetIds($this->nodes(), $query));
    }

    /** An "or" composed query expression must be kept as a group instead of being flattened away. */
    public function testAssembleWrapsOrComposedQueryExpressionAsAGroup(): void
    {
        $request = QueryRequest::assemble(
            [
                'query' => json_encode([
                    'filter' => [
                        'logic' => 'or',
                        'filters' => [
                            ['field' => 'name', 'operator' => FilterExpression::OP_CONTAINS, 'value' => 'Alpha'],
                            ['field' => 'name', 'operator' => FilterExpression::OP_CONTAINS, 'value' => 'Charlie'],
                        ],
                    ],
                ]),
                'warehouseId' => 'wh-1',
            ],
            ['warehouseId'],
        );

        $query = $request->getQuery();
        self::assertNotNull($query);

        // "wh-1" AND (name contains "Alpha" OR name contains "Charlie") - "Aisle Charlie" belongs to "wh-2".
        self::assertSame([1], $this->applyAndGetIds($this->nodes(), $query));
    }

    /** The sort and the values of the query expression must not be affected by the merge either. */
    public function testAssembleKeepsQueryExpressionSortAndValuesWhenFlatFiltersArePresent(): void
    {
        $request = QueryRequest::assemble(
            [
                'query' => json_encode([
                    'filter' => ['field' => 'parentId', 'operator' => FilterExpression::OP_IS_NOT_NULL],
                    'sort' => [['field' => 'name', 'dir' => 'desc']],
                    'values' => ['wh-1'],
                ]),
                'warehouseId' => 'wh-1',
            ],
            ['warehouseId'],
        );

        $query = $request->getQuery();
        self::assertNotNull($query);

        self::assertSame('desc', $query->sortDir('name'));
        self::assertSame(['wh-1'], $query->getValues());
        self::assertSame([2], $this->applyAndGetIds(
            $this->nodes(),
            $query,
            QueryExpressionProviderInterface::INCLUDE_DATA_FILTER | QueryExpressionProviderInterface::INCLUDE_DATA_SORT,
        ));
    }

    public function testAndWhereAccumulatesRestrictions(): void
    {
        $query = QueryExpression::create()
            ->andWhere(FilterExpression::create()->equalTo('warehouseId', 'wh-1'))
            ->andWhere(FilterExpression::create()->isNull('parentId'));

        self::assertCount(1, $query->fieldFilters('warehouseId'));
        self::assertCount(1, $query->fieldFilters('parentId'));
        self::assertSame([1], $this->applyAndGetIds($this->nodes(), $query));
    }

    public function testAndWhereKeepsAnExistingOrCompositionGrouped(): void
    {
        $query = QueryExpression::create()
            ->orWhere(
                FilterExpression::create()->equalTo('name', 'Aisle Alpha'),
                FilterExpression::create()->equalTo('name', 'Aisle Charlie'),
            )
            ->andWhere(FilterExpression::create()->equalTo('warehouseId', 'wh-1'));

        self::assertSame([1], $this->applyAndGetIds($this->nodes(), $query));
    }

    public function testOrWhereKeepsAnExistingAndCompositionGrouped(): void
    {
        $query = QueryExpression::create()
            ->andWhere(
                FilterExpression::create()->equalTo('warehouseId', 'wh-2'),
                FilterExpression::create()->isNull('parentId'),
            )
            ->orWhere(FilterExpression::create()->equalTo('name', 'Aisle Alpha'));

        // ("wh-2" AND parentId IS NULL) OR name = "Aisle Alpha"
        self::assertSame([1, 3], $this->applyAndGetIds($this->nodes(), $query));
    }

    public function testAndWhereOnAnEmptyExpressionStillComposesFromScratch(): void
    {
        $query = QueryExpression::create()->andWhere(
            FilterExpression::create()->equalTo('warehouseId', 'wh-1'),
            FilterExpression::create()->isNull('parentId'),
        );

        $filter = $query->getFilter();
        self::assertNotNull($filter);
        self::assertSame(FilterExpression::LOGIC_AND, $filter->logic());
        self::assertCount(2, $filter->filters());
    }

    public function testBooleanFilterValueIsAccepted(): void
    {
        $query = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('active', false));

        self::assertSame([3], $this->applyAndGetIds($this->nodes(), $query));
    }

    public function testAssembleAcceptsBooleanFlatFilterValue(): void
    {
        $request = QueryRequest::assemble(['active' => true], ['active']);

        $query = $request->getQuery();
        self::assertNotNull($query);
        self::assertSame([true], array_column($query->fieldFilters('active'), 'value'));
        self::assertSame([1, 2, 4], $this->applyAndGetIds($this->nodes(), $query));
    }

    public function testBooleanFilterValueSurvivesSerialization(): void
    {
        $filter = FilterExpression::create()->equalTo('active', false);

        $decoded = QueryExpression::decode(QueryExpression::create(['filter' => $filter])->encode());

        self::assertSame([false], array_column($decoded->fieldFilters('active'), 'value'));
        self::assertSame([3], $this->applyAndGetIds($this->nodes(), $decoded));
    }
}
