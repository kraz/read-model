<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Query\FilterExpression;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_column;
use function json_encode;
use function serialize;
use function unserialize;

#[CoversClass(FilterExpression::class)]
final class FilterExpressionTest extends TestCase
{
    public function testCreateEmpty(): void
    {
        $expr = FilterExpression::create();

        self::assertTrue($expr->isFilterEmpty());
        self::assertNull($expr->field());
        self::assertNull($expr->operator());
        self::assertNull($expr->value());
        self::assertNull($expr->logic());
        self::assertSame([], $expr->filters());
        self::assertSame([], $expr->toArray());
        self::assertSame('', (string) $expr);
        self::assertNull($expr->jsonSerialize());
    }

    public function testCreateFromArray(): void
    {
        $expr = FilterExpression::create(['field' => 'name', 'operator' => 'eq', 'value' => 'foo']);

        self::assertFalse($expr->isFilterEmpty());
        self::assertSame('name', $expr->field());
        self::assertSame('eq', $expr->operator());
        self::assertSame('foo', $expr->value());
        self::assertTrue($expr->ignoreCase());
        self::assertFalse($expr->inverted());
    }

    public function testCreateFromJsonString(): void
    {
        $expr = FilterExpression::create('{"field":"name","operator":"eq","value":"foo"}');

        self::assertSame('name', $expr->field());
        self::assertSame('eq', $expr->operator());
        self::assertSame('foo', $expr->value());
    }

    public function testCreateFromInvalidJsonStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FilterExpression::create('"not-an-array"');
    }

    public function testEqualToBuildsLeafFilter(): void
    {
        $expr = FilterExpression::create()->equalTo('name', 'foo');

        self::assertSame(
            ['field' => 'name', 'operator' => 'eq', 'value' => 'foo'],
            $expr->toArray(),
        );
    }

    public function testNotEqualTo(): void
    {
        $expr = FilterExpression::create()->notEqualTo('name', 'foo');

        self::assertSame('neq', $expr->operator());
        self::assertSame('foo', $expr->value());
    }

    public function testCaseSensitiveBuilderTagsFilter(): void
    {
        $expr = FilterExpression::create()->equalTo('name', 'Foo', false);

        self::assertFalse($expr->ignoreCase());
        self::assertSame(
            ['field' => 'name', 'operator' => 'eq', 'value' => 'Foo', 'ignoreCase' => false],
            $expr->toArray(),
        );
    }

    public function testZeroValueIsKept(): void
    {
        $expr = FilterExpression::create()->equalTo('count', 0);

        self::assertSame(0, $expr->value());
    }

    public function testNullValueOnLeafBuilderProducesEmptyFilter(): void
    {
        $expr = FilterExpression::create()->equalTo('name', null);

        self::assertTrue($expr->isFilterEmpty());
    }

    public function testEmptyStringValueOnLeafBuilderProducesEmptyFilter(): void
    {
        $expr = FilterExpression::create()->equalTo('name', '');

        self::assertTrue($expr->isFilterEmpty());
    }

    public function testEmptyArrayWithListOperatorProducesEmptyFilter(): void
    {
        $expr = FilterExpression::create()->inList('name', []);

        self::assertTrue($expr->isFilterEmpty());
    }

    public function testInListBuildsLeafFilter(): void
    {
        $expr = FilterExpression::create()->inList('name', ['a', 'b']);

        self::assertSame('inlist', $expr->operator());
        self::assertSame(['a', 'b'], $expr->value());
    }

    public function testNotInList(): void
    {
        $expr = FilterExpression::create()->notInList('name', ['a', 'b']);

        self::assertSame('notinlist', $expr->operator());
        self::assertSame(['a', 'b'], $expr->value());
    }

    public function testIsNullBuildsLeafWithoutValue(): void
    {
        $expr = FilterExpression::create()->isNull('deleted_at');

        self::assertFalse($expr->isFilterEmpty());
        self::assertSame('deleted_at', $expr->field());
        self::assertSame('isnull', $expr->operator());
        self::assertNull($expr->value());
        self::assertSame(
            ['field' => 'deleted_at', 'operator' => 'isnull'],
            $expr->toArray(),
        );
        self::assertSame('IS NULL', $expr->expression());
    }

    public function testIsNotNullBuildsLeafWithoutValue(): void
    {
        $expr = FilterExpression::create()->isNotNull('deleted_at');

        self::assertSame('isnotnull', $expr->operator());
        self::assertNull($expr->value());
        self::assertSame('IS NOT NULL', $expr->expression());
    }

    public function testIsEmptyBuildsLeafWithoutValue(): void
    {
        $expr = FilterExpression::create()->isEmpty('notes');

        self::assertSame('isempty', $expr->operator());
        self::assertNull($expr->value());
        self::assertSame('IS EMPTY', $expr->expression());
    }

    public function testIsNotEmptyBuildsLeafWithoutValue(): void
    {
        $expr = FilterExpression::create()->isNotEmpty('notes');

        self::assertSame('isnotempty', $expr->operator());
        self::assertNull($expr->value());
        self::assertSame('IS NOT EMPTY', $expr->expression());
    }

    public function testIsNullComposesInsideAndX(): void
    {
        $expr = FilterExpression::create();
        $and  = $expr->andX(
            $expr->equalTo('a', 1),
            $expr->isNull('b'),
        );

        self::assertSame(
            [
                'logic' => 'and',
                'filters' => [
                    ['field' => 'a', 'operator' => 'eq', 'value' => 1],
                    ['field' => 'b', 'operator' => 'isnull'],
                ],
            ],
            $and->toArray(),
        );
    }

    public function testStringComparisonBuilders(): void
    {
        $eq  = FilterExpression::create()->startsWith('name', 'foo');
        $eq2 = FilterExpression::create()->doesNotStartWith('name', 'foo');
        $eq3 = FilterExpression::create()->endsWith('name', 'foo');
        $eq4 = FilterExpression::create()->doesNotEndWith('name', 'foo');
        $eq5 = FilterExpression::create()->contains('name', 'foo');
        $eq6 = FilterExpression::create()->doesNotContain('name', 'foo');

        self::assertSame('startswith', $eq->operator());
        self::assertSame('doesnotstartwith', $eq2->operator());
        self::assertSame('endswith', $eq3->operator());
        self::assertSame('doesnotendwith', $eq4->operator());
        self::assertSame('contains', $eq5->operator());
        self::assertSame('doesnotcontain', $eq6->operator());
    }

    public function testNumericComparisonBuilders(): void
    {
        $expr = FilterExpression::create();

        self::assertSame('lt', $expr->lowerThan('age', 5)->operator());
        self::assertSame('lte', $expr->lowerThanOrEqual('age', 5)->operator());
        self::assertSame('gt', $expr->greaterThan('age', 5)->operator());
        self::assertSame('gte', $expr->greaterThanOrEqual('age', 5)->operator());
    }

    public function testAndXComposesAndLogic(): void
    {
        $expr = FilterExpression::create();
        $and  = $expr->andX(
            $expr->equalTo('a', 1),
            $expr->equalTo('b', 2),
        );

        self::assertSame('and', $and->logic());
        self::assertCount(2, $and->filters());
        self::assertSame(
            [
                'logic' => 'and',
                'filters' => [
                    ['field' => 'a', 'operator' => 'eq', 'value' => 1],
                    ['field' => 'b', 'operator' => 'eq', 'value' => 2],
                ],
            ],
            $and->toArray(),
        );
    }

    public function testOrXComposesOrLogic(): void
    {
        $expr = FilterExpression::create();
        $or   = $expr->orX(
            $expr->equalTo('a', 1),
            $expr->equalTo('b', 2),
        );

        self::assertSame('or', $or->logic());
        self::assertCount(2, $or->filters());
    }

    public function testNestedLogicGrouping(): void
    {
        $expr   = FilterExpression::create();
        $nested = $expr->andX(
            $expr->orX(
                $expr->equalTo('a', 1),
                $expr->equalTo('b', 2),
            ),
            $expr->equalTo('c', 3),
        );

        self::assertSame(
            [
                'logic' => 'and',
                'filters' => [
                    [
                        'logic' => 'or',
                        'filters' => [
                            ['field' => 'a', 'operator' => 'eq', 'value' => 1],
                            ['field' => 'b', 'operator' => 'eq', 'value' => 2],
                        ],
                    ],
                    ['field' => 'c', 'operator' => 'eq', 'value' => 3],
                ],
            ],
            $nested->toArray(),
        );
    }

    public function testLogicXOnFieldFilterThrows(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        $this->expectException(LogicException::class);
        $expr->andX($expr->equalTo('b', 2));
    }

    public function testNotInvertsRestriction(): void
    {
        $expr = FilterExpression::create();
        $not  = $expr->not($expr->equalTo('a', 1));

        self::assertTrue($not->inverted());
    }

    public function testNotTwiceCancels(): void
    {
        $expr = FilterExpression::create();
        $a    = $expr->equalTo('a', 1);
        $not2 = $expr->not($expr->not($a));

        self::assertFalse($not2->inverted());
        self::assertSame($a->toArray(), $not2->toArray());
    }

    public function testFieldFiltersFindsAllOccurrences(): void
    {
        $expr   = FilterExpression::create();
        $nested = $expr->andX(
            $expr->equalTo('a', 1),
            $expr->orX(
                $expr->equalTo('a', 2),
                $expr->equalTo('b', 3),
            ),
        );

        $matches = $nested->fieldFilters('a');

        self::assertCount(2, $matches);
        self::assertSame([1, 2], array_column($matches, 'value'));
    }

    public function testFieldFiltersReturnsEmptyArrayForUnknownField(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        self::assertSame([], $expr->fieldFilters('missing'));
    }

    public function testExpressionForLeaf(): void
    {
        $expr = FilterExpression::create(['field' => 'a', 'operator' => 'eq', 'value' => 'foo']);

        self::assertSame('=FOO', $expr->expression());
    }

    public function testExpressionWithCaseSensitiveValue(): void
    {
        $expr = FilterExpression::create([
            'field' => 'a',
            'operator' => 'eq',
            'value' => 'Foo',
            'ignoreCase' => false,
        ]);

        self::assertSame('=Foo', $expr->expression());
    }

    public function testExpressionWithoutOperatorReturnsNull(): void
    {
        self::assertNull(FilterExpression::create()->expression());
    }

    public function testFieldExpressionFromComposition(): void
    {
        $expr   = FilterExpression::create();
        $nested = $expr->andX(
            $expr->equalTo('a', 5),
            $expr->equalTo('a', 10),
        );

        self::assertSame('=5 and =10', $nested->fieldExpression('a'));
    }

    public function testFieldExpressionForUnknownField(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        self::assertSame('', $expr->fieldExpression('missing'));
    }

    public function testCompactRemovesEmptyChildren(): void
    {
        $expr   = FilterExpression::create();
        $nested = $expr->andX(
            $expr->equalTo('a', 1),
            $expr->equalTo('b', null),
        );

        $compacted = $nested->compact();

        self::assertCount(1, $compacted->filters());
        self::assertSame('a', $compacted->filters()[0]->field());
    }

    public function testResetWithoutFieldClearsAll(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        self::assertTrue($expr->reset()->isFilterEmpty());
    }

    public function testResetWithFieldRemovesField(): void
    {
        $expr   = FilterExpression::create();
        $nested = $expr->andX(
            $expr->equalTo('a', 1),
            $expr->equalTo('b', 2),
        );

        $cleared = $nested->reset('a');

        $remaining = $cleared->filters();
        self::assertCount(1, $remaining);
        self::assertSame('b', $remaining[0]->field());
    }

    public function testToStringReturnsJson(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        self::assertSame('{"field":"a","operator":"eq","value":1}', (string) $expr);
    }

    public function testJsonSerializeReturnsArray(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        self::assertSame(
            '{"field":"a","operator":"eq","value":1}',
            json_encode($expr),
        );
    }

    public function testSerializeRoundTripPreservesContent(): void
    {
        $expr = FilterExpression::create();
        $and  = $expr->andX(
            $expr->equalTo('a', 1),
            $expr->equalTo('b', 2),
        );

        $restored = unserialize(serialize($and));
        self::assertInstanceOf(FilterExpression::class, $restored);
        self::assertSame($and->toArray(), $restored->toArray());
    }

    public function testCloneIsIndependent(): void
    {
        $expr  = FilterExpression::create();
        $base  = $expr->andX($expr->equalTo('a', 1));
        $clone = clone $base;

        self::assertSame($base->toArray(), $clone->toArray());
        self::assertNotSame($base->filters()[0], $clone->filters()[0]);
    }

    public function testUseLogicGroupingFlag(): void
    {
        $expr = FilterExpression::create()->equalTo('a', 1);

        self::assertTrue($expr->isUsingLogicGrouping());

        $expr->useLogicGrouping(false);
        self::assertFalse($expr->isUsingLogicGrouping());
    }

    public function testGetOperatorsDescriptionContainsAllOperators(): void
    {
        $ops = FilterExpression::getOperatorsDescription();

        self::assertArrayHasKey('eq', $ops);
        self::assertArrayHasKey('isnull', $ops);
        self::assertArrayHasKey('inlist', $ops);
        self::assertSame('Is equal to', $ops['eq']['name']);
        self::assertTrue($ops['eq']['value_required']);
        self::assertFalse($ops['isnull']['value_required']);
    }

    public function testOperatorRequiresValue(): void
    {
        self::assertTrue(FilterExpression::operatorRequiresValue('eq'));
        self::assertFalse(FilterExpression::operatorRequiresValue('isnull'));
        self::assertFalse(FilterExpression::operatorRequiresValue('unknown'));
    }

    public function testApplyFieldMappingRenamesFields(): void
    {
        $mapped = FilterExpression::applyFieldMapping(
            [
                'logic' => 'and',
                'filters' => [
                    ['field' => 'name', 'operator' => 'eq', 'value' => 'foo'],
                    ['field' => 'age', 'operator' => 'gt', 'value' => 5],
                ],
            ],
            ['name' => 'full_name'],
        );

        self::assertSame('full_name', $mapped['filters'][0]['field']);
        self::assertSame('age', $mapped['filters'][1]['field']);
    }

    public function testApplyFieldMappingOnLeafFilter(): void
    {
        $mapped = FilterExpression::applyFieldMapping(
            ['field' => 'name', 'operator' => 'eq', 'value' => 'foo'],
            ['name' => 'full_name'],
        );

        self::assertSame('full_name', $mapped['field']);
    }

    public function testWalkFieldValuesTransformsValues(): void
    {
        $walked = FilterExpression::walkFieldValues(
            [
                'logic' => 'and',
                'filters' => [
                    ['field' => 'name', 'operator' => 'eq', 'value' => 'foo'],
                    ['field' => 'age', 'operator' => 'gt', 'value' => 5],
                ],
            ],
            static fn (string $field, mixed $value): mixed => $field === 'age' ? ((int) $value) * 2 : null,
        );

        self::assertSame('foo', $walked['filters'][0]['value']);
        self::assertSame(10, $walked['filters'][1]['value']);
    }

    public function testNormalizeProducesComposedExpression(): void
    {
        $expr   = FilterExpression::create();
        $filter = [
            'logic' => 'and',
            'filters' => [
                ['field' => 'a', 'operator' => 'eq', 'value' => 1],
                ['field' => 'b', 'operator' => 'eq', 'value' => 2],
            ],
        ];

        $params = [];

        $normalizer = static function (FilterExpression $expr, array $filter, array &$params): FilterExpression {
            $params[] = $filter['value'];

            return FilterExpression::create([
                'field' => $filter['field'],
                'operator' => $filter['operator'],
                'value' => $filter['value'],
            ]);
        };

        $result = FilterExpression::normalize($expr, $filter, $params, $normalizer, []);

        self::assertInstanceOf(FilterExpression::class, $result);
        self::assertSame('and', $result->logic());
        self::assertSame([1, 2], $params);
    }

    public function testNormalizeWithInvertedLogic(): void
    {
        $expr   = FilterExpression::create();
        $filter = [
            'logic' => 'or',
            'not' => true,
            'filters' => [
                ['field' => 'a', 'operator' => 'eq', 'value' => 1],
            ],
        ];

        $params     = [];
        $normalizer = static fn (FilterExpression $expr, array $filter, array &$params): FilterExpression => FilterExpression::create([
            'field' => $filter['field'],
            'operator' => $filter['operator'],
            'value' => $filter['value'],
        ]);

        $result = FilterExpression::normalize($expr, $filter, $params, $normalizer, []);

        self::assertInstanceOf(FilterExpression::class, $result);
        self::assertTrue($result->inverted());
    }

    public function testNormalizeReturnsNullForEmpty(): void
    {
        $expr   = FilterExpression::create();
        $params = [];

        $result = FilterExpression::normalize(
            $expr,
            ['logic' => 'and', 'filters' => []],
            $params,
            static fn (): FilterExpression => FilterExpression::create(),
            [],
        );

        self::assertNull($result);
    }
}
