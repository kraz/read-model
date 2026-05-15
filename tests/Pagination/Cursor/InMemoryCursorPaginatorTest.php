<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Pagination\Cursor;

use Kraz\ReadModel\Exception\InvalidCursorException;
use Kraz\ReadModel\Pagination\Cursor\Base64JsonCursorCodec;
use Kraz\ReadModel\Pagination\Cursor\Cursor;
use Kraz\ReadModel\Pagination\Cursor\CursorCodecInterface;
use Kraz\ReadModel\Pagination\Cursor\Direction;
use Kraz\ReadModel\Pagination\Cursor\InMemoryCursorPaginator;
use Kraz\ReadModel\Query\SortExpression;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;
use function iterator_to_array;
use function usort;

#[CoversClass(InMemoryCursorPaginator::class)]
final class InMemoryCursorPaginatorTest extends TestCase
{
    private CursorCodecInterface $codec;

    protected function setUp(): void
    {
        $this->codec = new Base64JsonCursorCodec();
    }

    /** @phpstan-return list<array{id: int, age: int, name: string}> */
    private function people(): array
    {
        return [
            ['id' => 1, 'age' => 20, 'name' => 'Anna'],
            ['id' => 2, 'age' => 25, 'name' => 'Bob'],
            ['id' => 3, 'age' => 30, 'name' => 'Carol'],
            ['id' => 4, 'age' => 35, 'name' => 'Dan'],
            ['id' => 5, 'age' => 40, 'name' => 'Eve'],
            ['id' => 6, 'age' => 45, 'name' => 'Frank'],
            ['id' => 7, 'age' => 50, 'name' => 'Gina'],
        ];
    }

    /**
     * @phpstan-param list<array{id: int, age: int, name: string}> $items
     *
     * @phpstan-return list<int>
     */
    private function ids(array $items): array
    {
        return array_column($items, 'id');
    }

    public function testFirstPageHasNoPreviousCursor(): void
    {
        $sort      = SortExpression::create()->asc('id');
        $paginator = new InMemoryCursorPaginator(
            $this->people(),
            $sort,
            3,
            $this->codec,
        );

        self::assertSame(Direction::FORWARD, $paginator->getDirection());
        self::assertFalse($paginator->hasPrevious());
        self::assertTrue($paginator->hasNext());
        self::assertNull($paginator->getPreviousCursor());
        self::assertNotNull($paginator->getNextCursor());

        /** @phpstan-var list<array{id: int, age: int, name: string}> $window */
        $window = iterator_to_array($paginator->getIterator(), false);
        self::assertSame([1, 2, 3], $this->ids($window));
    }

    public function testLastPageHasNoNextCursor(): void
    {
        $sort = SortExpression::create()->asc('id');

        // First page (3 items), then second (3 items), then last (1 item).
        $first       = new InMemoryCursorPaginator($this->people(), $sort, 3, $this->codec);
        $secondToken = $first->getNextCursor();
        self::assertNotNull($secondToken);

        $second = new InMemoryCursorPaginator(
            $this->people(),
            $sort,
            3,
            $this->codec,
            $this->codec->decode($secondToken),
        );

        $thirdToken = $second->getNextCursor();
        self::assertNotNull($thirdToken);

        $third = new InMemoryCursorPaginator(
            $this->people(),
            $sort,
            3,
            $this->codec,
            $this->codec->decode($thirdToken),
        );

        /** @phpstan-var list<array{id: int, age: int, name: string}> $window */
        $window = iterator_to_array($third->getIterator(), false);
        self::assertSame([7], $this->ids($window));
        self::assertFalse($third->hasNext());
        self::assertTrue($third->hasPrevious());
        self::assertNull($third->getNextCursor());
    }

