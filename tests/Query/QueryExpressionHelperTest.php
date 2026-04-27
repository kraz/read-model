<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Collections\Criteria;
use Kraz\ReadModel\Collections\Selectable;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionHelper;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\ReadModelDescriptor;
use Kraz\ReadModel\Tests\Query\Fixtures\PersonFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(QueryExpressionHelper::class)]
final class QueryExpressionHelperTest extends TestCase
{
    /** @return ArrayCollection<int, PersonFixture> */
    private function people(): ArrayCollection
    {
        return new ArrayCollection([
            new PersonFixture(id: 1, name: 'Alice', age: 30),
            new PersonFixture(id: 2, name: 'Bob', age: 25),
            new PersonFixture(id: 3, name: 'Carol', age: 35),
            new PersonFixture(id: 4, name: 'Dan', age: 40),
        ]);
    }

    /**
     * @phpstan-param Selectable<array-key, PersonFixture> $items
     *
     * @return list<int>
     */
    private function ids(Selectable $items): array
    {
        $ids = [];
        foreach ($items->matching(Criteria::create()) as $person) {
            $ids[] = $person->id;
        }

        return $ids;
    }

    /**
     * @phpstan-param ArrayCollection<int, PersonFixture> $data
     *
     * @return list<int>
     */
    private function applyAndGetIds(ArrayCollection $data, QueryExpression $qry): array
    {
        return $this->ids(QueryExpressionHelper::create($data)->apply($qry));
    }

    public function testEmptyQueryReturnsCloneOfData(): void
    {
        $data   = $this->people();
        $helper = QueryExpressionHelper::create($data);

        $result = $helper->apply(QueryExpression::create());

        self::assertNotSame($data, $result);
        self::assertSame($this->ids($data), $this->ids($result));
    }

