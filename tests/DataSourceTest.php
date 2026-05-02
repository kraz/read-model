<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests;

use ArrayIterator;
use ArrayObject;
use InvalidArgumentException;
use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\DataSource;
use Kraz\ReadModel\Pagination\InMemoryPaginator;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProvider;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\ReadDataProviderInterface;
use Kraz\ReadModel\ReadModelDescriptorFactory;
use Kraz\ReadModel\ReadModelDescriptorFactoryInterface;
use Kraz\ReadModel\ReadResponse;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function base64_encode;
use function iterator_to_array;
use function json_encode;

#[CoversClass(DataSource::class)]
final class DataSourceTest extends TestCase
{
    /** @return ArrayCollection<int, PersonFixture> */
    private function people(): ArrayCollection
    {
        return new ArrayCollection([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
        ]);
    }

    /** @return list<PersonFixture> */
    private function peopleArray(): array
    {
        return [
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
            new PersonFixture(id: 4, name: 'Dan', age: 35),
            new PersonFixture(id: 5, name: 'Eve', age: 22),
        ];
    }

    /**
     * @phpstan-param iterable<PersonFixture> $items
     *
     * @return list<int>
     */
    private function ids(iterable $items): array
    {
        $ids = [];
        foreach ($items as $person) {
            $ids[] = $person->id;
        }

        return $ids;
    }

    // ------------------------------------------------------------------
    // Basic data type handling
    // ------------------------------------------------------------------

    public function testNullDataIsEmpty(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);

