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

use function iterator_to_array;

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

    public function testArrayDataIsIterableAndCounted(): void
    {
        $array = [
            new PersonFixture(id: 1, name: 'Alice'),
            new PersonFixture(id: 2, name: 'Bob'),
        ];

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($array);

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
        $array = [
            new PersonFixture(id: 1),
            new PersonFixture(id: 2),
            new PersonFixture(id: 3),
            new PersonFixture(id: 4),
        ];

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($array);

        $page1 = $ds->withPagination(1, 2);

        self::assertInstanceOf(InMemoryPaginator::class, $page1->paginator());
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

    public function testWithPaginationOnPaginatorRejectsDifferentParams(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 1, 1, 5);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($paginator);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('changing the pagination parameters');

        $ds->withPagination(2, 5);
    }

    public function testWithPaginationOnPaginatorAcceptsMatchingParams(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 1, 1, 5);

        /** @var DataSource<PersonFixture> $ds */
        $ds    = new DataSource($paginator);
        $clone = $ds->withPagination(1, 5);

        self::assertTrue($clone->isPaginated());
    }

    public function testWithoutPaginationDisablesPagination(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds      = new DataSource($this->people());
        $paged   = $ds->withPagination(1, 2);
        $cleared = $paged->withoutPagination();

        self::assertNull($cleared->paginator());
        self::assertFalse($cleared->isPaginated());
        self::assertSame([1, 2, 3], $this->ids($cleared));
    }

    public function testPaginatorReturnsNullWhenNoPageSetOnCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertNull($ds->paginator());
    }

    public function testPaginatorPassThroughForReadDataProvider(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 1, 1, 5);

        $inner = $this->createStub(ReadDataProviderInterface::class);
        $inner->method('paginator')->willReturn($paginator);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertSame($paginator, $ds->paginator());
    }

    public function testPaginatorPassThroughForPaginatorInstance(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([]), 0, 1, 5);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($paginator);

        self::assertSame($paginator, $ds->paginator());
        self::assertTrue($ds->isPaginated());
    }

    public function testIsEmptyDelegatesToReadDataProvider(): void
    {
        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())->method('isEmpty')->willReturn(true);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertTrue($ds->isEmpty());
    }

    public function testCountDelegatesToReadDataProvider(): void
    {
        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())->method('count')->willReturn(7);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertSame(7, $ds->count());
    }

    public function testTotalCountDelegatesToReadDataProvider(): void
    {
        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())->method('totalCount')->willReturn(42);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertSame(42, $ds->totalCount());
    }

    public function testTotalCountUsesPaginatorTotalItems(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([new PersonFixture(id: 1)]), 99, 1, 1);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($paginator);

        self::assertSame(99, $ds->totalCount());
    }

    public function testWithQueryExpressionFiltersArrayCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $filtered = $ds->withQueryExpression($qry);

        self::assertSame([1], $this->ids($filtered));
    }

    public function testWithoutQueryExpressionOnOriginalRestoresCollection(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $ds->withQueryExpression($qry);

        $restored = $ds->withoutQueryExpression();

        self::assertSame([1, 2, 3], $this->ids($restored));
    }

    public function testWithoutQueryExpressionOnReturnedCloneIsNoOp(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        $filtered = $ds->withQueryExpression($qry);
        $restored = $filtered->withoutQueryExpression();

        self::assertSame([1], $this->ids($restored));
    }

    public function testWithQueryExpressionDelegatesToInnerProvider(): void
    {
        $qry = QueryExpression::create();

        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('withQueryExpression')
            ->with($qry)
            ->willReturnSelf();

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $clone = $ds->withQueryExpression($qry);

        self::assertNotSame($ds, $clone);
    }

    public function testWithoutQueryExpressionDelegatesToInnerProvider(): void
    {
        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('withoutQueryExpression')
            ->willReturnSelf();

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $clone = $ds->withoutQueryExpression();

        self::assertNotSame($ds, $clone);
    }

    public function testWithQueryExpressionThrowsWhenDataIsArray(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource([new PersonFixture(id: 1)]);

        $this->expectException(LogicException::class);
        $ds->withQueryExpression(QueryExpression::create());
    }

    public function testQueryExpressionsForwardsFromInnerProvider(): void
    {
        $expressions = [QueryExpression::create()];

        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('queryExpressions')
            ->willReturn($expressions);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertSame($expressions, $ds->queryExpressions());
    }

    public function testQueryExpressionsReturnsEmptyForRawData(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertSame([], $ds->queryExpressions());
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

    public function testWithQueryModifierDelegatesToInner(): void
    {
        $modifier = static fn (mixed $x): mixed => $x;

        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('withQueryModifier')
            ->with($modifier)
            ->willReturnSelf();

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $clone = $ds->withQueryModifier($modifier);

        self::assertNotSame($ds, $clone);
    }

    public function testWithQueryModifierThrowsWithoutInnerProvider(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $this->expectException(LogicException::class);
        $ds->withQueryModifier(static fn (mixed $x): mixed => $x);
    }

    public function testWithoutQueryModifierDelegatesToInner(): void
    {
        $inner = $this->createMock(ReadDataProviderInterface::class);
        $inner->expects(self::once())
            ->method('withoutQueryModifier')
            ->willReturnSelf();

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        $clone = $ds->withoutQueryModifier();

        self::assertNotSame($ds, $clone);
    }

    public function testHandleRequestThrowsLogicException(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource(null);

        $this->expectException(LogicException::class);
        $ds->handleRequest(new stdClass());
    }

    public function testGetResultDelegatesToReadDataProvider(): void
    {
        $expected = ReadResponse::create([new PersonFixture(id: 1)], 1, 1);

        $inner = $this->createStub(ReadDataProviderInterface::class);
        $inner->method('getResult')->willReturn($expected);

        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($inner);

        self::assertSame($expected, $ds->getResult());
    }

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
    }

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
}
