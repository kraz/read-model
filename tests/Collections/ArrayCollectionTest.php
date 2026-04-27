<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Collections;

use ArrayIterator;
use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Collections\Criteria;
use Kraz\ReadModel\Collections\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

use function iterator_to_array;
use function spl_object_hash;

#[CoversClass(ArrayCollection::class)]
final class ArrayCollectionTest extends TestCase
{
    public function testToArrayReturnsUnderlyingArray(): void
    {
        $items      = ['a' => 1, 'b' => 2];
        $collection = new ArrayCollection($items);

        self::assertSame($items, $collection->toArray());
    }

    public function testEmptyConstructor(): void
    {
        $collection = new ArrayCollection();

        self::assertSame([], $collection->toArray());
        self::assertTrue($collection->isEmpty());
        self::assertCount(0, $collection);
    }

    public function testFirstAndLast(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(1, $collection->first());
        self::assertSame(3, $collection->last());
    }

    public function testFirstAndLastReturnFalseOnEmpty(): void
    {
        $collection = new ArrayCollection();

        self::assertFalse($collection->first());
        self::assertFalse($collection->last());
    }

    public function testInternalIteratorMethods(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        $collection->first();
        self::assertSame('a', $collection->key());
        self::assertSame(1, $collection->current());

        self::assertSame(2, $collection->next());
        self::assertSame('b', $collection->key());
        self::assertSame(2, $collection->current());
    }

    public function testKeyReturnsNullWhenIteratorPastEnd(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        $collection->last();
        $collection->next();

        self::assertNull($collection->key());
    }

    public function testRemoveExistingKeyReturnsValue(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        self::assertSame(1, $collection->remove('a'));
        self::assertFalse($collection->containsKey('a'));
        self::assertSame(['b' => 2], $collection->toArray());
    }

    public function testRemoveMissingKeyReturnsNull(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        self::assertNull($collection->remove('missing'));
        self::assertSame(['a' => 1], $collection->toArray());
    }

    public function testRemoveNullValueReturnsNull(): void
    {
        $collection = new ArrayCollection(['a' => null]);

        self::assertNull($collection->remove('a'));
        self::assertFalse($collection->containsKey('a'));
    }

    public function testRemoveElementWhenPresent(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        self::assertTrue($collection->removeElement(2));
        self::assertSame(['a' => 1], $collection->toArray());
    }

    public function testRemoveElementWhenAbsent(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        self::assertFalse($collection->removeElement(99));
        self::assertSame(['a' => 1], $collection->toArray());
    }

    public function testOffsetExistsAndGet(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        self::assertTrue(isset($collection['a']));
        self::assertFalse(isset($collection['missing']));
        self::assertSame(1, $collection['a']);
        self::assertNull($collection['missing']);
    }

    public function testOffsetSetWithKey(): void
    {
        /** @var ArrayCollection<string, int> $collection */
        $collection = new ArrayCollection();

        $collection['a'] = 1;

        self::assertSame(1, $collection['a']);
    }

    public function testOffsetSetWithNullKeyAppends(): void
    {
        /** @var ArrayCollection<int, string> $collection */
        $collection = new ArrayCollection();

        $collection[] = 'first';
        $collection[] = 'second';

        self::assertSame(['first', 'second'], $collection->toArray());
    }

    public function testOffsetUnset(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        unset($collection['a']);

        self::assertFalse(isset($collection['a']));
        self::assertSame(['b' => 2], $collection->toArray());
    }

