<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use Kraz\ReadModel\ReadResponse;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonReadModel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersonReadModel::class)]
final class PersonReadModelTest extends TestCase
{
    private PersonReadModel $model;

    protected function setUp(): void
    {
        $this->model = new PersonReadModel();
    }

    /** @return list<int> */
    private function ids(PersonReadModel $model): array
    {
        $ids = [];
        foreach ($model as $person) {
            $ids[] = $person->id;
        }

        return $ids;
    }

    // --- Default (paginated: page 1, 3 per page) state ---

    public function testDefaultIsPaginated(): void
    {
        self::assertTrue($this->model->isPaginated());
    }

    public function testDefaultCountIsThreeItemsOnFirstPage(): void
    {
        self::assertSame(3, $this->model->count());
    }

    public function testDefaultTotalCountIsFive(): void
    {
        self::assertSame(5, $this->model->totalCount());
    }

    public function testDefaultIsNotEmpty(): void
    {
        self::assertFalse($this->model->isEmpty());
    }

    public function testDefaultPaginatorIsNotNull(): void
    {
        self::assertNotNull($this->model->paginator());
    }

    public function testDefaultPaginatorCurrentPageIsOne(): void
    {
        self::assertSame(1, $this->model->paginator()?->getCurrentPage());
    }

    public function testDefaultPaginatorLastPageIsTwo(): void
    {
        self::assertSame(2, $this->model->paginator()?->getLastPage());
    }

    public function testDefaultDataReturnsFirstPageItems(): void
    {
        $data = $this->model->data();

        self::assertCount(3, $data);
        self::assertSame(1, $data[0]->id);
        self::assertSame(2, $data[1]->id);
        self::assertSame(3, $data[2]->id);
    }

    public function testDefaultIterationYieldsFirstPageItems(): void
    {
        self::assertSame([1, 2, 3], $this->ids($this->model));
    }

    public function testDefaultGetResultReturnsReadResponse(): void
    {
        $result = $this->model->getResult();

        self::assertInstanceOf(ReadResponse::class, $result);
        self::assertSame(1, $result->page);
        self::assertSame(5, $result->total);
        self::assertCount(3, $result->data ?? []);
    }

    // --- Fixture data integrity ---

    public function testDataItemsMatchExpectedPeople(): void
    {
        $data = $this->model->withoutPagination()->data();

        $map = [];
        foreach ($data as $person) {
            $map[$person->id] = [$person->name, $person->age];
        }

        self::assertSame([
            1 => ['Alice', 30],
            2 => ['Bob', 25],
            3 => ['Carol', 40],
            4 => ['Dan', 35],
            5 => ['Eve', 22],
        ], $map);
    }

    public function testAllPersonFixtureFieldsAreAccessible(): void
    {
        foreach ($this->model->withoutPagination() as $person) {
            self::assertInstanceOf(PersonFixture::class, $person);
            self::assertIsInt($person->id);
            self::assertIsString($person->name);
            self::assertIsInt($person->age);
        }
    }

    // --- Pagination ---

    public function testNavigateToPageTwoHasTwoRemainingItems(): void
    {
        $model = $this->model->withPagination(2, 3);

        self::assertSame(2, $model->count());
        self::assertSame(5, $model->totalCount());
        self::assertSame([4, 5], $this->ids($model));
    }

    public function testNavigateToPageTwoGetResultHasCorrectMetadata(): void
    {
        $result = $this->model->withPagination(2, 3)->getResult();

        self::assertInstanceOf(ReadResponse::class, $result);
        self::assertSame(2, $result->page);
        self::assertSame(5, $result->total);
        self::assertCount(2, $result->data ?? []);
    }

    public function testWithoutPaginationRestoresAllItems(): void
    {
        $model = $this->model->withoutPagination();

        self::assertFalse($model->isPaginated());
        self::assertSame(5, $model->count());
        self::assertSame([1, 2, 3, 4, 5], $this->ids($model));
    }

