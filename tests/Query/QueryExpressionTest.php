<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\SortExpression;
use Kraz\ReadModel\ReadDataProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_values;
use function base64_decode;
use function base64_encode;
use function json_decode;
use function json_encode;
use function serialize;
use function unserialize;

#[CoversClass(QueryExpression::class)]
final class QueryExpressionTest extends TestCase
{
    private function filterOf(QueryExpression $qry): FilterExpression
    {
        $filter = $qry->getFilter();
        self::assertNotNull($filter);

        return $filter;
    }

    public function testCreateEmpty(): void
    {
        $qry = QueryExpression::create();

        self::assertTrue($qry->isEmpty());
        self::assertNull($qry->getFilter());
        self::assertNull($qry->getSort());
        self::assertNull($qry->getValues());
        self::assertSame([], $qry->toArray());
        self::assertSame('', (string) $qry);
        self::assertNull($qry->jsonSerialize());
    }

    public function testExprReturnsFreshFilterExpression(): void
    {
        $qry = QueryExpression::create();

        self::assertInstanceOf(FilterExpression::class, $qry->expr());
        self::assertTrue($qry->expr()->isFilterEmpty());
        self::assertNotSame($qry->expr(), $qry->expr());
    }

    public function testCreateFromArray(): void
    {
        $qry = QueryExpression::create([
            'filter' => ['field' => 'a', 'operator' => 'eq', 'value' => 1],
            'sort' => [['field' => 'name', 'dir' => 'asc']],
            'values' => ['x', 'y'],
        ]);

        self::assertFalse($qry->isEmpty());
        self::assertSame('a', $this->filterOf($qry)->field());
        self::assertSame('asc', $qry->sortDir('name'));
        self::assertSame(['x', 'y'], $qry->getValues());
    }

    public function testCreateFromJsonString(): void
    {
        $qry = QueryExpression::create(
            '{"filter":{"field":"a","operator":"eq","value":1},"sort":[{"field":"name","dir":"asc"}]}',
        );

        self::assertSame('a', $this->filterOf($qry)->field());
        self::assertSame('asc', $qry->sortDir('name'));
    }

