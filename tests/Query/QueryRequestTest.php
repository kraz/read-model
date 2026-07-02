<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query;

use InvalidArgumentException;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function assert;
use function base64_encode;
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

    // ------------------------------------------------------------------
    // withCursor — happy path & validation
    // ------------------------------------------------------------------

    public function testWithCursorSetsTokenAndLimit(): void
    {
        $request = QueryRequest::create()->withCursor('abc', 25);

        self::assertSame('abc', $request->getCursor());
        self::assertSame(25, $request->getCursorLimit());
        self::assertFalse($request->isEmpty());
    }

    public function testWithCursorRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create()->withCursor('', 10);
    }

    public function testWithCursorAcceptsNullTokenForFirstPage(): void
    {
        $request = QueryRequest::create()->withCursor(null, 10);

        self::assertNull($request->getCursor());
        self::assertSame(10, $request->getCursorLimit());
        self::assertFalse($request->isEmpty());
    }

    public function testWithCursorRejectsZeroLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create()->withCursor('abc', 0);
    }

    public function testWithCursorClearsPageBasedPagination(): void
    {
        $request = QueryRequest::create()->withPagination(2, 10)->withCursor('abc', 5);

        self::assertNull($request->getPage());
        self::assertNull($request->getItemsPerPage());
        self::assertSame('abc', $request->getCursor());
        self::assertSame(5, $request->getCursorLimit());
    }

    public function testWithCursorClearsLimitOffset(): void
    {
        $request = QueryRequest::create()->withLimit(10, 20)->withCursor('xyz', 7);

        self::assertNull($request->getLimit());
        self::assertNull($request->getOffset());
        self::assertSame('xyz', $request->getCursor());
        self::assertSame(7, $request->getCursorLimit());
    }

    public function testWithPaginationClearsCursor(): void
    {
        $request = QueryRequest::create()->withCursor('abc', 5)->withPagination(1, 10);

        self::assertNull($request->getCursor());
        self::assertNull($request->getCursorLimit());
        self::assertSame(1, $request->getPage());
        self::assertSame(10, $request->getItemsPerPage());
    }

    public function testWithLimitClearsCursor(): void
    {
        $request = QueryRequest::create()->withCursor('abc', 5)->withLimit(8);

        self::assertNull($request->getCursor());
        self::assertNull($request->getCursorLimit());
        self::assertSame(8, $request->getLimit());
    }

    public function testWithoutCursorClearsCursorState(): void
    {
        $request = QueryRequest::create()->withCursor('abc', 5)->withoutCursor();

        self::assertNull($request->getCursor());
        self::assertNull($request->getCursorLimit());
        self::assertTrue($request->isEmpty());
    }

    public function testCursorRoundTripsThroughCreateAndJson(): void
    {
        $request   = QueryRequest::create()->withCursor('token123', 12);
        $roundtrip = QueryRequest::create((string) $request);

        self::assertSame('token123', $roundtrip->getCursor());
        self::assertSame(12, $roundtrip->getCursorLimit());
    }

    public function testCursorRoundTripsThroughSerialize(): void
    {
        $request  = QueryRequest::create()->withCursor('token456', 30);
        $restored = unserialize(serialize($request));

        assert($restored instanceof QueryRequest);
        self::assertSame('token456', $restored->getCursor());
        self::assertSame(30, $restored->getCursorLimit());
    }

    public function testCreateRejectsNonStringCursor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create('{"cursor":123}');
    }

    public function testCreateRejectsNonIntegerCursorLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::create('{"cursor":"abc","cursorLimit":"5"}');
    }

    // ------------------------------------------------------------------
    // create() — base64 auto-detection
    // ------------------------------------------------------------------

    public function testCreateAcceptsBase64EncodedJsonString(): void
    {
        $encoded = base64_encode('{"limit":7,"offset":14}');
        $request = QueryRequest::create($encoded);

        self::assertSame(7, $request->getLimit());
        self::assertSame(14, $request->getOffset());
    }

    public function testCreateBase64AutoDetectionPreservesJsonPath(): void
    {
        $request = QueryRequest::create('{"limit":3}');

        self::assertSame(3, $request->getLimit());
    }

    // ------------------------------------------------------------------
    // encode()
    // ------------------------------------------------------------------

    public function testEncodeReturnsNonEmptyString(): void
    {
        $encoded = QueryRequest::create()->withLimit(5, 10)->encode();

        self::assertNotEmpty($encoded);
    }

    public function testEncodeRoundTripViaCreate(): void
    {
        $original = QueryRequest::create()->withLimit(8, 4);
        $restored = QueryRequest::create($original->encode());

        self::assertSame(8, $restored->getLimit());
        self::assertSame(4, $restored->getOffset());
    }

    public function testEncodeRoundTripWithCursor(): void
    {
        $original = QueryRequest::create()->withCursor('tok', 15);
        $restored = QueryRequest::create($original->encode());

        self::assertSame('tok', $restored->getCursor());
        self::assertSame(15, $restored->getCursorLimit());
    }

    // ------------------------------------------------------------------
    // decode()
    // ------------------------------------------------------------------

    public function testDecodeRestoresRequest(): void
    {
        $original = QueryRequest::create()->withLimit(12, 6);
        $restored = QueryRequest::decode($original->encode());

        self::assertSame(12, $restored->getLimit());
        self::assertSame(6, $restored->getOffset());
    }

    public function testDecodeRestoresCursorRequest(): void
    {
        $original = QueryRequest::create()->withCursor('cursor-abc', 20);
        $restored = QueryRequest::decode($original->encode());

        self::assertSame('cursor-abc', $restored->getCursor());
        self::assertSame(20, $restored->getCursorLimit());
    }

    public function testDecodeThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::decode('');
    }

    public function testDecodeThrowsOnInvalidBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::decode('!!!not-base64!!!');
    }

    public function testDecodeThrowsWhenDecodedIsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::decode(base64_encode(''));
    }

    // ------------------------------------------------------------------
    // Mutual exclusivity — new behaviours added in last commit
    // ------------------------------------------------------------------

    public function testWithPaginationClearsLimitAndOffset(): void
    {
        $request = QueryRequest::create()->withLimit(10, 5)->withPagination(2, 20);

        self::assertNull($request->getLimit());
        self::assertNull($request->getOffset());
        self::assertSame(2, $request->getPage());
        self::assertSame(20, $request->getItemsPerPage());
    }

    public function testWithLimitClearsPageBasedPagination(): void
    {
        $request = QueryRequest::create()->withPagination(3, 15)->withLimit(10, 5);

        self::assertNull($request->getPage());
        self::assertNull($request->getItemsPerPage());
        self::assertSame(10, $request->getLimit());
        self::assertSame(5, $request->getOffset());
    }

    // ------------------------------------------------------------------
    // assemble() — happy paths
    // ------------------------------------------------------------------

    public function testAssembleEmptyInputReturnsEmptyRequest(): void
    {
        $request = QueryRequest::assemble([]);

        self::assertTrue($request->isEmpty());
        self::assertNull($request->getQuery());
        self::assertNull($request->getLimit());
        self::assertNull($request->getCursor());
        self::assertNull($request->getPage());
    }

    public function testAssembleWithQueryKey(): void
    {
        $request = QueryRequest::assemble(['query' => '{"filters":[]}']);

        self::assertNotNull($request->getQuery());
    }

    public function testAssembleWithInvalidQueryThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryRequest::assemble(['query' => 'not-valid-json!!!!']);
    }

    public function testAssembleAppliesFieldFilters(): void
    {
        $request = QueryRequest::assemble(['name' => 'Alice'], ['name']);

        $query = $request->getQuery();
        self::assertNotNull($query);
        self::assertFalse($query->isEmpty());
    }

    public function testAssembleIgnoresInputKeyNotInFields(): void
    {
        $request = QueryRequest::assemble(['unknown' => 'value'], ['name']);

        self::assertNull($request->getQuery());
    }

    public function testAssembleRespectsFieldsOperator(): void
    {
        $request = QueryRequest::assemble(
            ['name' => 'Ali'],
            ['name'],
            ['name' => FilterExpression::OP_STARTS_WITH],
        );

        $query = $request->getQuery();
        self::assertNotNull($query);
        self::assertStringContainsString(FilterExpression::OP_STARTS_WITH, (string) $query);
    }

    public function testAssembleWithValueKey(): void
    {
        $request = QueryRequest::assemble(['value' => 'foo']);

        $query = $request->getQuery();
        self::assertNotNull($query);
        self::assertStringContainsString('foo', (string) $query);
    }

    public function testAssembleWithValuesKey(): void
    {
        $request = QueryRequest::assemble(['values' => ['a', 'b']]);

        $query = $request->getQuery();
        self::assertNotNull($query);
        self::assertStringContainsString('a', (string) $query);
    }

    public function testAssembleValueIsPrependedToValues(): void
    {
        $request = QueryRequest::assemble(['value' => 'first', 'values' => ['second']]);

        $query = $request->getQuery();
        self::assertNotNull($query);
        $json = (string) $query;
        self::assertStringContainsString('first', $json);
        self::assertStringContainsString('second', $json);
    }

    public function testAssembleNonArrayValuesIsIgnored(): void
    {
        $request = QueryRequest::assemble(['values' => 'not-an-array']);

        self::assertNull($request->getQuery());
    }

    public function testAssembleWithOrderAppliesSort(): void
    {
        $request = QueryRequest::assemble(['order' => ['name' => 'asc']], ['name']);

        $query = $request->getQuery();
        self::assertNotNull($query);
        self::assertStringContainsString('name', (string) $query);
    }

    public function testAssembleOrderOnlyAllowsKnownFields(): void
    {
        $request = QueryRequest::assemble(['order' => ['unknown' => 'asc']], ['name']);

        self::assertNull($request->getQuery());
    }

    public function testAssembleNonArrayOrderIsIgnored(): void
    {
        $request = QueryRequest::assemble(['order' => 'asc'], ['name']);

        self::assertNull($request->getQuery());
    }

    // ------------------------------------------------------------------
    // assemble() — pagination modes
    // ------------------------------------------------------------------

    public function testAssembleWithPageAndPageSize(): void
    {
        $request = QueryRequest::assemble(['page' => 2, 'pageSize' => 10]);

        self::assertSame(2, $request->getPage());
        self::assertSame(10, $request->getItemsPerPage());
        self::assertNull($request->getLimit());
        self::assertNull($request->getCursor());
    }

    public function testAssembleWithPageAndItemsPerPageAlias(): void
    {
        $request = QueryRequest::assemble(['page' => 3, 'itemsPerPage' => 15]);

        self::assertSame(3, $request->getPage());
        self::assertSame(15, $request->getItemsPerPage());
    }

    public function testAssembleWithLimitAndOffset(): void
    {
        $request = QueryRequest::assemble(['limit' => 20, 'offset' => 40]);

        self::assertSame(20, $request->getLimit());
        self::assertSame(40, $request->getOffset());
        self::assertNull($request->getPage());
        self::assertNull($request->getCursor());
    }

    public function testAssembleWithLimitOnly(): void
    {
        $request = QueryRequest::assemble(['limit' => 5]);

        self::assertSame(5, $request->getLimit());
        self::assertNull($request->getOffset());
    }

    public function testAssembleWithCursorAndCursorLimit(): void
    {
        $request = QueryRequest::assemble(['cursor' => 'tok', 'cursorLimit' => 25]);

        self::assertSame('tok', $request->getCursor());
        self::assertSame(25, $request->getCursorLimit());
        self::assertNull($request->getLimit());
        self::assertNull($request->getPage());
    }

    public function testAssembleCursorLimitDefaultsToLimit(): void
    {
        $request = QueryRequest::assemble(['cursor' => 'tok', 'limit' => 10]);

        self::assertSame('tok', $request->getCursor());
        self::assertSame(10, $request->getCursorLimit());
    }

    public function testAssembleCursorKeyAloneActivatesCursor(): void
    {
        $request = QueryRequest::assemble(['cursorLimit' => 30]);

        self::assertNull($request->getCursor());
        self::assertSame(30, $request->getCursorLimit());
    }

    // ------------------------------------------------------------------
    // assemble() — pagination mode priority (cursor > limit > page)
    // ------------------------------------------------------------------

    public function testAssembleCursorWinsOverLimitAndPagination(): void
    {
        $request = QueryRequest::assemble([
            'page'        => 2,
            'pageSize'    => 10,
            'limit'       => 20,
            'cursor'      => 'tok',
            'cursorLimit' => 5,
        ]);

        self::assertSame('tok', $request->getCursor());
        self::assertSame(5, $request->getCursorLimit());
        self::assertNull($request->getLimit());
        self::assertNull($request->getPage());
    }

    public function testAssembleLimitWinsOverPaginationWhenNoCursor(): void
    {
        $request = QueryRequest::assemble([
            'page'     => 2,
            'pageSize' => 10,
            'limit'    => 20,
        ]);

        self::assertSame(20, $request->getLimit());
        self::assertNull($request->getPage());
        self::assertNull($request->getCursor());
    }

    // ------------------------------------------------------------------
    // create() — cursor limit fallback to limit (commit 841282c fix)
    // ------------------------------------------------------------------

    public function testCreateFallsBackCursorLimitToLimitWhenCursorIsPresentAndCursorLimitIsAbsent(): void
    {
        $request = QueryRequest::create(['cursor' => 'tok', 'limit' => 10]);

        self::assertSame('tok', $request->getCursor());
        self::assertSame(10, $request->getCursorLimit());
    }
}
