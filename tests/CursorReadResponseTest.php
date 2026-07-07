<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests;

use InvalidArgumentException;
use Kraz\ReadModel\CursorReadResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CursorReadResponse::class)]
final class CursorReadResponseTest extends TestCase
{
    public function testCreatePopulatesAllFields(): void
    {
        $response = new CursorReadResponse(
            data: [['id' => 1]],
            nextCursor: 'next-token',
            previousCursor: 'prev-token',
            hasNext: true,
            hasPrevious: false,
            totalItems: 42,
        );

        self::assertSame([['id' => 1]], $response->data);
        self::assertSame('next-token', $response->nextCursor);
        self::assertSame('prev-token', $response->previousCursor);
        self::assertTrue($response->hasNext);
        self::assertFalse($response->hasPrevious);
        self::assertSame(42, $response->totalItems);
    }

    public function testCreateAllowsNullCursorsAndTotal(): void
    {
        $response = new CursorReadResponse([], null, null, false, false);

        self::assertNull($response->nextCursor);
        self::assertNull($response->previousCursor);
        self::assertNull($response->totalItems);
        self::assertSame([], $response->data);
    }

    public function testNegativeTotalThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore argument.type */
        new CursorReadResponse([], null, null, false, false, -1);
    }

    public function testArrayAccessReadsFields(): void
    {
        $response = new CursorReadResponse(
            [['id' => 1]],
            'n',
            'p',
            true,
            true,
            7,
        );

        self::assertSame([['id' => 1]], $response['data']);
        self::assertSame('n', $response['nextCursor']);
        self::assertSame('p', $response['previousCursor']);
        self::assertTrue($response['hasNext']);
        self::assertTrue($response['hasPrevious']);
        self::assertSame(7, $response['totalItems']);
        self::assertNull($response['unknown']);
    }

    public function testOffsetExistsCoversAllKnownFields(): void
    {
        $response = new CursorReadResponse([], null, null, false, false);

        self::assertTrue(isset($response['data']));
        self::assertTrue(isset($response['nextCursor']));
        self::assertTrue(isset($response['previousCursor']));
        self::assertTrue(isset($response['hasNext']));
        self::assertTrue(isset($response['hasPrevious']));
        self::assertTrue(isset($response['totalItems']));
        self::assertFalse(isset($response['unknown']));
    }

    public function testOffsetSetIsRejected(): void
    {
        $response = new CursorReadResponse([], null, null, false, false);

        $this->expectException(RuntimeException::class);
        $response['data'] = [];
    }

    public function testOffsetUnsetIsRejected(): void
    {
        $response = new CursorReadResponse([], null, null, false, false);

        $this->expectException(RuntimeException::class);
        unset($response['data']);
    }
}