    public function testContainsKey(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => null]);

        self::assertTrue($collection->containsKey('a'));
        self::assertTrue($collection->containsKey('b'));
        self::assertFalse($collection->containsKey('missing'));
    }

    public function testContains(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => '2']);

        self::assertTrue($collection->contains(1));
        self::assertFalse($collection->contains('1'));
        self::assertFalse($collection->contains(99));
    }

    public function testExists(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertTrue($collection->exists(static fn ($key, $value): bool => $value > 2));
        self::assertFalse($collection->exists(static fn ($key, $value): bool => $value > 10));
    }

    public function testExistsReceivesKeyAndValueInOrder(): void
    {
        $collection = new ArrayCollection(['target' => 1]);

        $captured = null;
        $collection->exists(static function ($key, $value) use (&$captured): bool {
            $captured = [$key, $value];

            return false;
        });

        self::assertSame(['target', 1], $captured);
    }

    public function testIndexOfFound(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        self::assertSame('b', $collection->indexOf(2));
    }

    public function testIndexOfNotFound(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        self::assertFalse($collection->indexOf(99));
    }

    public function testGet(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        self::assertSame(1, $collection->get('a'));
        self::assertNull($collection->get('missing'));
    }

    public function testGetKeysAndValues(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        self::assertSame(['a', 'b'], $collection->getKeys());
        self::assertSame([1, 2], $collection->getValues());
    }

    public function testCount(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertCount(3, $collection);
        self::assertSame(3, $collection->count());
    }

    public function testSet(): void
    {
        $collection = new ArrayCollection();

        $collection->set('a', 1);
        $collection->set('a', 2);

        self::assertSame(2, $collection->get('a'));
    }

    public function testAddAppends(): void
    {
        $collection = new ArrayCollection();

        $collection->add('x');
        $collection->add('y');

        self::assertSame(['x', 'y'], $collection->toArray());
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new ArrayCollection())->isEmpty());
        self::assertFalse((new ArrayCollection(['a' => 1]))->isEmpty());
    }

    public function testGetIteratorPreservesKeys(): void
    {
        $items      = ['a' => 1, 'b' => 2];
        $collection = new ArrayCollection($items);

        $iterator = $collection->getIterator();
        self::assertInstanceOf(ArrayIterator::class, $iterator);
        self::assertSame($items, iterator_to_array($iterator));
    }

    public function testMap(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        $mapped = $collection->map(static fn (int $value): int => $value * 10);

        self::assertInstanceOf(ArrayCollection::class, $mapped);
        self::assertNotSame($collection, $mapped);
        self::assertSame(['a' => 10, 'b' => 20], $mapped->toArray());
        self::assertSame(['a' => 1, 'b' => 2], $collection->toArray());
    }

    public function testReduce(): void
    {
        $collection = new ArrayCollection([1, 2, 3, 4]);

        $sum = $collection->reduce(static fn (int $carry, int $value): int => $carry + $value, 0);

        self::assertSame(10, $sum);
    }

    public function testReduceUsesInitialWhenEmpty(): void
    {
        $collection = new ArrayCollection();

        $result = $collection->reduce(static fn ($carry, $value) => $value, 'initial');

        self::assertSame('initial', $result);
    }

    public function testFilter(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        $filtered = $collection->filter(static fn (int $value): bool => $value > 1);

        self::assertInstanceOf(ArrayCollection::class, $filtered);
        self::assertNotSame($collection, $filtered);
        self::assertSame(['b' => 2, 'c' => 3], $filtered->toArray());
    }

    public function testFilterReceivesValueAndKey(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        $filtered = $collection->filter(static fn (int $value, string $key): bool => $key === 'b');

        self::assertSame(['b' => 2], $filtered->toArray());
    }

    public function testFindFirstFound(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $collection->findFirst(static fn ($key, $value): bool => $value > 1);

        self::assertSame(2, $result);
    }

    public function testFindFirstNotFound(): void
    {
        $collection = new ArrayCollection(['a' => 1]);

        self::assertNull($collection->findFirst(static fn ($key, $value): bool => $value > 99));
    }

    public function testForAll(): void
    {
        $collection = new ArrayCollection([1, 2, 3]);

        self::assertTrue($collection->forAll(static fn ($key, $value): bool => $value > 0));
        self::assertFalse($collection->forAll(static fn ($key, $value): bool => $value > 1));
    }

    public function testForAllReturnsTrueOnEmpty(): void
    {
        $collection = new ArrayCollection();

        self::assertTrue($collection->forAll(static fn ($key, $value): bool => false));
    }

    public function testPartition(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);

        [$matches, $noMatches] = $collection->partition(
            static fn ($key, $value): bool => $value % 2 === 0,
        );

        self::assertInstanceOf(ArrayCollection::class, $matches);
        self::assertInstanceOf(ArrayCollection::class, $noMatches);
        self::assertSame(['b' => 2, 'd' => 4], $matches->toArray());
        self::assertSame(['a' => 1, 'c' => 3], $noMatches->toArray());
    }

    public function testToStringContainsClassNameAndHash(): void
    {
        $collection = new ArrayCollection();

        self::assertSame(ArrayCollection::class . '@' . spl_object_hash($collection), (string) $collection);
    }

    public function testClear(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        $collection->clear();

        self::assertSame([], $collection->toArray());
        self::assertTrue($collection->isEmpty());
    }

    public function testSlice(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]);

        self::assertSame(['b' => 2, 'c' => 3], $collection->slice(1, 2));
        self::assertSame(['c' => 3, 'd' => 4], $collection->slice(2));
    }

    public function testSlicePreservesKeysAndDoesNotMutate(): void
    {
        $collection = new ArrayCollection(['a' => 1, 'b' => 2]);

        $collection->slice(0, 1);

        self::assertSame(['a' => 1, 'b' => 2], $collection->toArray());
    }

    public function testMatchingFiltersByExpression(): void
    {
        $collection = new ArrayCollection([
            ['name' => 'alice', 'age' => 30],
            ['name' => 'bob', 'age' => 25],
            ['name' => 'carol', 'age' => 35],
        ]);

        $criteria = Criteria::create()->where(Criteria::expr()->gt('age', 28));

        $result = $collection->matching($criteria);

        self::assertInstanceOf(ArrayCollection::class, $result);
        self::assertCount(2, $result);
        self::assertSame(['alice', 'carol'], [$result->first()['name'], $result->last()['name']]);
    }

    public function testMatchingSortsByOrderings(): void
    {
        $collection = new ArrayCollection([
            ['name' => 'bob'],
            ['name' => 'alice'],
            ['name' => 'carol'],
        ]);

        $criteria = Criteria::create()->orderBy(['name' => Order::Ascending]);

        $sorted = $collection->matching($criteria)->getValues();

        self::assertSame(['alice', 'bob', 'carol'], [$sorted[0]['name'], $sorted[1]['name'], $sorted[2]['name']]);
    }

    public function testMatchingSortsDescending(): void
    {
        $collection = new ArrayCollection([
            ['name' => 'bob'],
            ['name' => 'alice'],
            ['name' => 'carol'],
        ]);

        $criteria = Criteria::create()->orderBy(['name' => Order::Descending]);

        $sorted = $collection->matching($criteria)->getValues();

        self::assertSame(['carol', 'bob', 'alice'], [$sorted[0]['name'], $sorted[1]['name'], $sorted[2]['name']]);
    }

    public function testMatchingAppliesOffsetAndLimit(): void
    {
        $collection = new ArrayCollection([1, 2, 3, 4, 5]);

        $criteria = Criteria::create()
            ->orderBy([])
            ->setFirstResult(1)
            ->setMaxResults(2);

        $result = $collection->matching($criteria);

        self::assertSame([1 => 2, 2 => 3], $result->toArray());
    }

    public function testMatchingWithoutAnyConstraintsReturnsCopy(): void
    {
        $items      = ['a' => 1, 'b' => 2];
        $collection = new ArrayCollection($items);

        $result = $collection->matching(Criteria::create());

        self::assertNotSame($collection, $result);
        self::assertSame($items, $result->toArray());
    }

    public function testMatchingCombinesFilterSortAndPagination(): void
    {
        $collection = new ArrayCollection([
            ['name' => 'alice', 'age' => 30],
            ['name' => 'bob', 'age' => 25],
            ['name' => 'carol', 'age' => 35],
            ['name' => 'dan', 'age' => 40],
        ]);

        $criteria = Criteria::create()
            ->where(Criteria::expr()->gte('age', 30))
            ->orderBy(['name' => Order::Descending])
            ->setFirstResult(1)
            ->setMaxResults(2);

        $result = $collection->matching($criteria)->getValues();

        self::assertCount(2, $result);
        self::assertSame('carol', $result[0]['name']);
        self::assertSame('alice', $result[1]['name']);
    }

    public function testContainsUsesStrictComparisonForObjects(): void
    {
        $a          = new stdClass();
        $b          = new stdClass();
        $collection = new ArrayCollection([$a]);

        self::assertTrue($collection->contains($a));
        self::assertFalse($collection->contains($b));
    }
}
