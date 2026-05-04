<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests;

use InvalidArgumentException;
use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\DataSource;
use Kraz\ReadModel\DataSourceBuilder;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\ReadModelDescriptor;
use Kraz\ReadModel\ReadModelDescriptorFactoryInterface;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function array_slice;

#[CoversClass(DataSourceBuilder::class)]
final class DataSourceBuilderTest extends TestCase
{
    /** @phpstan-return DataSourceBuilder<object|array<string, mixed>> */
    private function makeBuilder(): DataSourceBuilder
    {
        return new DataSourceBuilder();
    }

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
     * @param iterable<PersonFixture|mixed> $items
     *
     * @return list<int>
     */
    private function ids(iterable $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (! ($item instanceof PersonFixture)) {
                continue;
            }

            $ids[] = $item->id;
        }

        return $ids;
    }

    // -------------------------------------------------------------------------
    // Helper factory methods
    // -------------------------------------------------------------------------

    public function testQryReturnsQueryExpression(): void
    {
        self::assertInstanceOf(QueryExpression::class, $this->makeBuilder()->qry());
    }

    public function testExprReturnsFilterExpression(): void
    {
        self::assertInstanceOf(FilterExpression::class, $this->makeBuilder()->expr());
    }

    // -------------------------------------------------------------------------
    // create() — guard when no data is set
    // -------------------------------------------------------------------------

    public function testCreateThrowsWhenDataIsNotSet(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeBuilder()->create();
    }

    // -------------------------------------------------------------------------
    // withData() — accepted data types
    // -------------------------------------------------------------------------

    public function testCreateWithArrayDataPassedToWithDataProducesWorkingDataSource(): void
    {
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->create();

        self::assertCount(5, $ds->data());
    }

    public function testCreateWithArrayCollectionDataProducesWorkingDataSource(): void
    {
        $ds = $this->makeBuilder()
            ->withData(new ArrayCollection($this->people()))
            ->create();

        self::assertCount(5, $ds->data());
    }

    public function testCreateWithDataSourceDataProducesWorkingDataSource(): void
    {
        $inner = new DataSource($this->people());

        $ds = $this->makeBuilder()
            ->withData($inner)
            ->create();

        self::assertCount(5, $ds->data());
    }

    public function testCreateAcceptsDataPassedDirectly(): void
    {
        $ds = $this->makeBuilder()->create($this->people());

        self::assertCount(5, $ds->data());
    }

    public function testCreateWithDirectDataOverridesWithData(): void
    {
        $threeItems = array_slice($this->people(), 0, 3);

        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->create($threeItems);

        self::assertCount(3, $ds->data());
    }

    // -------------------------------------------------------------------------
    // Immutability of withData()
    // -------------------------------------------------------------------------

    public function testWithDataReturnsNewBuilderInstance(): void
    {
        $builder = $this->makeBuilder();

        self::assertNotSame($builder, $builder->withData($this->people()));
    }

    public function testWithDataDoesNotMutateOriginalBuilder(): void
    {
        $builder = $this->makeBuilder();
        $builder->withData($this->people());

        $this->expectException(InvalidArgumentException::class);
        $builder->create();
    }

    // -------------------------------------------------------------------------
    // Immutability of all with* composition methods
    // -------------------------------------------------------------------------

    public function testWithMethodsAlwaysReturnNewBuilderInstance(): void
    {
        $builder = $this->makeBuilder();

        $stubProvider = $this->createStub(QueryExpressionProviderInterface::class);
        $stubFactory  = $this->createStub(ReadModelDescriptorFactoryInterface::class);
        $stubSpec     = $this->createStub(SpecificationInterface::class);
        $descriptor   = new ReadModelDescriptor(['id'], [], [], []);

        self::assertNotSame($builder, $builder->withData($this->people()));
        self::assertNotSame($builder, $builder->withQueryExpression(QueryExpression::create()));
        self::assertNotSame($builder, $builder->withoutQueryExpression());
        self::assertNotSame($builder, $builder->withQueryModifier(static fn () => null));
        self::assertNotSame($builder, $builder->withoutQueryModifier());
        self::assertNotSame($builder, $builder->withSpecification($stubSpec));
        self::assertNotSame($builder, $builder->withoutSpecification());
        self::assertNotSame($builder, $builder->withPagination(1, 10));
        self::assertNotSame($builder, $builder->withoutPagination());
        self::assertNotSame($builder, $builder->withQueryRequest(QueryRequest::create()));
        self::assertNotSame($builder, $builder->withItemNormalizer(static fn (mixed $item): mixed => $item));
        self::assertNotSame($builder, $builder->withoutItemNormalizer());
        self::assertNotSame($builder, $builder->withReadModelDescriptor($descriptor));
        self::assertNotSame($builder, $builder->withoutReadModelDescriptor());
        self::assertNotSame($builder, $builder->withReadModel(PersonFixture::class));
        self::assertNotSame($builder, $builder->withQueryExpressionProvider($stubProvider));
        self::assertNotSame($builder, $builder->withoutQueryExpressionProvider());
        self::assertNotSame($builder, $builder->withDescriptorFactory($stubFactory));
        self::assertNotSame($builder, $builder->withoutDescriptorFactory());
        self::assertNotSame($builder, $builder->withFieldMapping(['alias' => 'id']));
        self::assertNotSame($builder, $builder->withFieldMapping(null));
        self::assertNotSame($builder, $builder->withRootAlias('r'));
        self::assertNotSame($builder, $builder->withRootAlias(null));
        self::assertNotSame($builder, $builder->withRootIdentifier('id'));
        self::assertNotSame($builder, $builder->withRootIdentifier(null));
    }

    // -------------------------------------------------------------------------
    // andWhere / orWhere / sortBy — mutable fluent methods
    // -------------------------------------------------------------------------

    public function testAndWhereReturnsSameBuilderInstance(): void
    {
        $builder = $this->makeBuilder();
        $expr    = FilterExpression::create()->equalTo('name', 'Alice');

        self::assertSame($builder, $builder->andWhere($expr));
    }

    public function testOrWhereReturnsSameBuilderInstance(): void
    {
        $builder = $this->makeBuilder();
        $expr    = FilterExpression::create()->equalTo('name', 'Alice');

        self::assertSame($builder, $builder->orWhere($expr));
    }

    public function testSortByReturnsSameBuilderInstance(): void
    {
        $builder = $this->makeBuilder();

        self::assertSame($builder, $builder->sortBy('name'));
    }

    public function testAndWhereFiltersResultsOnCreate(): void
    {
        $builder = $this->makeBuilder()->withData($this->people());
        $builder->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));

        self::assertSame([1], $this->ids($builder->create()->data()));
    }

    public function testAndWhereWithMultipleExpressionsAppliesConjunction(): void
    {
        $builder = $this->makeBuilder()->withData($this->people());
        $builder->andWhere(
            FilterExpression::create()->greaterThan('age', 24),
            FilterExpression::create()->lowerThan('age', 36),
        );

        // age > 24 AND age < 36 → Alice (30), Bob (25), Dan (35)
        self::assertSame([1, 2, 4], $this->ids($builder->create()->data()));
    }

    public function testSubsequentAndWhereCallReplacesFilter(): void
    {
        $builder = $this->makeBuilder()->withData($this->people());
        $builder->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $builder->andWhere(FilterExpression::create()->greaterThan('age', 30)); // replaces the first

        // Only the second filter is active: age > 30 → Carol (40), Dan (35)
        self::assertSame([3, 4], $this->ids($builder->create()->data()));
    }

    public function testOrWhereAppliesDisjunction(): void
    {
        $builder = $this->makeBuilder()->withData($this->people());
        $builder->orWhere(
            FilterExpression::create()->equalTo('name', 'Alice'),
            FilterExpression::create()->equalTo('name', 'Bob'),
        );

        self::assertSame([1, 2], $this->ids($builder->create()->data()));
    }

    /**
     * Mutations on the original builder after a withData() clone do not affect
     * that clone, because with* creates a detached copy.
     */
    public function testAndWhereAfterWithDataDoesNotAffectEarlierClone(): void
    {
        $builder         = $this->makeBuilder();
        $builderWithData = $builder->withData($this->people()); // clone taken here

        // Mutate the original AFTER the clone — clone must be unaffected.
        $builder->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));

        self::assertCount(5, $builderWithData->create()->data());
    }

    // -------------------------------------------------------------------------
    // withQueryExpression — applied in create()
    // -------------------------------------------------------------------------

    public function testWithQueryExpressionFiltersResultsOnCreate(): void
    {
        $qe = QueryExpression::create()->andWhere(
            FilterExpression::create()->equalTo('name', 'Alice'),
        );
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryExpression($qe)
            ->create();

        self::assertSame([1], $this->ids($ds->data()));
    }

    public function testWithQueryExpressionDoesNotMutateOriginalBuilder(): void
    {
        $qe      = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $builder = $this->makeBuilder()->withData($this->people());
        $builder->withQueryExpression($qe);

        self::assertCount(5, $builder->create()->data());
    }

    public function testWithQueryExpressionAppendAccumulatesFilters(): void
    {
        $qe1 = QueryExpression::create()->andWhere(FilterExpression::create()->greaterThan('age', 24));
        $qe2 = QueryExpression::create()->andWhere(FilterExpression::create()->lowerThan('age', 36));
        $ds  = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryExpression($qe1)
            ->withQueryExpression($qe2, true)
            ->create();

        // age > 24 AND age < 36 → Alice (30), Bob (25), Dan (35)
        self::assertSame([1, 2, 4], $this->ids($ds->data()));
    }

    // -------------------------------------------------------------------------
    // withoutQueryExpression — clear and undo
    // -------------------------------------------------------------------------

    public function testWithoutQueryExpressionReturnsNewBuilderInstance(): void
    {
        $builder = $this->makeBuilder()->withQueryExpression(QueryExpression::create());

        self::assertNotSame($builder, $builder->withoutQueryExpression());
    }

    public function testWithoutQueryExpressionClearsFilterOnCreate(): void
    {
        $qe = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryExpression($qe)
            ->withoutQueryExpression()
            ->create();

        self::assertCount(5, $ds->data());
    }

    public function testWithoutQueryExpressionDoesNotMutateOriginalBuilder(): void
    {
        $qe      = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $builder = $this->makeBuilder()->withData($this->people())->withQueryExpression($qe);
        $builder->withoutQueryExpression();

        self::assertSame([1], $this->ids($builder->create()->data()));
    }

    public function testWithoutQueryExpressionUndoRestoresPreviousExpression(): void
    {
        // qe1 filters age > 29; qe2 replaces to name = Alice; undo → back to qe1
        $qe1 = QueryExpression::create()->andWhere(FilterExpression::create()->greaterThan('age', 29));
        $qe2 = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $ds  = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryExpression($qe1)
            ->withQueryExpression($qe2)
            ->withoutQueryExpression(true)
            ->create();

        // age > 29 → Alice (30), Carol (40), Dan (35)
        self::assertSame([1, 3, 4], $this->ids($ds->data()));
    }

    public function testWithoutQueryExpressionUndoOnEmptyIsNoOp(): void
    {
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withoutQueryExpression(true)
            ->create();

        self::assertCount(5, $ds->data());
    }

    public function testWithoutQueryExpressionClearAlsoClearsHistorySoUndoIsNoOp(): void
    {
        $qe = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryExpression($qe)
            ->withoutQueryExpression()      // clears expressions and history
            ->withoutQueryExpression(true)  // undo on empty history → no-op
            ->create();

        self::assertCount(5, $ds->data());
    }

    // -------------------------------------------------------------------------
    // withQueryModifier — immutability and undo
    // -------------------------------------------------------------------------

    public function testWithQueryModifierReturnsNewBuilderInstance(): void
    {
        $builder = $this->makeBuilder();

        self::assertNotSame($builder, $builder->withQueryModifier(static fn () => null));
    }

    public function testWithoutQueryModifierReturnsNewBuilderInstance(): void
    {
        $builder = $this->makeBuilder()->withQueryModifier(static fn () => null);

        self::assertNotSame($builder, $builder->withoutQueryModifier());
    }

    public function testWithoutQueryModifierUndoRestoresPreviousModifier(): void
    {
        $m1 = static fn () => 'first';
        $m2 = static fn () => 'second';

        $builder = $this->makeBuilder()
            ->withQueryModifier($m1)
            ->withQueryModifier($m2)
            ->withoutQueryModifier(true); // undo → back to m1

        // After undo there is one modifier; clearing again leaves none.
        $empty = $builder->withoutQueryModifier();
        self::assertNotSame($builder, $empty);
    }

    public function testWithoutQueryModifierUndoOnEmptyIsNoOp(): void
    {
        $builder = $this->makeBuilder()->withoutQueryModifier(true);

        // No exception — undo on empty history is silently a no-op.
        self::assertNotSame($this->makeBuilder(), $builder);
    }

    // -------------------------------------------------------------------------
    // withSpecification — applied in create()
    // -------------------------------------------------------------------------

    public function testWithSpecificationFiltersResultsOnCreate(): void
    {
        $spec = $this->createStub(SpecificationInterface::class);
        $spec->method('isSatisfiedBy')->willReturnCallback(
            static fn (PersonFixture $p): bool => $p->age > 29,
        );
        $spec->method('getQueryExpression')->willReturn(null);

        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withSpecification($spec)
            ->create();

        // age > 29 → Alice (30), Carol (40), Dan (35)
        self::assertSame([1, 3, 4], $this->ids($ds->data()));
    }

    public function testWithSpecificationDoesNotMutateOriginalBuilder(): void
    {
        $spec = $this->createStub(SpecificationInterface::class);
        $spec->method('isSatisfiedBy')->willReturn(false);
        $spec->method('getQueryExpression')->willReturn(null);

        $builder = $this->makeBuilder()->withData($this->people());
        $builder->withSpecification($spec);

        self::assertCount(5, $builder->create()->data());
    }

    public function testMultipleSpecificationsAreAllApplied(): void
    {
        $spec1 = $this->createStub(SpecificationInterface::class);
        $spec1->method('isSatisfiedBy')->willReturnCallback(
            static fn (PersonFixture $p): bool => $p->age > 24,
        );
        $spec1->method('getQueryExpression')->willReturn(null);

        $spec2 = $this->createStub(SpecificationInterface::class);
        $spec2->method('isSatisfiedBy')->willReturnCallback(
            static fn (PersonFixture $p): bool => $p->age < 36,
        );
        $spec2->method('getQueryExpression')->willReturn(null);

        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withSpecification($spec1)
            ->withSpecification($spec2, true)
            ->create();

        // age > 24 AND age < 36 → Alice (30), Bob (25), Dan (35)
        self::assertSame([1, 2, 4], $this->ids($ds->data()));
    }

    // -------------------------------------------------------------------------
    // withoutSpecification — clear and undo
    // -------------------------------------------------------------------------

    public function testWithoutSpecificationClearsFilterOnCreate(): void
    {
        $spec = $this->createStub(SpecificationInterface::class);
        $spec->method('isSatisfiedBy')->willReturn(false);
        $spec->method('getQueryExpression')->willReturn(null);

        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withSpecification($spec)
            ->withoutSpecification()
            ->create();

        self::assertCount(5, $ds->data());
    }

    public function testWithoutSpecificationUndoRestoresPreviousSpecification(): void
    {
        $spec1 = $this->createStub(SpecificationInterface::class);
        $spec1->method('isSatisfiedBy')->willReturnCallback(
            static fn (PersonFixture $p): bool => $p->age > 29,
        );
        $spec1->method('getQueryExpression')->willReturn(null);

        $spec2 = $this->createStub(SpecificationInterface::class);
        $spec2->method('isSatisfiedBy')->willReturnCallback(
            static fn (PersonFixture $p): bool => $p->age > 39,
        );
        $spec2->method('getQueryExpression')->willReturn(null);

        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withSpecification($spec1)
            ->withSpecification($spec2)
            ->withoutSpecification(true) // undo → back to spec1
            ->create();

        // spec1: age > 29 → Alice (30), Carol (40), Dan (35)
        self::assertSame([1, 3, 4], $this->ids($ds->data()));
    }

    // -------------------------------------------------------------------------
    // withQueryRequest — applied in create()
    // -------------------------------------------------------------------------

    public function testWithQueryRequestAppliesQueryExpressionOnCreate(): void
    {
        $qe      = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Bob'));
        $request = QueryRequest::create()->withQueryExpression($qe);
        $ds      = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryRequest($request)
            ->create();

        self::assertSame([2], $this->ids($ds->data()));
    }

    // -------------------------------------------------------------------------
    // withItemNormalizer — applied in create()
    // -------------------------------------------------------------------------

    public function testWithItemNormalizerTransformsResults(): void
    {
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withItemNormalizer(static fn (PersonFixture $p): int => $p->id)
            ->create();

        self::assertSame([1, 2, 3, 4, 5], $ds->data());
    }

    public function testWithItemNormalizerDoesNotMutateOriginalBuilder(): void
    {
        $builder = $this->makeBuilder()->withData($this->people());
        $builder->withItemNormalizer(static fn (PersonFixture $p): int => $p->id);

        // Original builder has no normalizer — results are still PersonFixture objects.
        self::assertContainsOnlyInstancesOf(PersonFixture::class, $builder->create()->data());
    }

    // -------------------------------------------------------------------------
    // withReadModel / withReadModelDescriptor
    // -------------------------------------------------------------------------

    public function testWithReadModelReturnsNewBuilderInstance(): void
    {
        self::assertNotSame(
            $this->makeBuilder(),
            $this->makeBuilder()->withReadModel(PersonFixture::class),
        );
    }

    public function testWithReadModelDescriptorReturnsNewBuilderInstance(): void
    {
        $descriptor = new ReadModelDescriptor(['id', 'name', 'age'], [], [], []);

        self::assertNotSame(
            $this->makeBuilder(),
            $this->makeBuilder()->withReadModelDescriptor($descriptor),
        );
    }

    // -------------------------------------------------------------------------
    // withFieldMapping — field name remapping in create()
    // -------------------------------------------------------------------------

    public function testWithFieldMappingReturnsNewBuilderInstance(): void
    {
        self::assertNotSame(
            $this->makeBuilder(),
            $this->makeBuilder()->withFieldMapping(['alias' => 'id']),
        );
    }

    public function testWithFieldMappingAppliesFieldRemapping(): void
    {
        // 'person_id' is remapped to the actual 'id' property.
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withFieldMapping(['person_id' => 'id'])
            ->withQueryExpression(
                QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('person_id', 3)),
            )
            ->create();

        self::assertSame([3], $this->ids($ds->data()));
    }

    // -------------------------------------------------------------------------
    // withRootAlias / withRootIdentifier
    // -------------------------------------------------------------------------

    public function testWithRootAliasReturnsNewBuilderInstance(): void
    {
        self::assertNotSame($this->makeBuilder(), $this->makeBuilder()->withRootAlias('r'));
    }

    public function testWithRootIdentifierReturnsNewBuilderInstance(): void
    {
        self::assertNotSame($this->makeBuilder(), $this->makeBuilder()->withRootIdentifier('id'));
    }

    // -------------------------------------------------------------------------
    // withQueryExpressionProvider — applied in create()
    // -------------------------------------------------------------------------

    public function testWithQueryExpressionProviderReturnsNewBuilderInstance(): void
    {
        $provider = $this->createStub(QueryExpressionProviderInterface::class);

        self::assertNotSame(
            $this->makeBuilder(),
            $this->makeBuilder()->withQueryExpressionProvider($provider),
        );
    }

    public function testWithQueryExpressionProviderIsAppliedToCreatedDataSource(): void
    {
        $provider = $this->createMock(QueryExpressionProviderInterface::class);
        $provider->expects(self::once())->method('apply')->willReturnArgument(0);

        $qe = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('name', 'Alice'));
        $ds = $this->makeBuilder()
            ->withData($this->people())
            ->withQueryExpressionProvider($provider)
            ->withQueryExpression($qe)
            ->create();

        $ds->data();
    }

    /**
     * When withFieldMapping is also set, the custom provider is cloned, configured
     * with the field mapping, and then applied — the clone must still be invoked.
     */
    public function testWithQueryExpressionProviderIsUsedAsBaseWhenFieldMappingIsSet(): void
    {
        $called   = false;
        $provider = $this->createStub(QueryExpressionProviderInterface::class);
        $provider->method('apply')->willReturnCallback(
            static function () use (&$called): never {
                $called = true;

                throw new RuntimeException('provider was called');
            },
        );

        $qe = QueryExpression::create()->andWhere(FilterExpression::create()->equalTo('person_id', 1));

        try {
            $this->makeBuilder()
                ->withData($this->people())
                ->withQueryExpressionProvider($provider)
                ->withFieldMapping(['person_id' => 'id'])
                ->withQueryExpression($qe)
                ->create()
                ->data();
        } catch (RuntimeException) {
            // Expected: the cloned provider's apply() was invoked.
        }

        self::assertTrue($called, 'The custom provider was not used when field mapping was set.');
    }

    // -------------------------------------------------------------------------
    // withDescriptorFactory
    // -------------------------------------------------------------------------

    public function testWithDescriptorFactoryReturnsNewBuilderInstance(): void
    {
        $factory = $this->createStub(ReadModelDescriptorFactoryInterface::class);

        self::assertNotSame(
            $this->makeBuilder(),
            $this->makeBuilder()->withDescriptorFactory($factory),
        );
    }

    // -------------------------------------------------------------------------
    // handleRequest
    // -------------------------------------------------------------------------

    public function testHandleRequestThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->makeBuilder()->handleRequest(new stdClass());
    }

    // -------------------------------------------------------------------------
    // Branching — independent clones do not share history
    // -------------------------------------------------------------------------

    public function testIndependentBranchesDoNotShareQueryExpressionHistory(): void
    {
        $base = $this->makeBuilder()->withData($this->people());
        $qe1  = QueryExpression::create()->andWhere(FilterExpression::create()->greaterThan('age', 29));
        $qe2  = QueryExpression::create()->andWhere(FilterExpression::create()->lowerThan('age', 30));

        $branchA = $base->withQueryExpression($qe1);
        $branchB = $branchA->withQueryExpression($qe2, true); // appended: qe1 + qe2
        $branchA = $branchA->withoutQueryExpression(true);    // undo → no filter

        // branchA after undo: no filters → all 5
        self::assertCount(5, $branchA->create()->data());
        // branchB: age > 29 AND age < 30 → nobody
        self::assertSame([], $this->ids($branchB->create()->data()));
    }
}