    public function testBackwardNavigationReturnsPreviousWindowInNaturalOrder(): void
    {
        $sort  = SortExpression::create()->asc('id');
        $first = new InMemoryCursorPaginator($this->people(), $sort, 3, $this->codec);

        $secondPageToken = $first->getNextCursor();
        self::assertNotNull($secondPageToken);
        $second = new InMemoryCursorPaginator(
            $this->people(),
            $sort,
            3,
            $this->codec,
            $this->codec->decode($secondPageToken),
        );

        // Walking back from the second page should land us on the first.
        $backToken = $second->getPreviousCursor();
        self::assertNotNull($backToken);
        $back = new InMemoryCursorPaginator(
            $this->people(),
            $sort,
            3,
            $this->codec,
            $this->codec->decode($backToken),
        );

        /** @phpstan-var list<array{id: int, age: int, name: string}> $window */
        $window = iterator_to_array($back->getIterator(), false);
        self::assertSame([1, 2, 3], $this->ids($window), 'Backward must restore natural order.');
        self::assertFalse($back->hasPrevious());
        self::assertTrue($back->hasNext());
    }

    public function testForwardThenBackwardRoundTrip(): void
    {
        $sort  = SortExpression::create()->asc('id');
        $first = new InMemoryCursorPaginator($this->people(), $sort, 2, $this->codec);

        $current  = $first;
        $forwards = [];
        while ($current->hasNext()) {
            $forwards[] = $this->ids(iterator_to_array($current->getIterator(), false));
            $next       = $current->getNextCursor();
            self::assertNotNull($next);
            $current = new InMemoryCursorPaginator(
                $this->people(),
                $sort,
                2,
                $this->codec,
                $this->codec->decode($next),
            );
        }

        $forwards[] = $this->ids(iterator_to_array($current->getIterator(), false));
        self::assertSame([[1, 2], [3, 4], [5, 6], [7]], $forwards);

        // Walk back from the final page.
        $backwards = [];
        while ($current->hasPrevious()) {
            $prev = $current->getPreviousCursor();
            self::assertNotNull($prev);
            $current     = new InMemoryCursorPaginator(
                $this->people(),
                $sort,
                2,
                $this->codec,
                $this->codec->decode($prev),
            );
            $backwards[] = $this->ids(iterator_to_array($current->getIterator(), false));
        }

        // Returning in reverse order means each backwards page mirrors a forward page.
        self::assertSame([[5, 6], [3, 4], [1, 2]], $backwards);
    }

    public function testDescendingSortReturnsItemsHighFirst(): void
    {
        $sort  = SortExpression::create()->desc('id');
        $items = $this->people();
        usort($items, static fn (array $a, array $b): int => $b['id'] <=> $a['id']);

        $paginator = new InMemoryCursorPaginator($items, $sort, 3, $this->codec);
        /** @phpstan-var list<array{id: int, age: int, name: string}> $window */
        $window = iterator_to_array($paginator->getIterator(), false);

        self::assertSame([7, 6, 5], $this->ids($window));

        $next = $paginator->getNextCursor();
        self::assertNotNull($next);
        $second = new InMemoryCursorPaginator(
            $items,
            $sort,
            3,
            $this->codec,
            $this->codec->decode($next),
        );
        /** @phpstan-var list<array{id: int, age: int, name: string}> $secondWindow */
        $secondWindow = iterator_to_array($second->getIterator(), false);
        self::assertSame([4, 3, 2], $this->ids($secondWindow));
    }

