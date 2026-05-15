<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Pagination\Cursor;

use InvalidArgumentException;
use Kraz\ReadModel\Exception\InvalidCursorException;
use Kraz\ReadModel\Pagination\Cursor\Base64JsonCursorCodec;
use Kraz\ReadModel\Pagination\Cursor\Cursor;
use Kraz\ReadModel\Pagination\Cursor\Direction;
use Kraz\ReadModel\Pagination\Cursor\SignedCursorCodec;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function hash;
use function strrpos;
use function substr;

#[CoversClass(SignedCursorCodec::class)]
final class SignedCursorCodecTest extends TestCase
{
    private function codec(string $secret = 'top-secret'): SignedCursorCodec
    {
        return new SignedCursorCodec(new Base64JsonCursorCodec(), $secret);
    }

    private function sampleCursor(): Cursor
    {
        return new Cursor(Direction::FORWARD, [['field' => 'id', 'value' => 7]], 'sig');
    }

    public function testRoundTripWithValidSignature(): void
    {
        $codec   = $this->codec();
        $decoded = $codec->decode($codec->encode($this->sampleCursor()));

        self::assertSame(Direction::FORWARD, $decoded->getDirection());
        self::assertSame([['field' => 'id', 'value' => 7]], $decoded->getPosition());
        self::assertSame('sig', $decoded->getSortSignature());
    }

    public function testEmptySecretThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SignedCursorCodec(new Base64JsonCursorCodec(), '');
    }

    public function testUnknownAlgorithmThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SignedCursorCodec(new Base64JsonCursorCodec(), 'secret', 'no-such-algo');
    }

    public function testMissingSignatureSeparatorThrows(): void
    {
        $this->expectException(InvalidCursorException::class);
        $this->codec()->decode('payload-without-signature');
    }

    public function testEmptyPayloadOrSignatureThrows(): void
    {
        $this->expectException(InvalidCursorException::class);
        $this->codec()->decode('.signature-only');
    }

    public function testTamperedSignatureIsRejected(): void
    {
        $codec  = $this->codec();
        $token  = $codec->encode($this->sampleCursor());
        $sepPos = strrpos($token, '.');
        self::assertNotFalse($sepPos);
        $payload = substr($token, 0, $sepPos);
        $bogus   = $payload . '.' . substr(hash('sha256', 'other'), 0, 64);

        $this->expectException(InvalidCursorException::class);
        $codec->decode($bogus);
    }

    public function testDifferentSecretIsRejected(): void
    {
        $issuer = $this->codec('secret-a');
        $token  = $issuer->encode($this->sampleCursor());

        $this->expectException(InvalidCursorException::class);
        $this->codec('secret-b')->decode($token);
    }

    public function testFlippingPayloadByteInvalidatesSignature(): void
    {
        $codec   = $this->codec();
        $token   = $codec->encode($this->sampleCursor());
        $flipped = ($token[0] === 'a' ? 'b' : 'a') . substr($token, 1);

        $this->expectException(InvalidCursorException::class);
        $codec->decode($flipped);
    }
}
