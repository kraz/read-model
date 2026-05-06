<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests;

use Kraz\ReadModel\DataSource;
use Kraz\ReadModel\EagerSpecificationFetcher;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use Kraz\ReadModel\Tests\Specification\Fixtures\AgeAboveSpecification;
use Kraz\ReadModel\Tests\Specification\Fixtures\NameEqualsSpecification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EagerSpecificationFetcher::class)]
final class EagerSpecificationFetcherTest extends TestCase
{
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
    // Basic filtering
    // ------------------------------------------------------------------

    public function testFetchReturnsMatchingItemsUpToLimit(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
            new PersonFixture(id: 4, name: 'Dan', age: 35),
            new PersonFixture(id: 5, name: 'Eve', age: 22),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [new AgeAboveSpecification(28)], limit: 2);

        self::assertSame([1, 3], $this->ids($result));
    }

    public function testFetchReturnsAllMatchingItemsWhenFewerThanLimit(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [new AgeAboveSpecification(28)], limit: 10);

        self::assertSame([1, 3], $this->ids($result));
    }

    public function testFetchReturnsEmptyArrayForEmptyProvider(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [new AgeAboveSpecification(28)], limit: 5);

        self::assertSame([], $result);
    }

    public function testFetchReturnsEmptyArrayWhenNoItemsSatisfySpec(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 20),
            new PersonFixture(id: 2, name: 'Bob', age: 15),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [new AgeAboveSpecification(28)], limit: 5);

        self::assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // Offset
    // ------------------------------------------------------------------

    public function testFetchRespectsOffset(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
            new PersonFixture(id: 4, name: 'Dan', age: 35),
            new PersonFixture(id: 5, name: 'Eve', age: 22),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();

        // Matching (age > 28): Alice(1), Carol(3), Dan(4) — skip first 1, collect up to 2
        $result = $fetcher->fetch($provider, [new AgeAboveSpecification(28)], limit: 2, offset: 1);

        self::assertSame([3, 4], $this->ids($result));
    }

    public function testFetchWithOffsetLargerThanMatchingCountReturnsEmpty(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();

        // Only 1 item matches (Alice), but offset=2 skips everything
        $result = $fetcher->fetch($provider, [new AgeAboveSpecification(28)], limit: 5, offset: 2);

        self::assertSame([], $result);
    }

    public function testFetchWithZeroOffsetBehavesLikeNoOffset(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcherNoOffset */
        $fetcherNoOffset = new EagerSpecificationFetcher();
        /** @var EagerSpecificationFetcher<PersonFixture> $fetcherZeroOffset */
        $fetcherZeroOffset = new EagerSpecificationFetcher();

        $withoutOffset = $fetcherNoOffset->fetch($provider, [new AgeAboveSpecification(28)], limit: 5);
        $withZero      = $fetcherZeroOffset->fetch($provider, [new AgeAboveSpecification(28)], limit: 5, offset: 0);

        self::assertSame($this->ids($withoutOffset), $this->ids($withZero));
    }

    // ------------------------------------------------------------------
    // Multiple specifications
    // ------------------------------------------------------------------

    public function testFetchWithMultipleSpecificationsRequiresAllToBeSatisfied(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
            new PersonFixture(id: 4, name: 'Alice', age: 35),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();

        // Must be age > 28 AND name = 'Alice' → only ids 1 and 4
        $result = $fetcher->fetch(
            $provider,
            [new AgeAboveSpecification(28), new NameEqualsSpecification('Alice')],
            limit: 5,
        );

        self::assertSame([1, 4], $this->ids($result));
    }

    public function testFetchWithNoSpecificationsReturnsItemsUpToLimit(): void
    {
        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 40),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [], limit: 2);

        self::assertSame([1, 2], $this->ids($result));
    }

    // ------------------------------------------------------------------
    // Multi-batch fetching
    // ------------------------------------------------------------------

    public function testFetchSpansMultipleBatchesWhenNeeded(): void
    {
        // 9 items; ages alternate low/high so matching items are spread across batches.
        // Spec: age > 25 matches positions 1, 3, 5, 7 (ids 2, 4, 6, 8).
        // limit=2, offset=1 → batchSize = max(1, 2+1) = 3
        //   Batch 1 (raw offset 0, size 3): ids 1,2,3 → 1 match (id 2) → skip 1 (offset exhausted)
        //   Batch 2 (raw offset 3, size 3): ids 4,5,6 → 2 matches (id 4, id 6) → collect both → done
        // Expected result: ids [4, 6]

        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource([
            new PersonFixture(id: 1, age: 10),
            new PersonFixture(id: 2, age: 40),
            new PersonFixture(id: 3, age: 10),
            new PersonFixture(id: 4, age: 40),
            new PersonFixture(id: 5, age: 10),
            new PersonFixture(id: 6, age: 40),
            new PersonFixture(id: 7, age: 10),
            new PersonFixture(id: 8, age: 40),
            new PersonFixture(id: 9, age: 10),
        ]);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [new AgeAboveSpecification(25)], limit: 2, offset: 1);

        self::assertSame([4, 6], $this->ids($result));
    }

    public function testFetchStopsAfterLimitIsReachedWithoutExhaustingAllData(): void
    {
        // 100 items; all match the spec. limit=3 → should only return first 3.
        /** @var list<PersonFixture> $people */
        $people = [];
        for ($i = 1; $i <= 100; $i++) {
            $people[] = new PersonFixture(id: $i, age: 50);
        }

        /** @var DataSource<PersonFixture> $provider */
        $provider = new DataSource($people);

        /** @var EagerSpecificationFetcher<PersonFixture> $fetcher */
        $fetcher = new EagerSpecificationFetcher();
        $result  = $fetcher->fetch($provider, [new AgeAboveSpecification(25)], limit: 3);

        self::assertSame([1, 2, 3], $this->ids($result));
    }
}
