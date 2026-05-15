<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

use InvalidArgumentException;
use Kraz\ReadModel\Exception\InvalidCursorException;
use Override;
use SensitiveParameter;

use function hash_algos;
use function hash_equals;
use function hash_hmac;
use function in_array;
use function strrpos;
use function substr;

/**
 * Decorator codec that appends an HMAC signature for tamper detection.
 *
 * Wraps an inner codec, appends a hex HMAC over its output, and rejects any decode
 * whose signature does not validate against the supplied secret. Uses
 * {@see hash_equals()} so signature comparison is constant-time.
 */
final readonly class SignedCursorCodec implements CursorCodecInterface
{
    private const string SEPARATOR = '.';

    public function __construct(
        private CursorCodecInterface $inner,
        #[SensitiveParameter]
        private string $secret,
        private string $algo = 'sha256',
    ) {
        if ($secret === '') {
            throw new InvalidArgumentException('Cursor signing secret cannot be empty.');
        }

        if (! in_array($algo, hash_algos(), true)) {
            throw new InvalidArgumentException('Unknown hash algorithm: ' . $algo);
        }
    }

    #[Override]
    public function encode(Cursor $cursor): string
    {
        $payload = $this->inner->encode($cursor);

        return $payload . self::SEPARATOR . hash_hmac($this->algo, $payload, $this->secret);
    }

    #[Override]
    public function decode(string $token): Cursor
    {
        $sepPos = strrpos($token, self::SEPARATOR);
        if ($sepPos === false) {
            throw new InvalidCursorException('Cursor token is missing signature.');
        }

        $payload = substr($token, 0, $sepPos);
        $sig     = substr($token, $sepPos + 1);

        if ($payload === '' || $sig === '') {
            throw new InvalidCursorException('Cursor token has an empty payload or signature.');
        }

        $expected = hash_hmac($this->algo, $payload, $this->secret);
        if (! hash_equals($expected, $sig)) {
            throw new InvalidCursorException('Cursor signature is invalid.');
        }

        return $this->inner->decode($payload);
    }
}
