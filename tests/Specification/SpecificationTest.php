<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Specification;

use ArrayIterator;
use Kraz\ReadModel\DataSource;
use Kraz\ReadModel\Pagination\InMemoryPaginator;
use Kraz\ReadModel\Specification\AbstractSpecification;
use Kraz\ReadModel\Specification\CompositeAndSpecification;
use Kraz\ReadModel\Specification\CompositeOrSpecification;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use Kraz\ReadModel\Tests\Specification\Fixtures\AgeAboveSpecification;
use Kraz\ReadModel\Tests\Specification\Fixtures\NameEqualsSpecification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(AbstractSpecification::class)]
#[CoversClass(CompositeAndSpecification::class)]
#[CoversClass(CompositeOrSpecification::class)]
final class SpecificationTest extends TestCase
{
    /** @return list<PersonFixture> */
    private function people(): array
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
    // AbstractSpecification basics
    // ------------------------------------------------------------------

    public function testIsSatisfiedByReturnsTrueWhenConditionMet(): void
    {
        $spec  = new AgeAboveSpecification(28);
        $alice = new PersonFixture(id: 1, name: 'Alice', age: 30);

        self::assertTrue($spec->isSatisfiedBy($alice));
    }

    public function testIsSatisfiedByReturnsFalseWhenConditionNotMet(): void
    {
        $spec = new AgeAboveSpecification(28);
        $bob  = new PersonFixture(id: 2, name: 'Bob', age: 25);

        self::assertFalse($spec->isSatisfiedBy($bob));
    }

    public function testInvertedFlagIsFalseByDefault(): void
    {
        self::assertFalse((new AgeAboveSpecification(28))->inverted());
    }

    public function testInvertFlipsInvertedFlag(): void
    {
        $inverted = (new AgeAboveSpecification(28))->invert();

        self::assertTrue($inverted->inverted());
    }

    public function testInvertTwiceRestoresOriginalFlag(): void
    {
        $spec = (new AgeAboveSpecification(28))->invert()->invert();

        self::assertFalse($spec->inverted());
    }

    public function testInvertedSpecIsSatisfiedByOppositeItems(): void
    {
        $inverted = (new AgeAboveSpecification(28))->invert();
        $alice    = new PersonFixture(id: 1, age: 30);
        $bob      = new PersonFixture(id: 2, age: 25);

        self::assertFalse($inverted->isSatisfiedBy($alice));
        self::assertTrue($inverted->isSatisfiedBy($bob));
    }

    // ------------------------------------------------------------------
    // getQueryExpression
    // ------------------------------------------------------------------

    public function testGetQueryExpressionReturnsNullWhenNotOverridden(): void
    {
        $spec = new NameEqualsSpecification('Alice');

        self::assertNull($spec->getQueryExpression());
    }

    public function testGetQueryExpressionReturnsExpressionWhenOverridden(): void
    {
        $spec = new AgeAboveSpecification(28);

        self::assertNotNull($spec->getQueryExpression());
    }

    public function testGetQueryExpressionIsInvertedWhenSpecIsInverted(): void
    {
        $spec = new AgeAboveSpecification(28);

        /** @var SpecificationInterface<PersonFixture> $inverted */
        $inverted = $spec->invert();

        $original = $spec->getQueryExpression();
        $inv      = $inverted->getQueryExpression();

        self::assertNotNull($original);
        self::assertNotNull($inv);
        self::assertNotSame($original->toArray(), $inv->toArray());
    }

    // ------------------------------------------------------------------
    // Composition: and / andNot / or / orNot
    // ------------------------------------------------------------------

    public function testAndComposesAsTrueWhenBothSatisfied(): void
    {
        $spec  = (new AgeAboveSpecification(28))->and(new NameEqualsSpecification('Alice'));
        $alice = new PersonFixture(id: 1, name: 'Alice', age: 30);
        $carol = new PersonFixture(id: 3, name: 'Carol', age: 40);

        self::assertTrue($spec->isSatisfiedBy($alice));
        self::assertFalse($spec->isSatisfiedBy($carol));
    }

