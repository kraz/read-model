<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Tools;

use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use Kraz\ReadModel\Tools\CollectionUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_column;
use function array_map;

#[CoversClass(CollectionUtils::class)]
final class CollectionUtilsTest extends TestCase
{
    /** @return list<PersonFixture> */
    private function people(): array
    {
        return [
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
        ];
    }

    public function testSortByIndexReturnsEmptyForEmptyArray(): void
    {
        self::assertSame([], CollectionUtils::sortByIndex([], 'id', [1, 2, 3]));
    }

    public function testSortByIndexReturnsEmptyForEmptyArrayCollection(): void
    {
        /** @var ArrayCollection<int, PersonFixture> $empty */
        $empty = new ArrayCollection([]);

        self::assertSame([], CollectionUtils::sortByIndex($empty, 'id', [1, 2, 3]));
    }

    public function testSortByIndexSortsArrayItemsByField(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ];

        $sorted = CollectionUtils::sortByIndex($items, 'id', [3, 1, 2]);

        self::assertSame([3, 1, 2], array_column($sorted, 'id'));
    }

    public function testSortByIndexSortsObjectsWithPublicProperty(): void
    {
        $sorted = CollectionUtils::sortByIndex($this->people(), 'id', [3, 1, 2]);

        self::assertSame([3, 1, 2], array_column($sorted, 'id'));
    }

    public function testSortByIndexSortsObjectsWithGetterMethod(): void
    {
        $makeItem = static fn (int $id) => new class ($id) {
            public function __construct(private int $id)
            {
            }

            public function getId(): int
            {
                return $this->id;
            }
        };

        $items  = [$makeItem(1), $makeItem(2), $makeItem(3)];
        $sorted = CollectionUtils::sortByIndex($items, 'id', [3, 1, 2]);

        self::assertSame([3, 1, 2], array_map(static fn ($item) => $item->getId(), $sorted));
    }

    public function testSortByIndexAcceptsArrayCollectionInput(): void
    {
        /** @var ArrayCollection<int, PersonFixture> $collection */
        $collection = new ArrayCollection($this->people());

        $sorted = CollectionUtils::sortByIndex($collection, 'id', [3, 1, 2]);

        self::assertSame([3, 1, 2], array_column($sorted, 'id'));
    }

    public function testSortByIndexPreservesOrderWhenAlreadyMatchingIndex(): void
    {
        $sorted = CollectionUtils::sortByIndex($this->people(), 'id', [1, 2, 3]);

        self::assertSame([1, 2, 3], array_column($sorted, 'id'));
    }

    public function testSortByIndexWithSingleItemReturnsItUnchanged(): void
    {
        $items  = [new PersonFixture(id: 5, name: 'Eve')];
        $sorted = CollectionUtils::sortByIndex($items, 'id', [5]);

        self::assertSame([5], array_column($sorted, 'id'));
    }

    public function testSortByIndexSortsArrayItemsInReverseNaturalOrder(): void
    {
        $items = [
            ['id' => 10, 'label' => 'A'],
            ['id' => 20, 'label' => 'B'],
            ['id' => 30, 'label' => 'C'],
        ];

        $sorted = CollectionUtils::sortByIndex($items, 'id', [30, 20, 10]);

        self::assertSame([30, 20, 10], array_column($sorted, 'id'));
    }
}
