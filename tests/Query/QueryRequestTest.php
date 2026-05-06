<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Query\QueryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function serialize;
use function unserialize;

#[CoversClass(QueryRequest::class)]
final class QueryRequestTest extends TestCase
{
    // ------------------------------------------------------------------
    // Defaults
    // ------------------------------------------------------------------

    public function testDefaultLimitIsNull(): void
    {
        self::assertNull(QueryRequest::create()->getLimit());
    }

    public function testDefaultOffsetIsNull(): void
    {
        self::assertNull(QueryRequest::create()->getOffset());
    }

    public function testDefaultIsEmpty(): void
    {
        self::assertTrue(QueryRequest::create()->isEmpty());
    }

    // ------------------------------------------------------------------
    // withLimit — happy path
    // ------------------------------------------------------------------

    public function testWithLimitSetsLimit(): void
    {
        $request = QueryRequest::create()->withLimit(5);

        self::assertSame(5, $request->getLimit());
        self::assertNull($request->getOffset());
    }

    public function testWithLimitAndOffsetSetsLimitAndOffset(): void
    {
        $request = QueryRequest::create()->withLimit(10, 20);

        self::assertSame(10, $request->getLimit());
        self::assertSame(20, $request->getOffset());
    }

    public function testWithLimitOffsetZeroIsAllowed(): void
    {
        $request = QueryRequest::create()->withLimit(5, 0);

        self::assertSame(5, $request->getLimit());
        self::assertSame(0, $request->getOffset());
    }

    // ------------------------------------------------------------------
    // withLimit — validation
    // ------------------------------------------------------------------

    public function testWithLimitRejectsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create()->withLimit(0);
    }

    public function testCreateThrowsOnNegativeLimitViaConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create('{"limit":-1}');
    }

    public function testCreateThrowsOnNegativeOffsetViaConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create('{"limit":5,"offset":-1}');
    }

    // ------------------------------------------------------------------
    // withoutLimit
    // ------------------------------------------------------------------

    public function testWithoutLimitClearsLimitAndOffset(): void
    {
        $request = QueryRequest::create()->withLimit(5, 10)->withoutLimit();

        self::assertNull($request->getLimit());
        self::assertNull($request->getOffset());
    }

    public function testWithoutLimitOnFreshRequestIsNoOp(): void
    {
        $request = QueryRequest::create()->withoutLimit();

        self::assertNull($request->getLimit());
        self::assertNull($request->getOffset());
        self::assertTrue($request->isEmpty());
    }

    // ------------------------------------------------------------------
    // Immutability
    // ------------------------------------------------------------------

    public function testWithLimitDoesNotMutateOriginal(): void
    {
        $original = QueryRequest::create();
        $original->withLimit(5);

        self::assertNull($original->getLimit());
        self::assertTrue($original->isEmpty());
    }

    public function testWithoutLimitDoesNotMutateOriginal(): void
    {
        $request = QueryRequest::create()->withLimit(5);
        $request->withoutLimit();

        self::assertSame(5, $request->getLimit());
    }

    // ------------------------------------------------------------------
    // isEmpty
    // ------------------------------------------------------------------

    public function testIsNotEmptyWhenLimitIsSet(): void
    {
        self::assertFalse(QueryRequest::create()->withLimit(1)->isEmpty());
    }

    public function testIsNotEmptyWhenOffsetIsZero(): void
    {
        self::assertFalse(QueryRequest::create()->withLimit(5, 0)->isEmpty());
    }

    public function testIsEmptyAfterWithoutLimit(): void
    {
        self::assertTrue(QueryRequest::create()->withLimit(5)->withoutLimit()->isEmpty());
    }

    // ------------------------------------------------------------------
    // toArray
    // ------------------------------------------------------------------

    public function testToArrayIncludesLimitWhenSet(): void
    {
        $array = QueryRequest::create()->withLimit(7)->toArray();

        self::assertArrayHasKey('limit', $array);
        self::assertSame(7, $array['limit'] ?? null);
        self::assertArrayNotHasKey('offset', $array);
    }

    public function testToArrayIncludesOffsetWhenSet(): void
    {
        $array = QueryRequest::create()->withLimit(3, 6)->toArray();

        self::assertSame(3, $array['limit'] ?? null);
        self::assertSame(6, $array['offset'] ?? null);
    }

    public function testToArrayIncludesOffsetZero(): void
    {
        $array = QueryRequest::create()->withLimit(3, 0)->toArray();

        self::assertArrayHasKey('offset', $array);
        self::assertSame(0, $array['offset'] ?? null);
    }

    public function testToArrayExcludesLimitWhenNull(): void
    {
        $array = QueryRequest::create()->toArray();

        self::assertArrayNotHasKey('limit', $array);
        self::assertArrayNotHasKey('offset', $array);
    }

    // ------------------------------------------------------------------
    // create() — parsing
    // ------------------------------------------------------------------

    public function testCreateParsesLimitFromArray(): void
    {
        $request = QueryRequest::create(['limit' => 4]);

        self::assertSame(4, $request->getLimit());
        self::assertNull($request->getOffset());
    }

    public function testCreateParsesLimitAndOffsetFromArray(): void
    {
        $request = QueryRequest::create(['limit' => 10, 'offset' => 5]);

        self::assertSame(10, $request->getLimit());
        self::assertSame(5, $request->getOffset());
    }

    public function testCreateParsesLimitAndOffsetFromJsonString(): void
    {
        $request = QueryRequest::create('{"limit":8,"offset":2}');

        self::assertSame(8, $request->getLimit());
        self::assertSame(2, $request->getOffset());
    }

    public function testCreateThrowsWhenLimitIsNotInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create('{"limit":"5"}');
    }

    public function testCreateThrowsWhenOffsetIsNotInteger(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create('{"offset":"0"}');
    }

    // ------------------------------------------------------------------
    // Serialization round-trip
    // ------------------------------------------------------------------

    public function testSerializeRoundTripPreservesLimitAndOffset(): void
    {
        $request    = QueryRequest::create()->withLimit(15, 30);
        $serialized = serialize($request);

        $restored = unserialize($serialized);
        assert($restored instanceof QueryRequest);

        self::assertSame(15, $restored->getLimit());
        self::assertSame(30, $restored->getOffset());
    }

    public function testSerializeRoundTripWithNullLimitAndOffset(): void
    {
        $request  = QueryRequest::create();
        $restored = unserialize(serialize($request));

        self::assertNull($restored->getLimit());
        self::assertNull($restored->getOffset());
        self::assertTrue($restored->isEmpty());
    }

    // ------------------------------------------------------------------
    // __toString / jsonSerialize
    // ------------------------------------------------------------------

    public function testToStringIncludesLimitAndOffset(): void
    {
        $str = (string) QueryRequest::create()->withLimit(5, 10);

        self::assertStringContainsString('"limit":5', $str);
        self::assertStringContainsString('"offset":10', $str);
    }

    public function testToStringIsEmptyWhenNoLimitSet(): void
    {
        self::assertSame('', (string) QueryRequest::create());
    }
}