    public function testOrComposesAsTrueWhenEitherSatisfied(): void
    {
        $spec  = (new AgeAboveSpecification(38))->or(new NameEqualsSpecification('Alice'));
        $alice = new PersonFixture(id: 1, name: 'Alice', age: 30);
        $carol = new PersonFixture(id: 3, name: 'Carol', age: 40);
        $bob   = new PersonFixture(id: 2, name: 'Bob', age: 25);

        self::assertTrue($spec->isSatisfiedBy($alice));
        self::assertTrue($spec->isSatisfiedBy($carol));
        self::assertFalse($spec->isSatisfiedBy($bob));
    }

    public function testAndNotComposesAsTrueWhenFirstSatisfiedAndSecondNot(): void
    {
        $spec  = (new AgeAboveSpecification(28))->andNot(new NameEqualsSpecification('Alice'));
        $alice = new PersonFixture(id: 1, name: 'Alice', age: 30);
        $carol = new PersonFixture(id: 3, name: 'Carol', age: 40);
        $bob   = new PersonFixture(id: 2, name: 'Bob', age: 25);

        self::assertFalse($spec->isSatisfiedBy($alice));
        self::assertTrue($spec->isSatisfiedBy($carol));
        self::assertFalse($spec->isSatisfiedBy($bob));
    }

    public function testOrNotComposesAsTrueWhenFirstSatisfiedOrSecondNot(): void
    {
        $spec  = (new AgeAboveSpecification(38))->orNot(new NameEqualsSpecification('Alice'));
        $bob   = new PersonFixture(id: 2, name: 'Bob', age: 25);
        $carol = new PersonFixture(id: 3, name: 'Carol', age: 40);

        self::assertTrue($spec->isSatisfiedBy($carol));
        self::assertTrue($spec->isSatisfiedBy($bob));
    }

    // ------------------------------------------------------------------
    // CompositeAndSpecification
    // ------------------------------------------------------------------

    public function testCompositeAndReturnsTrueWhenAllSpecificationsSatisfied(): void
    {
        /** @phpstan-var CompositeAndSpecification<PersonFixture> $composite */
        $composite = new CompositeAndSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(28), new NameEqualsSpecification('Alice'));

        $alice = new PersonFixture(id: 1, name: 'Alice', age: 30);
        $carol = new PersonFixture(id: 3, name: 'Carol', age: 40);

