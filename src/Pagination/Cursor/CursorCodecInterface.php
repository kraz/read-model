<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

use Kraz\ReadModel\Exception\InvalidCursorException;

/**
 * Translates a {@see Cursor} value object to and from its opaque wire format.
 *
 * Implementations control the on-the-wire representation and any integrity guarantees
 * (e.g. HMAC signing). Decoders MUST throw {@see InvalidCursorException} on malformed,
 * truncated or tampered input — they are the only enforcement point that keeps cursor
 * tokens immutable in transit.
 */
interface CursorCodecInterface
{
    public function encode(Cursor $cursor): string;

    /** @throws InvalidCursorException */
    public function decode(string $token): Cursor;
}
