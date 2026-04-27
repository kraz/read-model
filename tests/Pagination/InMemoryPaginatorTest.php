<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Pagination;

use ArrayIterator;
use EmptyIterator;
use InvalidArgumentException;
use Kraz\ReadModel\Pagination\InMemoryPaginator;
use LimitIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

#[CoversClass(InMemoryPaginator::class)]
final class InMemoryPaginatorTest extends TestCase
{
    /** @return ArrayIterator<int, array{id: int}> */
    private function items(int $count): ArrayIterator
    {
        $items = [];
        for ($i = 1; $i <= $count; $i++) {
            $items[] = ['id' => $i];
        }

        return new ArrayIterator($items);
    }

    public function testGettersExposeConstructorArguments(): void
    {
        $paginator = new InMemoryPaginator($this->items(10), 10, 2, 3);

        self::assertSame(2, $paginator->getCurrentPage());
        self::assertSame(3, $paginator->getItemsPerPage());
        self::assertSame(10, $paginator->getTotalItems());
    }

    public function testLastPageRoundsUp(): void
    {
        $paginator = new InMemoryPaginator($this->items(10), 10, 1, 3);

        self::assertSame(4, $paginator->getLastPage());
    }

    public function testLastPageIsAtLeastOneWhenEmpty(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([]), 0, 1, 10);

        self::assertSame(1, $paginator->getLastPage());
    }

    public function testLastPageWhenTotalDividesEvenly(): void
    {
        $paginator = new InMemoryPaginator($this->items(9), 9, 1, 3);

        self::assertSame(3, $paginator->getLastPage());
    }

    public function testNegativeTotalItemsThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryPaginator(new ArrayIterator([]), -1, 1, 10);
    }

    public function testZeroCurrentPageThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryPaginator(new ArrayIterator([]), 0, 0, 10);
    }

    public function testNegativeCurrentPageThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryPaginator(new ArrayIterator([]), 0, -1, 10);
    }

    public function testZeroItemsPerPageThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryPaginator(new ArrayIterator([]), 0, 1, 0);
    }

    public function testNegativeItemsPerPageThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryPaginator(new ArrayIterator([]), 0, 1, -5);
    }

    public function testGetIteratorReturnsLimitIteratorForFirstPage(): void
    {
        $paginator = new InMemoryPaginator($this->items(10), 10, 1, 3);

        $iterator = $paginator->getIterator();
        self::assertInstanceOf(LimitIterator::class, $iterator);

        $values = iterator_to_array($iterator, false);
        self::assertSame([['id' => 1], ['id' => 2], ['id' => 3]], $values);
    }

    public function testGetIteratorAdvancesByOffsetForMiddlePage(): void
    {
        $paginator = new InMemoryPaginator($this->items(10), 10, 2, 3);

        $values = iterator_to_array($paginator->getIterator(), false);

        self::assertSame([['id' => 4], ['id' => 5], ['id' => 6]], $values);
    }

    public function testGetIteratorReturnsPartialLastPage(): void
    {
        $paginator = new InMemoryPaginator($this->items(10), 10, 4, 3);

        $values = iterator_to_array($paginator->getIterator(), false);

        self::assertSame([['id' => 10]], $values);
    }

    public function testGetIteratorReturnsEmptyIteratorPastLastPage(): void
    {
        $paginator = new InMemoryPaginator($this->items(10), 10, 5, 3);

        $iterator = $paginator->getIterator();
        self::assertInstanceOf(EmptyIterator::class, $iterator);
        self::assertSame([], iterator_to_array($iterator, false));
    }

    public function testGetIteratorReturnsEmptyIteratorWhenNoTotalItems(): void
    {
        $paginator = new InMemoryPaginator(new ArrayIterator([]), 0, 2, 10);

        self::assertInstanceOf(EmptyIterator::class, $paginator->getIterator());
    }

    public function testCountReflectsCurrentPageItems(): void
    {
        $full    = new InMemoryPaginator($this->items(10), 10, 1, 3);
        $partial = new InMemoryPaginator($this->items(10), 10, 4, 3);
        $past    = new InMemoryPaginator($this->items(10), 10, 5, 3);

        self::assertCount(3, $full);
        self::assertCount(1, $partial);
        self::assertCount(0, $past);
    }
}
