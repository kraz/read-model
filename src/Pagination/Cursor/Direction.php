<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

/**
 * Direction of cursor-based traversal.
 *
 * Encoded inside the opaque cursor token so the client can simply echo back whichever
 * cursor (next or previous) it received without having to track direction separately.
 */
enum Direction: string
{
    case FORWARD  = 'f';
    case BACKWARD = 'b';

    public function invert(): self
    {
        return match ($this) {
            self::FORWARD => self::BACKWARD,
            self::BACKWARD => self::FORWARD,
        };
    }
}