        self::assertTrue($spec->isSatisfiedBy($alice));
        self::assertFalse($spec->isSatisfiedBy($carol));
    }

    public function testCompositeAndReturnsFalseWhenAnySpecificationFails(): void
    {
        /** @phpstan-var CompositeAndSpecification<PersonFixture> $composite */
        $composite = new CompositeAndSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(28), new NameEqualsSpecification('Alice'));

        $bob = new PersonFixture(id: 2, name: 'Bob', age: 25);

        self::assertFalse($spec->isSatisfiedBy($bob));
    }

    public function testCompositeAndInvertedReturnsTrueWhenAnyFails(): void
    {
        /** @phpstan-var CompositeAndSpecification<PersonFixture> $composite */
        $composite = new CompositeAndSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(28), new NameEqualsSpecification('Alice'));
        $inverted  = $spec->invert();
        $carol     = new PersonFixture(id: 3, name: 'Carol', age: 40);

        self::assertTrue($inverted->isSatisfiedBy($carol));
    }

    public function testCompositeAndBuildQueryExpressionWrapsWithAnd(): void
    {
        /** @phpstan-var CompositeAndSpecification<PersonFixture> $composite */
        $composite = new CompositeAndSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(28));

        $qe = $spec->getQueryExpression();

        self::assertNotNull($qe);
        self::assertFalse($qe->isEmpty());
    }

    public function testCompositeAndBuildQueryExpressionWithNoQuerySpecsReturnsNull(): void
    {
        /** @phpstan-var CompositeAndSpecification<PersonFixture> $composite */
        $composite = new CompositeAndSpecification();
        $spec      = $composite->with(new NameEqualsSpecification('Alice'));

        self::assertNull($spec->getQueryExpression());
    }

    public function testCompositeAndBuildQueryExpressionReturnsNullWhenAnySpecLacksQueryExpression(): void
    {
        /** @phpstan-var CompositeAndSpecification<PersonFixture> $composite */
        $composite = new CompositeAndSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(28), new NameEqualsSpecification('Alice'));

        self::assertNull($spec->getQueryExpression());
    }

    // ------------------------------------------------------------------
    // CompositeOrSpecification
    // ------------------------------------------------------------------

    public function testCompositeOrReturnsTrueWhenAnySpecificationSatisfied(): void
    {
        /** @phpstan-var CompositeOrSpecification<PersonFixture> $composite */
        $composite = new CompositeOrSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(38), new NameEqualsSpecification('Alice'));

        $alice = new PersonFixture(id: 1, name: 'Alice', age: 30);
        $carol = new PersonFixture(id: 3, name: 'Carol', age: 40);
        $bob   = new PersonFixture(id: 2, name: 'Bob', age: 25);

        self::assertTrue($spec->isSatisfiedBy($alice));
        self::assertTrue($spec->isSatisfiedBy($carol));
        self::assertFalse($spec->isSatisfiedBy($bob));
    }

    public function testCompositeOrReturnsFalseWhenNoSpecificationSatisfied(): void
    {
        /** @phpstan-var CompositeOrSpecification<PersonFixture> $composite */
        $composite = new CompositeOrSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(38), new NameEqualsSpecification('Alice'));

        $bob = new PersonFixture(id: 2, name: 'Bob', age: 25);

        self::assertFalse($spec->isSatisfiedBy($bob));
    }

    public function testCompositeOrInvertedReturnsFalseWhenAnySatisfied(): void
    {
        /** @phpstan-var CompositeOrSpecification<PersonFixture> $composite */
        $composite = new CompositeOrSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(38), new NameEqualsSpecification('Alice'));
        $inverted  = $spec->invert();
        $alice     = new PersonFixture(id: 1, name: 'Alice', age: 30);

        self::assertFalse($inverted->isSatisfiedBy($alice));
    }

    public function testCompositeOrBuildQueryExpressionWrapsWithOr(): void
    {
        /** @phpstan-var CompositeOrSpecification<PersonFixture> $composite */
        $composite = new CompositeOrSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(28));

        $qe = $spec->getQueryExpression();

        self::assertNotNull($qe);
        self::assertFalse($qe->isEmpty());
    }

    public function testCompositeOrBuildQueryExpressionReturnsNullWhenAnySpecLacksQueryExpression(): void
    {
        /** @phpstan-var CompositeOrSpecification<PersonFixture> $composite */
        $composite = new CompositeOrSpecification();
        $spec      = $composite->with(new AgeAboveSpecification(38), new NameEqualsSpecification('Alice'));

        self::assertNull($spec->getQueryExpression());
    }

    // ------------------------------------------------------------------
    // DataSource integration: withSpecification
    // ------------------------------------------------------------------

    public function testWithSpecificationFiltersItemsViaIsSatisfiedBy(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($this->people());
        $filtered = $ds->withSpecification(new AgeAboveSpecification(28));

        self::assertSame([1, 3, 4], $this->ids($filtered));
    }

    public function testWithSpecificationCountMatchesFilteredItems(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($this->people());
        $filtered = $ds->withSpecification(new AgeAboveSpecification(28));

        self::assertSame(3, $filtered->count());
        self::assertSame(3, $filtered->totalCount());
    }

    public function testWithSpecificationDoesNotMutateOriginal(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $ds->withSpecification(new AgeAboveSpecification(28));

        self::assertSame([1, 2, 3, 4, 5], $this->ids($ds));
    }

    public function testWithSpecificationReturnsNewInstance(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        self::assertNotSame($ds, $ds->withSpecification(new AgeAboveSpecification(28)));
    }

    public function testMultipleSpecificationsAreCombinedWithAnd(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $filtered = $ds
            ->withSpecification(new AgeAboveSpecification(28))
            ->withSpecification(new NameEqualsSpecification('Alice'));

        self::assertSame([1], $this->ids($filtered));
    }

    public function testWithoutSpecificationUndoesLastSpecification(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $filtered = $ds->withSpecification(new AgeAboveSpecification(28));
        $restored = $filtered->withoutSpecification(true);

        self::assertSame([1, 2, 3, 4, 5], $this->ids($restored));
    }

    public function testWithoutSpecificationRestoresPreviousSpecificationStack(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $first  = $ds->withSpecification(new AgeAboveSpecification(28));
        $second = $first->withSpecification(new NameEqualsSpecification('Alice'));
        $undone = $second->withoutSpecification(true);

        self::assertSame([1, 3, 4], $this->ids($undone));
    }

    public function testWithoutSpecificationDefaultClearsAll(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $filtered = $ds
            ->withSpecification(new AgeAboveSpecification(28))
            ->withSpecification(new NameEqualsSpecification('Alice'));
        $cleared  = $filtered->withoutSpecification();

        self::assertSame([1, 2, 3, 4, 5], $this->ids($cleared));
    }

    public function testWithoutSpecificationDefaultAlsoClearsHistory(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $filtered  = $ds->withSpecification(new AgeAboveSpecification(28));
        $cleared   = $filtered->withoutSpecification();
        $afterUndo = $cleared->withoutSpecification(true);

        self::assertSame([1, 2, 3, 4, 5], $this->ids($afterUndo));
    }

    public function testWithoutSpecificationOnFreshDataSourceIsNoOp(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds      = new DataSource($this->people());
        $cleared = $ds->withoutSpecification();

        self::assertSame([1, 2, 3, 4, 5], $this->ids($cleared));
    }

    public function testInvertedSpecificationFiltersOppositeItems(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($this->people());
        $inverted = (new AgeAboveSpecification(28))->invert();
        $filtered = $ds->withSpecification($inverted);

        self::assertSame([2, 5], $this->ids($filtered));
    }

    public function testSpecificationWithoutQueryExpressionStillFiltersViaIsSatisfiedBy(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($this->people());
        $filtered = $ds->withSpecification(new NameEqualsSpecification('Carol'));

        self::assertSame([3], $this->ids($filtered));
    }

    public function testWithSpecificationWorksWithPagination(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds     = new DataSource($this->people());
        $result = $ds->withSpecification(new AgeAboveSpecification(28))->withPagination(1, 2);

        self::assertTrue($result->isPaginated());
        self::assertSame([1, 3], $this->ids($result));
        self::assertSame(3, $result->totalCount());
    }

    public function testCompositeAndSpecificationViaAndMethodFiltersCorrectly(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($this->people());
        $spec     = (new AgeAboveSpecification(28))->and(new NameEqualsSpecification('Carol'));
        $filtered = $ds->withSpecification($spec);

        self::assertSame([3], $this->ids($filtered));
    }

    public function testCompositeOrSpecificationViaOrMethodFiltersCorrectly(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($this->people());
        $spec     = (new AgeAboveSpecification(38))->or(new NameEqualsSpecification('Alice'));
        $filtered = $ds->withSpecification($spec);

        self::assertSame([1, 3], $this->ids($filtered));
    }

    public function testWithSpecificationCombinedWithQueryExpression(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry      = $ds->qry();
        $qry      = $qry->andWhere($qry->expr()->greaterThan('age', 24));
        $filtered = $ds->withQueryExpression($qry)->withSpecification(new NameEqualsSpecification('Alice'));

        self::assertSame([1], $this->ids($filtered));
    }

    public function testSpecificationQueryExpressionIsAppliedBeforeQueryExpression(): void
    {
        /** @var DataSource<PersonFixture> $ds */
        $ds = new DataSource($this->people());

        $qry      = $ds->qry();
        $qry      = $qry->andWhere($qry->expr()->greaterThan('age', 24));
        $filtered = $ds->withSpecification(new AgeAboveSpecification(28))->withQueryExpression($qry);

        // spec QE (age > 28) runs first, then regular QE (age > 24) — result is age > 28
        self::assertSame([1, 3, 4], $this->ids($filtered));
    }

    public function testWithSpecificationFiltersWhenDataIsWrappedPaginator(): void
    {
        $people    = $this->people();
        $paginator = new InMemoryPaginator(
            new ArrayIterator($people),
            count($people),
            1,
            10,
        );

        /** @var DataSource<PersonFixture> $ds */
        $ds       = new DataSource($paginator);
        $filtered = $ds->withSpecification(new AgeAboveSpecification(28));

        self::assertFalse($filtered->isPaginated());
        self::assertSame([1, 3, 4], $this->ids($filtered));
        self::assertSame(3, $filtered->count());
        self::assertSame(3, $filtered->totalCount());
    }
}
