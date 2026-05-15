<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

use Kraz\ReadModel\Query\SortExpression;

use function hash;
use function implode;
use function substr;

/**
 * Anchor point for a cursor-paginated window.
 *
 * Holds the direction the cursor traverses, an ordered tuple of (field, value) pairs
 * describing the row to anchor against, and a fingerprint of the sort expression that
 * issued it. The fingerprint lets adapters reject cursors whose ordering no longer matches
 * the request's current sort (which would otherwise return garbage results).
 *
 * This object is the internal representation. External consumers only ever see the
 * opaque string produced by a {@see CursorCodecInterface}.
 *
 * @phpstan-type CursorPosition = list<array{field: string, value: scalar|null}>
 */
final readonly class Cursor
{
    /** @phpstan-param CursorPosition $position */
    public function __construct(
        private Direction $direction,
        private array $position,
        private string $sortSignature,
    ) {
    }

    public function getDirection(): Direction
    {
        return $this->direction;
    }

    /** @phpstan-return CursorPosition */
    public function getPosition(): array
    {
        return $this->position;
    }

    public function getSortSignature(): string
    {
        return $this->sortSignature;
    }

    public function withDirection(Direction $direction): self
    {
        if ($direction === $this->direction) {
            return $this;
        }

        return new self($direction, $this->position, $this->sortSignature);
    }

    /**
     * Build a short, stable fingerprint of the given sort.
     *
     * Used to bind a cursor to the exact ordering that issued it. A 16-character hex
     * truncation of SHA-256 is more than enough for change-detection (64 bits of
     * collision space against accidental sort drift) and keeps the encoded cursor
     * payload compact.
     */
    public static function signatureFor(SortExpression $sort): string
    {
        $parts = [];
        foreach ($sort->items() as $item) {
            $parts[] = $item['field'] . '|' . $item['dir'];
        }

        return substr(hash('sha256', implode("\n", $parts)), 0, 16);
    }
}