    public function testEqFilterMatchesValue(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'Alice'));

        self::assertSame([1], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testGtAndLtComposition(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere(
            $qry->expr()->greaterThan('age', 25),
            $qry->expr()->lowerThan('age', 40),
        );

        self::assertSame([1, 3], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testGteAndLteBoundaries(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere(
            $qry->expr()->greaterThanOrEqual('age', 30),
            $qry->expr()->lowerThanOrEqual('age', 35),
        );

        self::assertSame([1, 3], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testNotEqualTo(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->notEqualTo('name', 'Alice'));

        self::assertSame([2, 3, 4], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testOrComposition(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->orWhere(
            $qry->expr()->equalTo('name', 'Alice'),
            $qry->expr()->equalTo('name', 'Dan'),
        );

        self::assertSame([1, 4], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testStartsWith(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->startsWith('name', 'A', false));

        self::assertSame([1], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testEndsWith(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->endsWith('name', 'l', false));

        self::assertSame([3], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testContains(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->contains('name', 'a', false));

        self::assertSame([3, 4], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testDoesNotContain(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->doesNotContain('name', 'a', false));

        self::assertSame([1, 2], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testDoesNotStartWith(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->doesNotStartWith('name', 'A', false));

        self::assertSame([2, 3, 4], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testDoesNotEndWith(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->doesNotEndWith('name', 'l', false));

        self::assertSame([1, 2, 4], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testIsNullAndIsNotNull(): void
    {
        $data = new ArrayCollection([
            new PersonFixture(id: 1, name: 'Alice', tag: 'apple'),
            new PersonFixture(id: 2, name: 'Bob', tag: null),
        ]);

        $isNull = QueryExpression::create();
        $isNull = $isNull->andWhere($isNull->expr()->isNull('tag'));
        self::assertSame([2], $this->applyAndGetIds($data, $isNull));

        $isNotNull = QueryExpression::create();
        $isNotNull = $isNotNull->andWhere($isNotNull->expr()->isNotNull('tag'));
        self::assertSame([1], $this->applyAndGetIds($data, $isNotNull));
    }

    public function testIsEmptyAndIsNotEmpty(): void
    {
        $data = new ArrayCollection([
            new PersonFixture(id: 1, tag: 'apple'),
            new PersonFixture(id: 2, tag: ''),
            new PersonFixture(id: 3, tag: null),
        ]);

        $isEmpty = QueryExpression::create();
        $isEmpty = $isEmpty->andWhere($isEmpty->expr()->isEmpty('tag'));
        self::assertSame([2, 3], $this->applyAndGetIds($data, $isEmpty));

        $isNotEmpty = QueryExpression::create();
        $isNotEmpty = $isNotEmpty->andWhere($isNotEmpty->expr()->isNotEmpty('tag'));
        self::assertSame([1], $this->applyAndGetIds($data, $isNotEmpty));
    }

    public function testInListAndNotInList(): void
    {
        $inList = QueryExpression::create();
        $inList = $inList->andWhere($inList->expr()->inList('name', ['Alice', 'Bob']));
        self::assertSame([1, 2], $this->applyAndGetIds($this->people(), $inList));

        $notInList = QueryExpression::create();
        $notInList = $notInList->andWhere($notInList->expr()->notInList('name', ['Alice', 'Bob']));
        self::assertSame([3, 4], $this->applyAndGetIds($this->people(), $notInList));
    }

    public function testInListAcceptsCommaSeparatedString(): void
    {
        $qry = QueryExpression::create([
            'filter' => ['field' => 'name', 'operator' => 'inlist', 'value' => 'Alice,Bob'],
        ]);

        self::assertSame([1, 2], $this->applyAndGetIds($this->people(), $qry));
    }

    public function testSortAscendingAndDescending(): void
    {
        $asc = QueryExpression::create()->sortBy('age', 'asc');
        self::assertSame([2, 1, 3, 4], $this->applyAndGetIds($this->people(), $asc));

        $desc = QueryExpression::create()->sortBy('age', 'desc');
        self::assertSame([4, 3, 1, 2], $this->applyAndGetIds($this->people(), $desc));
    }

    public function testSortMissingFieldThrows(): void
    {
        $qry = QueryExpression::create(['sort' => [['field' => '', 'dir' => 'asc']]]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The sort rule must specify a field');

        QueryExpressionHelper::create($this->people())->apply($qry);
    }

    public function testValuesAsEqWhenSingle(): void
    {
        $qry = QueryExpression::create()->withValues([2]);

        $result = QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'id'])
            ->apply($qry);

        self::assertSame([2], $this->ids($result));
    }

    public function testValuesAsInWhenMultiple(): void
    {
        $qry = QueryExpression::create()->withValues([1, 3, 4]);

        $result = QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'id'])
            ->apply($qry);

        self::assertSame([1, 3, 4], $this->ids($result));
    }

    public function testValuesAreAndedWithExistingFilter(): void
    {
        $qry = QueryExpression::create()->withValues([1, 2, 3]);
        $qry = $qry->andWhere($qry->expr()->greaterThan('age', 28));

        $result = QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'id'])
            ->apply($qry);

        self::assertSame([1, 3], $this->ids($result));
    }

    public function testValuesRequireRootIdentifierOption(): void
    {
        $qry = QueryExpression::create()->withValues([1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Did you missed the "root_identifier" option');

        QueryExpressionHelper::create($this->people())->apply($qry);
    }

    public function testValuesRejectEmptyRootIdentifier(): void
    {
        $qry = QueryExpression::create()->withValues([1]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"root_identifier"');

        QueryExpressionHelper::create($this->people(), null, ['root_identifier' => ''])->apply($qry);
    }

    public function testValuesRejectRootIdentifierContainingDot(): void
    {
        $qry = QueryExpression::create()->withValues([1]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"."');

        QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'p.id'])->apply($qry);
    }

    public function testIncludeFlagFilterOnly(): void
    {
        $qry = QueryExpression::create()->withValues([1])->sortBy('age', 'desc');
        $qry = $qry->andWhere($qry->expr()->greaterThan('age', 28));

        $result = QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'id'])
            ->apply($qry, QueryExpressionProviderInterface::INCLUDE_DATA_FILTER);

        self::assertSame([1, 3, 4], $this->ids($result));
    }

    public function testIncludeFlagSortOnly(): void
    {
        $qry = QueryExpression::create()->withValues([1])->sortBy('age', 'desc');
        $qry = $qry->andWhere($qry->expr()->greaterThan('age', 28));

        $result = QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'id'])
            ->apply($qry, QueryExpressionProviderInterface::INCLUDE_DATA_SORT);

        self::assertSame([4, 3, 1, 2], $this->ids($result));
    }

    public function testIncludeFlagValuesOnly(): void
    {
        $qry = QueryExpression::create()->withValues([2, 3])->sortBy('age', 'desc');
        $qry = $qry->andWhere($qry->expr()->greaterThan('age', 100));

        $result = QueryExpressionHelper::create($this->people(), null, ['root_identifier' => 'id'])
            ->apply($qry, QueryExpressionProviderInterface::INCLUDE_DATA_VALUES);

        self::assertSame([2, 3], $this->ids($result));
    }

    public function testFieldMapInOptionsRewritesFilterField(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = QueryExpressionHelper::create(
            $this->people(),
            null,
            ['field_map' => ['label' => 'name']],
        )->apply($qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testFieldMapFromDescriptorRewritesFilterField(): void
    {
        $descriptor = new ReadModelDescriptor([], [], [], ['label' => 'name']);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = QueryExpressionHelper::create($this->people(), $descriptor)->apply($qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testOptionsFieldMapWinsOverDescriptor(): void
    {
        $descriptor = new ReadModelDescriptor([], [], [], ['label' => 'age']);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = QueryExpressionHelper::create(
            $this->people(),
            $descriptor,
            ['field_map' => ['label' => 'name']],
        )->apply($qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testReadModelDescriptorOptionInstanceIsAdoptedWhenNoExplicitDescriptor(): void
    {
        $descriptor = new ReadModelDescriptor([], [], [], ['label' => 'name']);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('label', 'Alice'));

        $result = QueryExpressionHelper::create(
            $this->people(),
            null,
            ['read_model_descriptor' => $descriptor],
        )->apply($qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testRootAliasStripsPrefixFromFilterField(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('p.name', 'Alice'));

        $result = QueryExpressionHelper::create($this->people(), null, ['root_alias' => 'p'])
            ->apply($qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testExpressionsOptionRewritesFieldInFilter(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('display', 'Alice'));

        $result = QueryExpressionHelper::create($this->people(), null, [
            'expressions' => ['display' => ['exp' => 'name']],
        ])->apply($qry);

        self::assertSame([1], $this->ids($result));
    }

    public function testExpressionsOptionRewritesFieldInSort(): void
    {
        $qry = QueryExpression::create()->sortBy('display', 'desc');

        $result = QueryExpressionHelper::create($this->people(), null, [
            'expressions' => ['display' => ['exp' => 'age']],
        ])->apply($qry);

        self::assertSame([4, 3, 1, 2], $this->ids($result));
    }

    public function testMissingFilterValueThrows(): void
    {
        $qry = QueryExpression::create(['filter' => ['field' => 'name', 'operator' => 'eq']]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing filter value');

        QueryExpressionHelper::create($this->people())->apply($qry);
    }

    public function testUnsupportedFilterOperatorThrows(): void
    {
        $qry = QueryExpression::create([
            'filter' => ['field' => 'name', 'operator' => 'bogus', 'value' => 'x'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported filter operator');

        QueryExpressionHelper::create($this->people())->apply($qry);
    }

    public function testGroupsExpandFilterAcrossGroupFields(): void
    {
        $data = new ArrayCollection([
            new PersonFixture(id: 1, first_name: 'Alice', last_name: 'Smith'),
            new PersonFixture(id: 2, first_name: 'Bob', last_name: 'Alice'),
            new PersonFixture(id: 3, first_name: 'Carol', last_name: 'Jones'),
        ]);

        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('combined', 'Alice'));

        $result = QueryExpressionHelper::create($data, null, [
            'groups' => [
                'combined' => ['logic' => 'or', 'fields' => ['first_name', 'last_name']],
            ],
        ])->apply($qry);

        self::assertSame([1, 2], $this->ids($result));
    }

    public function testIgnoreCaseFalseUsesCaseSensitiveComparison(): void
    {
        $qry = QueryExpression::create();
        $qry = $qry->andWhere($qry->expr()->equalTo('name', 'alice', false));

        self::assertSame([], $this->applyAndGetIds($this->people(), $qry));
    }
}