    public function testCreateFromInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryExpression::create('"not-an-array"');
    }

    public function testCreateAcceptsExpressionInstances(): void
    {
        $filter = FilterExpression::create()->equalTo('a', 1);
        $sort   = SortExpression::create()->asc('name');

        $qry = QueryExpression::create(['filter' => $filter, 'sort' => $sort]);

        self::assertSame('a', $this->filterOf($qry)->field());
        self::assertSame(1, $this->filterOf($qry)->value());
        self::assertSame('asc', $qry->sortDir('name'));
    }

    public function testValuesAreFilteredToScalarStringsAndInts(): void
    {
        $qry = QueryExpression::create(['values' => ['x', '', 'y', 5]]);

        $values = $qry->getValues();
        self::assertSame(['x', 'y', 5], array_values($values ?? []));
    }

    public function testAndWhereSetsAndComposedFilter(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere(
            $qry->expr()->equalTo('a', 1),
            $qry->expr()->equalTo('b', 2),
        );

        $filter = $this->filterOf($qry);
        self::assertSame('and', $filter->logic());
        self::assertCount(2, $filter->filters());
    }

    public function testOrWhereSetsOrComposedFilter(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->orWhere(
            $qry->expr()->equalTo('a', 1),
            $qry->expr()->equalTo('b', 2),
        );

        self::assertSame('or', $this->filterOf($qry)->logic());
    }

    public function testAndWhereOverwritesPreviousFilter(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));
        $qry = $qry->andWhere($qry->expr()->equalTo('b', 2));

        $filters = $this->filterOf($qry)->filters();
        self::assertCount(1, $filters);
        self::assertSame('b', $filters[0]->field());
    }

    public function testSortByAccumulates(): void
    {
        $qry = QueryExpression::create()
            ->sortBy('name', 'asc')
            ->sortBy('age', 'desc');

        self::assertSame(2, $qry->sortCount());
        self::assertSame('asc', $qry->sortDir('name'));
        self::assertSame('desc', $qry->sortDir('age'));
        self::assertSame(1, $qry->sortNum('name'));
        self::assertSame(2, $qry->sortNum('age'));
    }

    public function testSortByIsCaseInsensitive(): void
    {
        $qry = QueryExpression::create()->sortBy('name', 'DESC');

        self::assertSame('desc', $qry->sortDir('name'));
    }

    public function testSortByThrowsOnInvalidDirection(): void
    {
        $qry = QueryExpression::create();

        $this->expectException(InvalidArgumentException::class);
        $qry->sortBy('name', 'sideways');
    }

    public function testSortCountIsZeroWhenNoSort(): void
    {
        self::assertSame(0, QueryExpression::create()->sortCount());
        self::assertNull(QueryExpression::create()->sortDir('name'));
        self::assertNull(QueryExpression::create()->sortNum('name'));
    }

    public function testFieldFiltersDelegatesToFilter(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $filters = $qry->fieldFilters('a');
        self::assertCount(1, $filters);
        self::assertSame([1], array_column($filters, 'value'));

        self::assertSame([], QueryExpression::create()->fieldFilters('a'));
    }

    public function testFieldExpressionDelegatesToFilter(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 5));

        self::assertSame('=5', $qry->fieldExpression('a'));
        self::assertSame('', QueryExpression::create()->fieldExpression('a'));
    }

    public function testCompactFilterRemovesEmptyChildren(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere(
            $qry->expr()->equalTo('a', 1),
            $qry->expr()->equalTo('b', null),
        );

        $compacted = $qry->compactFilter();
        self::assertCount(1, $this->filterOf($compacted)->filters());
    }

    public function testResetFilterClearsAllOrSingleField(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere(
            $qry->expr()->equalTo('a', 1),
            $qry->expr()->equalTo('b', 2),
        );

        $cleared   = $qry->resetFilter('a');
        $remaining = $this->filterOf($cleared)->filters();
        self::assertCount(1, $remaining);
        self::assertSame('b', $remaining[0]->field());

        $allCleared = $qry->resetFilter();
        self::assertTrue($this->filterOf($allCleared)->isFilterEmpty());
    }

    public function testResetSortClearsAllOrSingleField(): void
    {
        $qry = QueryExpression::create()
            ->sortBy('name', 'asc')
            ->sortBy('age', 'desc');

        $cleared = $qry->resetSort('name');
        self::assertSame(1, $cleared->sortCount());
        self::assertSame('desc', $cleared->sortDir('age'));

        $allCleared = $qry->resetSort();
        self::assertSame(0, $allCleared->sortCount());
    }

    public function testWithValuesReplaces(): void
    {
        $qry = QueryExpression::create();

        self::assertFalse($qry->isValuesQuery());
        self::assertNull($qry->getValues());

        $qry = $qry->withValues(['x', 'y']);
        self::assertTrue($qry->isValuesQuery());
        self::assertSame(['x', 'y'], $qry->getValues());

        $cleared = $qry->withValues(null);
        self::assertNull($cleared->getValues());
        self::assertFalse($cleared->isValuesQuery());
    }

    public function testIsValuesQueryFalseForEmptyArray(): void
    {
        $qry = QueryExpression::create()->withValues([]);

        self::assertFalse($qry->isValuesQuery());
    }

    public function testIsEmptyConsidersFilterSortAndValues(): void
    {
        self::assertTrue(QueryExpression::create()->isEmpty());

        $withFilter = QueryExpression::create();
        $withFilter = $withFilter->andWhere($withFilter->expr()->equalTo('a', 1));
        self::assertFalse($withFilter->isEmpty());

        self::assertFalse(QueryExpression::create()->sortBy('name')->isEmpty());
        self::assertFalse(QueryExpression::create()->withValues(['x'])->isEmpty());
    }

    public function testToArrayOmitsEmptyParts(): void
    {
        $qry = QueryExpression::create()->sortBy('name', 'asc');

        $arr = $qry->toArray();
        self::assertArrayNotHasKey('filter', $arr);
        self::assertArrayHasKey('sort', $arr);
        self::assertArrayNotHasKey('values', $arr);
    }

    public function testToStringEncodesAsJson(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $json = (string) $qry;
        self::assertNotSame('', $json);

        $decoded = json_decode($json, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('filter', $decoded);
    }

    public function testJsonSerializeNullWhenEmpty(): void
    {
        self::assertNull(QueryExpression::create()->jsonSerialize());
        self::assertSame('null', json_encode(QueryExpression::create()));
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $qry = QueryExpression::create()
            ->sortBy('name', 'asc')
            ->withValues(['x', 'y']);
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $encoded = $qry->encode();
        self::assertNotSame('', $encoded);
        self::assertNotFalse(base64_decode($encoded, true));

        $decoded = QueryExpression::decode($encoded);
        self::assertSame($qry->toArray(), $decoded->toArray());
    }

    public function testDecodeEmptyStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryExpression::decode('');
    }

    public function testDecodeInvalidBase64Throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryExpression::decode('***not-base64***');
    }

    public function testDecodeEmptyDecodedPayloadThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryExpression::decode(base64_encode(''));
    }

    public function testSerializeRoundTrip(): void
    {
        $qry = QueryExpression::create()
            ->sortBy('name', 'asc')
            ->withValues(['x']);
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $restored = unserialize(serialize($qry));
        self::assertInstanceOf(QueryExpression::class, $restored);
        self::assertSame($qry->toArray(), $restored->toArray());
    }

    public function testCloneIsDeep(): void
    {
        $qry = QueryExpression::create()->sortBy('name', 'asc');
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $clone = clone $qry;
        self::assertNotSame($qry->getFilter(), $clone->getFilter());
        self::assertNotSame($qry->getSort(), $clone->getSort());
    }

    public function testApplyFieldMappingMapsFilterAndSort(): void
    {
        $mapped = QueryExpression::applyFieldMapping(
            [
                'filter' => ['field' => 'a', 'operator' => 'eq', 'value' => 1],
                'sort' => [['field' => 'a', 'dir' => 'asc']],
            ],
            ['a' => 'aliased_a'],
        );

        self::assertSame('aliased_a', $mapped['filter']['field']);
        self::assertSame('aliased_a', $mapped['sort'][0]['field']);
    }

    public function testApplyFieldMappingHandlesMissingParts(): void
    {
        $mapped = QueryExpression::applyFieldMapping([], ['a' => 'b']);

        self::assertSame([], $mapped);
    }

    public function testWrapWithOrLogicOperator(): void
    {
        $base  = QueryExpression::create();
        $base  = $base->andWhere($base->expr()->equalTo('a', 1));
        $other = QueryExpression::create();
        $other = $other->andWhere($other->expr()->equalTo('b', 2));

        $wrapped = $base->wrap($other, FilterExpression::LOGIC_OR);

        self::assertSame('or', $this->filterOf($wrapped)->logic());
        self::assertCount(2, $this->filterOf($wrapped)->filters());
    }

    public function testWrapWithInvalidLogicOperatorThrows(): void
    {
        $base  = QueryExpression::create();
        $base  = $base->andWhere($base->expr()->equalTo('a', 1));
        $other = QueryExpression::create();
        $other = $other->andWhere($other->expr()->equalTo('b', 2));

        $this->expectException(InvalidArgumentException::class);
        $base->wrap($other, 'xor');
    }

    public function testWrapAndCombinesMultipleExpressionsSequentially(): void
    {
        $qry1 = QueryExpression::create();
        $qry1 = $qry1->andWhere($qry1->expr()->equalTo('a', 1));
        $qry2 = QueryExpression::create();
        $qry2 = $qry2->andWhere($qry2->expr()->equalTo('b', 2));

        $wrapped = QueryExpression::create()->wrapAnd($qry1, $qry2);

        $filter = $this->filterOf($wrapped);
        self::assertSame('and', $filter->logic());
        self::assertCount(2, $filter->filters());
    }

    public function testWrapOrCombinesExpressionsWithOrLogic(): void
    {
        $qry1 = QueryExpression::create();
        $qry1 = $qry1->andWhere($qry1->expr()->equalTo('a', 1));
        $qry2 = QueryExpression::create();
        $qry2 = $qry2->andWhere($qry2->expr()->equalTo('b', 2));

        $wrapped = QueryExpression::create()->wrapOr($qry1, $qry2);

        $filter = $this->filterOf($wrapped);
        self::assertSame('or', $filter->logic());
        self::assertCount(2, $filter->filters());
    }

    public function testWrapAndWithNoArgsReturnsClone(): void
    {
        $qry     = QueryExpression::create();
        $qry     = $qry->andWhere($qry->expr()->equalTo('a', 1));
        $wrapped = $qry->wrapAnd();

        self::assertSame($qry->toArray(), $wrapped->toArray());
    }

    public function testInvertFlipsFilterAndSort(): void
    {
        $qry = QueryExpression::create()->sortBy('name', 'asc');
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $inverted = $qry->invert();

        self::assertTrue($this->filterOf($inverted)->inverted());
        self::assertSame('desc', $inverted->sortDir('name'));
    }

    public function testInvertPreservesValues(): void
    {
        $qry      = QueryExpression::create()->withValues(['x', 'y']);
        $inverted = $qry->invert();

        self::assertSame(['x', 'y'], $inverted->getValues());
    }

    public function testInvertOnEmptyIsEmpty(): void
    {
        $inverted = QueryExpression::create()->invert();

        self::assertTrue($inverted->isEmpty());
    }

    public function testWrapMergesFiltersAndSorts(): void
    {
        $base = QueryExpression::create()->sortBy('name', 'asc');
        $base = $base->andWhere($base->expr()->equalTo('a', 1));

        $other = QueryExpression::create()->sortBy('age', 'desc');
        $other = $other->andWhere($other->expr()->equalTo('b', 2));

        $wrapped = $base->wrap($other);

        $filter = $this->filterOf($wrapped);
        self::assertSame('and', $filter->logic());
        self::assertCount(2, $filter->filters());

        self::assertSame(2, $wrapped->sortCount());
        self::assertSame('desc', $wrapped->sortDir('age'));
        self::assertSame('asc', $wrapped->sortDir('name'));
        self::assertSame(1, $wrapped->sortNum('age'));
        self::assertSame(2, $wrapped->sortNum('name'));
    }

    public function testWrapWithEmptyOther(): void
    {
        $base = QueryExpression::create();
        $base = $base->andWhere($base->expr()->equalTo('a', 1));

        $wrapped = $base->wrap(QueryExpression::create());

        $filter = $this->filterOf($wrapped);
        self::assertSame('and', $filter->logic());
        self::assertCount(1, $filter->filters());
    }

    public function testAppendToReturnsProviderUnchangedForEmptyQuery(): void
    {
        $provider = $this->createMock(ReadDataProviderInterface::class);
        $provider->expects(self::never())->method('withQueryExpression');

        $qry = QueryExpression::create();

        self::assertSame($provider, $qry->appendTo($provider));
    }

    public function testAppendToCallsWithQueryExpression(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('a', 1));

        $provider = $this->createMock(ReadDataProviderInterface::class);
        $provider
            ->expects(self::once())
            ->method('withQueryExpression')
            ->with($qry)
            ->willReturnSelf();

        self::assertSame($provider, $qry->appendTo($provider));
    }
}
