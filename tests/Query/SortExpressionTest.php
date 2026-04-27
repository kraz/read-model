<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Query\SortExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function serialize;
use function unserialize;

#[CoversClass(SortExpression::class)]
final class SortExpressionTest extends TestCase
{
    public function testCreateEmpty(): void
    {
        $sort = SortExpression::create();

        self::assertTrue($sort->isSortEmpty());
        self::assertSame(0, $sort->count());
        self::assertSame([], $sort->items());
        self::assertSame([], $sort->toArray());
        self::assertSame('', (string) $sort);
        self::assertNull($sort->jsonSerialize());
    }

    public function testCreateFromArray(): void
    {
        $sort = SortExpression::create([
            ['field' => 'name', 'dir' => 'asc'],
            ['field' => 'age', 'dir' => 'desc'],
        ]);

        self::assertFalse($sort->isSortEmpty());
        self::assertSame(2, $sort->count());
        self::assertSame('asc', $sort->dir('name'));
        self::assertSame('desc', $sort->dir('age'));
        self::assertSame(1, $sort->num('name'));
        self::assertSame(2, $sort->num('age'));
    }

    public function testCreateFromSingleItemArray(): void
    {
        $sort = SortExpression::create(['field' => 'name', 'dir' => 'asc']);

        self::assertSame(1, $sort->count());
        self::assertSame('asc', $sort->dir('name'));
        self::assertSame([['field' => 'name', 'dir' => 'asc']], $sort->items());
    }

    public function testCreateFromJsonString(): void
    {
        $json = '[{"field":"name","dir":"asc"},{"field":"age","dir":"desc"}]';
        $sort = SortExpression::create($json);

        self::assertSame(2, $sort->count());
        self::assertSame('asc', $sort->dir('name'));
        self::assertSame('desc', $sort->dir('age'));
    }

    public function testCreateFromInvalidStringThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SortExpression::create('"not-an-array"');
    }

    public function testAscAddsAscendingSort(): void
    {
        $sort = SortExpression::create()->asc('name');

        self::assertSame('asc', $sort->dir('name'));
        self::assertSame(1, $sort->num('name'));
    }

    public function testDescAddsDescendingSort(): void
    {
        $sort = SortExpression::create()->desc('age');

        self::assertSame('desc', $sort->dir('age'));
        self::assertSame(1, $sort->num('age'));
    }

    public function testAscWithMultipleFields(): void
    {
        $sort = SortExpression::create()->asc('name', 'age');

        self::assertSame('asc', $sort->dir('name'));
        self::assertSame('asc', $sort->dir('age'));
        self::assertSame(1, $sort->num('name'));
        self::assertSame(2, $sort->num('age'));
    }

    public function testReplaceDirectionForSameField(): void
    {
        $sort = SortExpression::create()->asc('name')->desc('name');

        self::assertSame('desc', $sort->dir('name'));
        self::assertSame(1, $sort->count());
    }

    public function testCombineAscAndDescBuildsList(): void
    {
        $sort = SortExpression::create()->asc('name')->desc('age');

        self::assertSame(2, $sort->count());
        self::assertSame('asc', $sort->dir('name'));
        self::assertSame('desc', $sort->dir('age'));
    }

    public function testResetWithFieldRemovesOnlyThatField(): void
    {
        $sort = SortExpression::create()
            ->asc('name')
            ->desc('age')
            ->reset('name');

        self::assertSame(1, $sort->count());
        self::assertNull($sort->dir('name'));
        self::assertSame('desc', $sort->dir('age'));
    }

    public function testResetWithoutFieldRemovesAll(): void
    {
        $sort = SortExpression::create()
            ->asc('name')
            ->desc('age')
            ->reset();

        self::assertTrue($sort->isSortEmpty());
        self::assertSame(0, $sort->count());
    }

    public function testResetUnknownFieldKeepsItems(): void
    {
        $sort = SortExpression::create()->asc('name')->reset('missing');

        self::assertSame(1, $sort->count());
        self::assertSame('asc', $sort->dir('name'));
    }

    public function testDirReturnsNullForUnknownField(): void
    {
        $sort = SortExpression::create()->asc('name');

        self::assertNull($sort->dir('missing'));
    }

    public function testNumReturnsNullForUnknownField(): void
    {
        $sort = SortExpression::create()->asc('name');

        self::assertNull($sort->num('missing'));
    }

    public function testToArrayReturnsItems(): void
    {
        $sort = SortExpression::create()->asc('name')->desc('age');

        self::assertSame(
            [
                ['field' => 'name', 'dir' => 'asc'],
                ['field' => 'age', 'dir' => 'desc'],
            ],
            $sort->toArray(),
        );
    }

    public function testToStringEncodesAsJson(): void
    {
        $sort = SortExpression::create()->asc('name');

        self::assertSame('{"field":"name","dir":"asc"}', (string) $sort);
    }

    public function testJsonSerializeReturnsItems(): void
    {
        $sort = SortExpression::create()->asc('name');

        self::assertSame(
            [['field' => 'name', 'dir' => 'asc']],
            $sort->jsonSerialize(),
        );
        self::assertSame('[{"field":"name","dir":"asc"}]', json_encode($sort));
    }

    public function testSerializeRoundTrip(): void
    {
        $sort = SortExpression::create()->asc('name')->desc('age');

        $restored = unserialize(serialize($sort));
        self::assertInstanceOf(SortExpression::class, $restored);
        self::assertSame($sort->toArray(), $restored->toArray());
    }

    public function testCloneIsIndependent(): void
    {
        $sort  = SortExpression::create()->asc('name');
        $other = $sort->desc('age');

        self::assertSame(1, $sort->count());
        self::assertSame(2, $other->count());
    }

    public function testApplyFieldMappingOnList(): void
    {
        $mapped = SortExpression::applyFieldMapping(
            [
                ['field' => 'name', 'dir' => 'asc'],
                ['field' => 'age', 'dir' => 'desc'],
            ],
            ['name' => 'full_name'],
        );

        self::assertSame(
            [
                ['field' => 'full_name', 'dir' => 'asc'],
                ['field' => 'age', 'dir' => 'desc'],
            ],
            $mapped,
        );
    }

    public function testApplyFieldMappingOnSingleItem(): void
    {
        $mapped = SortExpression::applyFieldMapping(
            ['field' => 'name', 'dir' => 'asc'],
            ['name' => 'full_name'],
        );

        self::assertSame(['field' => 'full_name', 'dir' => 'asc'], $mapped);
    }

    public function testApplyFieldMappingLeavesUnknownFieldsUnchanged(): void
    {
        $mapped = SortExpression::applyFieldMapping(
            [['field' => 'name', 'dir' => 'asc']],
            ['other' => 'aliased'],
        );

        self::assertSame([['field' => 'name', 'dir' => 'asc']], $mapped);
    }
}