    // --- Immutability ---

    public function testWithPaginationDoesNotMutateOriginal(): void
    {
        $this->model->withPagination(2, 3);

        self::assertTrue($this->model->isPaginated());
        self::assertSame(3, $this->model->count());
        self::assertSame([1, 2, 3], $this->ids($this->model));
    }

    public function testWithQueryExpressionDoesNotMutateOriginal(): void
    {
        $qry = $this->model->qry()->andWhere($this->model->expr()->equalTo(PersonReadModel::FIELD_NAME, 'Alice'));
        $this->model->withQueryExpression($qry);

        self::assertSame([1, 2, 3], $this->ids($this->model));
    }

    // --- Filtering (removes pagination to test against full dataset) ---

    public function testFilterByNameReturnsMatchingItem(): void
    {
        $qry   = $this->model->qry()->andWhere($this->model->expr()->equalTo(PersonReadModel::FIELD_NAME, 'Bob'));
        $model = $this->model->withQueryExpression($qry);

        self::assertSame([2], $this->ids($model));
    }

    public function testFilterByAgeGreaterThanReturnsMatchingItems(): void
    {
        $qry   = $this->model->qry()->andWhere($this->model->expr()->greaterThan(PersonReadModel::FIELD_AGE, 30));
        $model = $this->model->withoutPagination()->withQueryExpression($qry);

        self::assertSame([3, 4], $this->ids($model));
    }

    public function testFilterByNonExistentValueReturnsEmpty(): void
    {
        $qry   = $this->model->qry()->andWhere($this->model->expr()->equalTo(PersonReadModel::FIELD_NAME, 'Unknown'));
        $model = $this->model->withQueryExpression($qry);

        self::assertTrue($model->isEmpty());
        self::assertSame([], $this->ids($model));
    }

    public function testWithoutQueryExpressionClearsFilters(): void
    {
        $qry      = $this->model->qry()->andWhere($this->model->expr()->equalTo(PersonReadModel::FIELD_NAME, 'Alice'));
        $filtered = $this->model->withoutPagination()->withQueryExpression($qry);
        $cleared  = $filtered->withoutQueryExpression();

        self::assertSame([1, 2, 3, 4, 5], $this->ids($cleared));
    }

    // --- Sorting ---

    public function testSortByAgeAscending(): void
    {
        $qry   = $this->model->qry()->sortBy(PersonReadModel::FIELD_AGE);
        $model = $this->model->withoutPagination()->withQueryExpression($qry);

        self::assertSame([5, 2, 1, 4, 3], $this->ids($model));
    }

    public function testSortByAgeDescending(): void
    {
        $qry   = $this->model->qry()->sortBy(PersonReadModel::FIELD_AGE, 'DESC');
        $model = $this->model->withoutPagination()->withQueryExpression($qry);

        self::assertSame([3, 4, 1, 2, 5], $this->ids($model));
    }

    // --- Reset ---

    public function testResetRestoresInitialPaginatedStateAfterFilter(): void
    {
        $filtered = $this->model->withoutPagination()->withQueryExpression(
            $this->model->qry()->andWhere($this->model->expr()->equalTo(PersonReadModel::FIELD_NAME, 'Alice')),
        );

        self::assertSame([1], $this->ids($filtered));

        $filtered->reset();

        // reset() recreates the data source, restoring the default paginated state (page 1, 3 items)
        self::assertSame([1, 2, 3], $this->ids($filtered));
        self::assertTrue($filtered->isPaginated());
    }

    public function testResetReturnsSelf(): void
    {
        $model  = $this->model->withoutPagination()->withQueryExpression(
            $this->model->qry()->andWhere($this->model->expr()->equalTo(PersonReadModel::FIELD_NAME, 'Alice')),
        );
        $result = $model->reset();

        self::assertSame($model, $result);
    }
}
