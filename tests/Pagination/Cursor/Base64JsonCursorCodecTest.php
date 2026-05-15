<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Pagination\Cursor;

use Kraz\ReadModel\Exception\InvalidCursorException;
use Kraz\ReadModel\Pagination\Cursor\Base64JsonCursorCodec;
use Kraz\ReadModel\Pagination\Cursor\Cursor;
use Kraz\ReadModel\Pagination\Cursor\Direction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function rtrim;
use function str_starts_with;
use function strtr;
use function substr;
use function substr_count;

#[CoversClass(Base64JsonCursorCodec::class)]
final class Base64JsonCursorCodecTest extends TestCase
{
    public function testRoundTripPreservesAllFields(): void
    {
        $codec    = new Base64JsonCursorCodec();
        $original = new Cursor(
            Direction::BACKWARD,
            [['field' => 'created_at', 'value' => '2026-05-15 12:00:00'], ['field' => 'id', 'value' => 99]],
            'sig-xyz',
        );

        $decoded = $codec->decode($codec->encode($original));

        self::assertSame($original->getDirection(), $decoded->getDirection());
        self::assertSame($original->getPosition(), $decoded->getPosition());
        self::assertSame($original->getSortSignature(), $decoded->getSortSignature());
    }

    public function testEncodedTokenIsUrlSafeBase64(): void
    {
        $codec  = new Base64JsonCursorCodec();
        $cursor = new Cursor(Direction::FORWARD, [['field' => 'id', 'value' => 1]], 'sig');

        $token = $codec->encode($cursor);

        self::assertSame(0, substr_count($token, '+'), 'URL-safe base64 must not contain +');
        self::assertSame(0, substr_count($token, '/'), 'URL-safe base64 must not contain /');
        self::assertSame(0, substr_count($token, '='), 'URL-safe base64 must not have padding');
    }

    public function testEncodedTokenDoesNotLeakFieldNames(): void
    {
        $codec  = new Base64JsonCursorCodec();
        $cursor = new Cursor(Direction::FORWARD, [['field' => 'sensitive_id', 'value' => 42]], 'sig');

        $token = $codec->encode($cursor);

        self::assertStringNotContainsString('sensitive_id', $token);
    }

    public function testEmptyTokenThrows(): void
    {
        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode('');
    }

    public function testInvalidBase64Throws(): void
    {
        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode('!!!not-base64!!!');
    }

    public function testNonJsonPayloadThrows(): void
    {
        $token = rtrim(strtr(base64_encode('not json'), '+/', '-_'), '=');

        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode($token);
    }

    public function testMissingDirectionKeyThrows(): void
    {
        $token = rtrim(strtr(base64_encode('{"p":[],"s":"sig"}'), '+/', '-_'), '=');

        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode($token);
    }

    public function testUnknownDirectionThrows(): void
    {
        $token = rtrim(strtr(base64_encode('{"d":"sideways","p":[],"s":"sig"}'), '+/', '-_'), '=');

        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode($token);
    }

    public function testMalformedPositionEntryThrows(): void
    {
        $token = rtrim(strtr(base64_encode('{"d":"f","p":[{"oops":1}],"s":"sig"}'), '+/', '-_'), '=');

        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode($token);
    }

    public function testNonScalarPositionValueThrows(): void
    {
        $token = rtrim(strtr(base64_encode('{"d":"f","p":[{"field":"id","value":{"nested":true}}],"s":"sig"}'), '+/', '-_'), '=');

        $this->expectException(InvalidCursorException::class);
        new Base64JsonCursorCodec()->decode($token);
    }

    public function testFlippingFirstBytesProducesParseError(): void
    {
        $codec    = new Base64JsonCursorCodec();
        $token    = $codec->encode(new Cursor(Direction::FORWARD, [['field' => 'id', 'value' => 1]], 'sig'));
        $tampered = ($token[0] === 'a' ? 'b' : 'a') . substr($token, 1);

        // Tampering may either fail to decode (most likely) or decode to something else.
        // Either way, the result is not a faithful round-trip.
        try {
            $decoded = $codec->decode($tampered);
            self::assertNotSame('sig', $decoded->getSortSignature());
        } catch (InvalidCursorException $e) {
            self::assertTrue(str_starts_with($e->getMessage(), 'Cursor token'));
        }
    }
}