        self::assertTrue($ds->isEmpty());
        self::assertSame(0, $ds->count());
        self::assertSame(0, $ds->totalCount());
        self::assertSame([], $ds->data());
        self::assertNull($ds->paginator());
        self::assertFalse($ds->isPaginated());
        self::assertSame([], iterator_to_array($ds->getIterator()));
    }

    public function testEmptyArrayIsEmpty(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource([]);

        self::assertTrue($ds->isEmpty());
        self::assertSame(0, $ds->count());
        self::assertSame(0, $ds->totalCount());
    }

    public function testArrayDataIsIterableAndCounted(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource([
            new PersonFixture(id: 1, name: 'Alice'),
            new PersonFixture(id: 2, name: 'Bob'),
        ]);

        self::assertSame([1, 2], $this->ids($ds));
        self::assertSame(2, $ds->count());
        self::assertSame(2, $ds->totalCount());
        self::assertFalse($ds->isEmpty());
    }

    public function testCollectionDataIsIterableAndCounted(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertSame([1, 2, 3], $this->ids($ds));
        self::assertSame(3, $ds->count());
        self::assertSame(3, $ds->totalCount());
        self::assertFalse($ds->isEmpty());
    }

    public function testIteratorAggregateDataIsIterable(): void
    {
        /** @var ArrayObject<int, PersonFixture> $items */
        $items = new ArrayObject([new PersonFixture(id: 7, name: 'Dan')]);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($items);

        self::assertSame([7], $this->ids($ds));
        self::assertSame(1, $ds->totalCount());
    }

    public function testTraversableDataIsIterable(): void
    {
        $generator = (static function () {
            yield new PersonFixture(id: 11, name: 'Ed');
            yield new PersonFixture(id: 12, name: 'Fay');
        })();

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($generator);

        self::assertSame([11, 12], $this->ids($ds));
    }

    // ------------------------------------------------------------------
    // Item normalizer
    // ------------------------------------------------------------------

    public function testItemNormalizerIsApplied(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(
            $this->people(),
            static function (PersonFixture $p): PersonFixture {
                $p->name = 'X-' . $p->name;

                return $p;
            },
        );

        $names = [];
        foreach ($ds as $person) {
            $names[] = $person->name;
        }

        self::assertSame(['X-Alice', 'X-Bob', 'X-Carol'], $names);
    }

    public function testItemNormalizerAppliedExactlyOnceWithPagination(): void
    {
        $calls = 0;
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(
            $this->people(),
            static function (PersonFixture $p) use (&$calls): PersonFixture {
                $calls++;
                $p->name = 'X-' . $p->name;

                return $p;
            },
        );

        $names = [];
        foreach ($ds->withPagination(1, 2) as $person) {
            $names[] = $person->name;
        }

        self::assertSame(['X-Alice', 'X-Bob'], $names);
        self::assertSame(2, $calls);
    }

    // ------------------------------------------------------------------
    // Pagination
    // ------------------------------------------------------------------

    public function testWithPaginationLimitsIteratorForCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $page2 = $ds->withPagination(2, 2);

        self::assertTrue($page2->isPaginated());
        self::assertInstanceOf(InMemoryPaginator::class, $page2->paginator());
        self::assertSame([3], $this->ids($page2));
        self::assertSame(1, $page2->count());
        self::assertSame(3, $page2->totalCount());
    }

    public function testWithPaginationOnArrayBuildsInMemoryPaginator(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource([
            new PersonFixture(id: 1),
            new PersonFixture(id: 2),
            new PersonFixture(id: 3),
            new PersonFixture(id: 4),
        ]);

        $page1 = $ds->withPagination(1, 2);

        self::assertInstanceOf(InMemoryPaginator::class, $page1->paginator());
        self::assertSame([1, 2], $this->ids($page1));
        self::assertSame(4, $page1->totalCount());
    }

    public function testWithPaginationOnGeneratorBuildsInMemoryPaginator(): void
    {
        $generator = (static function () {
            yield new PersonFixture(id: 1);
            yield new PersonFixture(id: 2);
            yield new PersonFixture(id: 3);
        })();

        /** @var DataSource<PersonFixture> $ds */
        $ds    = new DataSource($generator);
        $page1 = $ds->withPagination(1, 2);

        self::assertSame([1, 2], $this->ids($page1));
    }

    public function testWithPaginationRejectsNonPositivePage(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $this->expectException(InvalidArgumentException::class);
        $ds->withPagination(0, 5);
    }

    public function testWithPaginationRejectsNonPositiveItemsPerPage(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $this->expectException(InvalidArgumentException::class);
        $ds->withPagination(1, 0);
    }

    public function testWithoutPaginationUndoesLastWithPagination(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds      = new DataSource($this->people());
        $paged   = $ds->withPagination(1, 2);
        $cleared = $paged->withoutPagination();

        self::assertNull($cleared->paginator());
        self::assertFalse($cleared->isPaginated());
        self::assertSame([1, 2, 3], $this->ids($cleared));
    }

    public function testWithoutPaginationRestoresPreviousPagination(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $page1 = $ds->withPagination(1, 2);
        $page2 = $page1->withPagination(2, 2);

        self::assertSame([3], $this->ids($page2));

        $back = $page2->withoutPagination();

        $backPaginator = $back->paginator();
        self::assertNotNull($backPaginator);
        self::assertSame([1, 2], $this->ids($back));
        self::assertSame(1, $backPaginator->getCurrentPage());
        self::assertSame(2, $backPaginator->getItemsPerPage());
    }

    public function testWithoutPaginationOnFreshDataSourceIsNoOp(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $cleared = $ds->withoutPagination();

        self::assertNull($cleared->paginator());
        self::assertSame([1, 2, 3], $this->ids($cleared));
    }

    public function testPaginationDoesNotMutateOriginal(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $ds->withPagination(1, 1);

        self::assertNull($ds->paginator());
        self::assertSame([1, 2, 3], $this->ids($ds));
    }

    public function testPaginatorReturnsNullWhenNoPageSetOnCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertNull($ds->paginator());
    }

    // ------------------------------------------------------------------
    // Wrapped paginator / ReadDataProvider passthrough
    // ------------------------------------------------------------------

    public function testWrappedPaginatorIsExposed(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 10, 1, 5);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($paginator);

        self::assertSame($paginator, $ds->paginator());
        self::assertTrue($ds->isPaginated());
        self::assertSame(10, $ds->totalCount());
    }

    public function testWrappedReadDataProviderPaginatorIsExposed(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 1, 1, 5);

        $inner = $this->createStub(ReadDataProviderInterface::class);
        $inner->method('paginator')->willReturn($paginator);
        $inner->method('data')->willReturn([new PersonFixture(id: 1)]);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertSame($paginator, $ds->paginator());
    }

    public function testOwnPaginationOverridesWrappedPaginator(): void
    {
        $paginator = new InMemoryPaginator(
            new ArrayIterator([
                new PersonFixture(id: 1),
                new PersonFixture(id: 2),
                new PersonFixture(id: 3),
                new PersonFixture(id: 4),
            ]),
            4,
            1,
            4,
        );

        /** @var DataSource<PersonFixture> $ds */
        $ds   = new DataSource($paginator);
        $page = $ds->withPagination(2, 2);

        self::assertNotSame($paginator, $page->paginator());
        self::assertSame([3, 4], $this->ids($page));
    }

    public function testQueryExpressionDisablesWrappedPaginatorPassthrough(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 10, 1, 5);

        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($paginator);
        $filtered = $ds->withQueryExpression(QueryExpression::create());

        self::assertNull($filtered->paginator());
    }

    // ------------------------------------------------------------------
    // Query expression
    // ------------------------------------------------------------------

    public function testWithQueryExpressionFiltersArrayCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry      = QueryExpression::create();
        $qry      = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));
        $filtered = $ds->withQueryExpression($qry);

        self::assertSame([1], $this->ids($filtered));
    }

    public function testWithQueryExpressionFiltersPlainArray(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $qry      = QueryExpression::create();
        $qry      = $qry->andWhere($qry->expr()->greaterThan('age', 28));
        $filtered = $ds->withQueryExpression($qry);

        self::assertSame([1, 3, 4], $this->ids($filtered));
    }

    public function testWithQueryExpressionFiltersGenerator(): void
    {
        $generator = (static function () {
            yield new PersonFixture(id: 1, name: 'Alice', age: 30);
            yield new PersonFixture(id: 2, name: 'Bob', age: 25);
            yield new PersonFixture(id: 3, name: 'Carol', age: 40);
        })();

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($generator);

        $qry      = QueryExpression::create();
        $qry      = $qry->andWhere($qry->expr()->lowerThan('age', 35));
        $filtered = $ds->withQueryExpression($qry);

        self::assertSame([1, 2], $this->ids($filtered));
    }

    public function testWithQueryExpressionFiltersIteratorAggregate(): void
    {
        /** @var ArrayObject<int, PersonFixture> $items */
        $items = new ArrayObject([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
        ]);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($items);

        $qry      = QueryExpression::create();
        $qry      = $qry->andWhere($qry->expr()->equalTo('name', 'Bob'));
        $filtered = $ds->withQueryExpression($qry);

        self::assertSame([2], $this->ids($filtered));
    }

    public function testMultipleQueryExpressionsApplySequentially(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $first = QueryExpression::create();
        $first = $first->andWhere($first->expr()->greaterThan('age', 25));

        $second = QueryExpression::create();
        $second = $second->andWhere($second->expr()->lowerThan('age', 38));

        $filtered = $ds->withQueryExpression($first)->withQueryExpression($second, true);

        self::assertSame([1, 4], $this->ids($filtered));
        self::assertCount(2, $filtered->queryExpressions());
    }

    public function testMultipleQueryExpressionsCanBeMixedSortAndFilter(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $filter = QueryExpression::create();
        $filter = $filter->andWhere($filter->expr()->greaterThan('age', 25));

        $sort = QueryExpression::create()->sortBy('age', 'asc');

        $filtered = $ds->withQueryExpression($filter)->withQueryExpression($sort, true);

        self::assertSame([1, 4, 3], $this->ids($filtered));
    }

    public function testWithoutQueryExpressionUndoesLastWithQueryExpression(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $filtered = $ds->withQueryExpression($qry);
        $restored = $filtered->withoutQueryExpression(true);

        self::assertSame([1, 2, 3], $this->ids($restored));
        self::assertSame([], $restored->queryExpressions());
    }

    public function testWithoutQueryExpressionRestoresPreviousQueryStack(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $first = QueryExpression::create();
        $first = $first->andWhere($first->expr()->greaterThan('age', 25));

        $second = QueryExpression::create();
        $second = $second->andWhere($second->expr()->lowerThan('age', 38));

        $stacked = $ds->withQueryExpression($first)->withQueryExpression($second);
        $back    = $stacked->withoutQueryExpression(true);

        self::assertSame([1, 3, 4], $this->ids($back));
        self::assertCount(1, $back->queryExpressions());
    }

    public function testWithoutQueryExpressionOnFreshDataSourceIsNoOp(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $cleared = $ds->withoutQueryExpression();

        self::assertSame([1, 2, 3], $this->ids($cleared));
        self::assertSame([], $cleared->queryExpressions());
    }

    public function testWithoutQueryExpressionDefaultClearsAllExpressions(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $first = QueryExpression::create();
        $first = $first->andWhere($first->expr()->greaterThan('age', 25));

        $second = QueryExpression::create();
        $second = $second->andWhere($second->expr()->lowerThan('age', 38));

        $stacked = $ds->withQueryExpression($first)->withQueryExpression($second);
        $cleared = $stacked->withoutQueryExpression();

        self::assertSame([1, 2, 3, 4, 5], $this->ids($cleared));
        self::assertSame([], $cleared->queryExpressions());
    }

    public function testWithoutQueryExpressionDefaultAlsoClearsHistory(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $filtered = $ds->withQueryExpression($qry);
        $cleared  = $filtered->withoutQueryExpression();

        // After a clear, undo has nothing to restore — should stay empty
        $afterUndo = $cleared->withoutQueryExpression(true);
        self::assertSame([], $afterUndo->queryExpressions());
        self::assertSame([1, 2, 3], $this->ids($afterUndo));
    }

    public function testWithQueryExpressionDoesNotMutateOriginal(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds  = new DataSource($this->people());
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $ds->withQueryExpression($qry);

        self::assertSame([1, 2, 3], $this->ids($ds));
        self::assertSame([], $ds->queryExpressions());
    }

    public function testWithoutQueryExpressionOnReturnedCloneRestoresOriginal(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $filtered = $ds->withQueryExpression($qry);
        $restored = $filtered->withoutQueryExpression(true);

        self::assertSame([1, 2, 3], $this->ids($restored));
        self::assertSame([1], $this->ids($filtered));
    }

    public function testQueryExpressionsReturnsOwnList(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertSame([], $ds->queryExpressions());

        $qe       = QueryExpression::create();
        $filtered = $ds->withQueryExpression($qe);

        self::assertSame([$qe], $filtered->queryExpressions());
    }

    public function testWithQueryExpressionWorksOnPlainArrayWithoutThrowing(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource([
            new PersonFixture(id: 1, name: 'Alice'),
            new PersonFixture(id: 2, name: 'Bob'),
        ]);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Bob'));

        self::assertSame([2], $this->ids($ds->withQueryExpression($qry)));
    }

    // ------------------------------------------------------------------
    // Query expression + pagination interplay
    // ------------------------------------------------------------------

    public function testQueryExpressionAndPaginationCombined(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->greaterThan('age', 25));

        $result = $ds->withQueryExpression($qry)->withPagination(2, 2);

        self::assertSame([4], $this->ids($result));
        self::assertSame(3, $result->totalCount());
        self::assertTrue($result->isPaginated());
    }

    public function testWithQueryRequestAppliesQueryAndPagination(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Bob'));

        $request = QueryRequest::create()->withQueryExpression($qry)->withPagination(1, 5);
        $applied = $ds->withQueryRequest($request);

        self::assertTrue($applied->isPaginated());
        self::assertSame([2], $this->ids($applied));
    }

    // ------------------------------------------------------------------
    // Query modifier
    // ------------------------------------------------------------------

    public function testWithQueryModifierThrowsWithoutInnerProvider(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $this->expectException(LogicException::class);
        $ds->withQueryModifier(static fn (mixed $x): mixed => $x);
    }

    public function testWithQueryModifierAppliesToInnerProviderAtIteration(): void
    {
        $modifier = static fn (mixed $q): mixed => $q;

        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('withQueryModifier')
            ->with($modifier)
            ->willReturnSelf();
        $inner->method('data')->willReturn([new PersonFixture(id: 99)]);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $clone = $ds->withQueryModifier($modifier);

        self::assertNotSame($ds, $clone);
        self::assertSame([99], $this->ids($clone));
    }

    public function testQueryExpressionIsPushedToInnerReadDataProvider(): void
    {
        $qe = QueryExpression::create()->andWhere(QueryExpression::create()->expr()->greaterThan('age', 25));

        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('withQueryExpression')
            ->with($qe)
            ->willReturnSelf();
        $inner->method('data')->willReturn([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
        ]);

        /** @var DataSource<PersonFixture> $ds */
        $ds     = new DataSource($inner);
        $result = $ds->withQueryExpression($qe)->data();

        self::assertSame([1, 3], $this->ids($result));
    }

    public function testWithoutQueryModifierUndoesLastModifier(): void
    {
        $inner = $this->createStub(ReadDataProviderInterface::class);
        $inner->method('data')->willReturn([new PersonFixture(id: 1)]);

        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($inner);
        $modified = $ds->withQueryModifier(static fn (mixed $q): mixed => $q);
        $cleared  = $modified->withoutQueryModifier(true);

        self::assertSame([1], $this->ids($cleared));
    }

    public function testWithoutQueryModifierOnFreshDataSourceIsNoOp(): void
    {
        $inner = $this->createStub(ReadDataProviderInterface::class);
        $inner->method('data')->willReturn([new PersonFixture(id: 1)]);

        /** @var DataSource<PersonFixture> $ds */
        $ds      = new DataSource($inner);
        $cleared = $ds->withoutQueryModifier();

        self::assertSame([1], $this->ids($cleared));
    }

    public function testWithoutQueryModifierDefaultClearsAllModifiers(): void
    {
        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::never())->method('withQueryModifier');
        $inner->method('data')->willReturn([new PersonFixture(id: 1)]);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $stacked = $ds
            ->withQueryModifier(static fn (mixed $q): mixed => $q)
            ->withQueryModifier(static fn (mixed $q): mixed => $q);
        $cleared = $stacked->withoutQueryModifier();

        self::assertSame([1], $this->ids($cleared));
    }

    public function testWithoutQueryModifierDefaultAlsoClearsHistory(): void
    {
        $inner = $this->createStub(ReadDataProviderInterface::class);
        $inner->method('data')->willReturn([new PersonFixture(id: 1)]);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $modified = $ds->withQueryModifier(static fn (mixed $q): mixed => $q);
        $cleared  = $modified->withoutQueryModifier();

        // History was wiped — undo has nothing to restore, modifiers stay empty
        $afterUndo = $cleared->withoutQueryModifier(true);
        self::assertSame([1], $this->ids($afterUndo));
    }

    // ------------------------------------------------------------------
    // handleRequest / handleInput
    // ------------------------------------------------------------------

    public function testHandleRequestThrowsLogicException(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);

        $this->expectException(LogicException::class);
        $ds->handleRequest(new stdClass());
    }

    public function testHandleInputAppliesPaginationFromInput(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $clone = $ds->handleInput(['page' => 2, 'pageSize' => 2]);

        self::assertTrue($clone->isPaginated());
        self::assertSame([3, 4], $this->ids($clone));
    }

    public function testHandleInputAppliesQueryExpression(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry        = QueryExpression::create();
        $qry        = $qry->andWhere($qry->expr()->equalTo('name', 'Carol'));
        $queryParam = base64_encode((string) json_encode($qry->toArray()));

        $clone = $ds->handleInput(['query' => $queryParam]);

        self::assertSame([3], $this->ids($clone));
    }

    // ------------------------------------------------------------------
    // getResult / ReadResponse
    // ------------------------------------------------------------------

    public function testGetResultReturnsReadResponseForRawCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $result = $ds->getResult();

        self::assertInstanceOf(ReadResponse::class, $result);
        self::assertSame(1, $result->page);
        self::assertSame(3, $result->total);
        self::assertNotNull($result->data);
        self::assertCount(3, $result->data);
    }

    public function testGetResultReadResponsePageReflectsPaginator(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $result = $ds->withPagination(2, 2)->getResult();

        self::assertInstanceOf(ReadResponse::class, $result);
        self::assertSame(2, $result->page);
        self::assertSame(3, $result->total);
        self::assertNotNull($result->data);
        self::assertCount(1, $result->data);
    }

    public function testIsValueTrueWhenQueryExpressionHasValues(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create()->withValues([1, 3]);

        self::assertFalse($ds->isValue());
        self::assertTrue($ds->withQueryExpression($qry)->isValue());
    }

    public function testGetResultReturnsArrayWhenQueryHasValues(): void
    {
        $passthrough = $this->createStub(QueryExpressionProviderInterface::class);
        $passthrough->method('apply')->willReturnArgument(0);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());
        $ds->setQueryExpressionProvider($passthrough);

        $qry      = QueryExpression::create()->withValues([1, 3]);
        $filtered = $ds->withQueryExpression($qry);

        self::assertTrue($filtered->isValue());

        $result = $filtered->getResult();
        self::assertIsArray($result);
    }

    // ------------------------------------------------------------------
    // Getters / setters
    // ------------------------------------------------------------------

    public function testGetQueryExpressionProviderLazyDefault(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);

        $provider = $ds->getQueryExpressionProvider();

        self::assertInstanceOf(QueryExpressionProvider::class, $provider);
        self::assertSame($provider, $ds->getQueryExpressionProvider());
    }

    public function testSetQueryExpressionProviderOverridesDefault(): void
    {
        $custom = $this->createStub(QueryExpressionProviderInterface::class);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);
        $ds->setQueryExpressionProvider($custom);

        self::assertSame($custom, $ds->getQueryExpressionProvider());
    }

    public function testGetDescriptorFactoryLazyDefault(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);

        $factory = $ds->getDescriptorFactory();

        self::assertInstanceOf(ReadModelDescriptorFactory::class, $factory);
        self::assertSame($factory, $ds->getDescriptorFactory());
    }

    public function testSetDescriptorFactoryOverridesDefault(): void
    {
        $custom = $this->createStub(ReadModelDescriptorFactoryInterface::class);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);
        $ds->setDescriptorFactory($custom);

        self::assertSame($custom, $ds->getDescriptorFactory());
    }

    // ------------------------------------------------------------------
    // Immutability
    // ------------------------------------------------------------------

    public function testWithMethodsAlwaysReturnNewInstance(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertNotSame($ds, $ds->withPagination(1, 1));
        self::assertNotSame($ds, $ds->withoutPagination());
        self::assertNotSame($ds, $ds->withQueryExpression(QueryExpression::create()));
        self::assertNotSame($ds, $ds->withoutQueryExpression());
        self::assertNotSame($ds, $ds->withoutQueryModifier());
    }

    public function testIndependentClonesDoNotShareHistory(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->peopleArray());

        $first = QueryExpression::create();
        $first = $first->andWhere($first->expr()->greaterThan('age', 25));

        $second = QueryExpression::create();
        $second = $second->andWhere($second->expr()->lowerThan('age', 38));

        $branchA = $ds->withQueryExpression($first);
        $branchB = $branchA->withQueryExpression($second, true);

        $branchA = $branchA->withoutQueryExpression(true);

        self::assertSame([1, 4], $this->ids($branchB));
        self::assertSame([1, 2, 3, 4, 5], $this->ids($branchA));
    }
}