    public function testMultiFieldSortTieBreakerKeepsRowsStable(): void
    {
        // Three rows share the same age — the id tiebreaker decides their order.
        $items = [
            ['id' => 1, 'age' => 30, 'name' => 'A'],
            ['id' => 2, 'age' => 30, 'name' => 'B'],
            ['id' => 3, 'age' => 30, 'name' => 'C'],
            ['id' => 4, 'age' => 40, 'name' => 'D'],
            ['id' => 5, 'age' => 40, 'name' => 'E'],
        ];
        $sort  = SortExpression::create()->asc('age')->asc('id');

        $first = new InMemoryCursorPaginator($items, $sort, 2, $this->codec);
        self::assertSame([1, 2], $this->ids(iterator_to_array($first->getIterator(), false)));

        $next = $first->getNextCursor();
        self::assertNotNull($next);
        $second = new InMemoryCursorPaginator(
            $items,
            $sort,
            2,
            $this->codec,
            $this->codec->decode($next),
        );
        self::assertSame([3, 4], $this->ids(iterator_to_array($second->getIterator(), false)));

        $thirdToken = $second->getNextCursor();
        self::assertNotNull($thirdToken);
        $third = new InMemoryCursorPaginator(
            $items,
            $sort,
            2,
            $this->codec,
            $this->codec->decode($thirdToken),
        );
        self::assertSame([5], $this->ids(iterator_to_array($third->getIterator(), false)));
        self::assertFalse($third->hasNext());
    }

    public function testCursorWithMismatchedSortSignatureThrows(): void
    {
        $sort        = SortExpression::create()->asc('id');
        $foreignSort = SortExpression::create()->desc('age');
        $cursor      = new Cursor(
            Direction::FORWARD,
            [['field' => 'age', 'value' => 30]],
            Cursor::signatureFor($foreignSort),
        );

        $this->expectException(InvalidCursorException::class);
        new InMemoryCursorPaginator($this->people(), $sort, 2, $this->codec, $cursor);
    }

    public function testEmptySortIsRejected(): void
    {
        $this->expectException(LogicException::class);
        new InMemoryCursorPaginator($this->people(), SortExpression::create(), 2, $this->codec);
    }

    public function testEmptyItemsetReturnsEmptyWindow(): void
    {
        $paginator = new InMemoryCursorPaginator(
            [],
            SortExpression::create()->asc('id'),
            3,
            $this->codec,
        );

        self::assertSame(0, $paginator->count());
        self::assertFalse($paginator->hasNext());
        self::assertFalse($paginator->hasPrevious());
        self::assertNull($paginator->getNextCursor());
        self::assertNull($paginator->getPreviousCursor());
        self::assertSame([], iterator_to_array($paginator->getIterator(), false));
    }

    public function testCountReportsWindowSizeNotTotal(): void
    {
        $paginator = new InMemoryCursorPaginator(
            $this->people(),
            SortExpression::create()->asc('id'),
            3,
            $this->codec,
            null,
            42,
        );

        self::assertSame(3, $paginator->count(), 'count() reports the window size.');
        self::assertSame(42, $paginator->getTotalItems());
    }

    public function testTotalItemsIsNullWhenUnknown(): void
    {
        $paginator = new InMemoryCursorPaginator(
            $this->people(),
            SortExpression::create()->asc('id'),
            3,
            $this->codec,
        );

        self::assertNull($paginator->getTotalItems());
    }

    public function testObjectItemsUseDefaultFieldAccessorWithProperties(): void
    {
        $items = array_map(
            static fn (array $row): object => (object) $row,
            $this->people(),
        );
        $sort  = SortExpression::create()->asc('id');

        $paginator = new InMemoryCursorPaginator($items, $sort, 2, $this->codec);
        /** @phpstan-var list<object{id: int}> $window */
        $window = iterator_to_array($paginator->getIterator(), false);

        $ids = array_map(static fn (object $o): int => $o->id, $window);
        self::assertSame([1, 2], $ids);

        $next = $paginator->getNextCursor();
        self::assertNotNull($next);
        $second = new InMemoryCursorPaginator(
            $items,
            $sort,
            2,
            $this->codec,
            $this->codec->decode($next),
        );
        /** @phpstan-var list<object{id: int}> $secondWindow */
        $secondWindow = iterator_to_array($second->getIterator(), false);
        $nextIds      = array_map(static fn (object $o): int => $o->id, $secondWindow);
        self::assertSame([3, 4], $nextIds);
    }
}
