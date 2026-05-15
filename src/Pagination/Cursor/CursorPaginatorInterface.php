<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Provides domain specific notation for obtaining cursor-paginated data.
 *
 * Unlike {@see \Kraz\ReadModel\Pagination\PaginatorInterface} (offset/page-based), a
 * cursor paginator represents a single window into a stable ordering anchored by an
 * opaque token. Total counts are optional because keyset pagination is intentionally
 * bounded to O(limit) — issuing a `COUNT(*)` next to every page defeats its purpose.
 *
 * Implementations must guarantee that calling any of the methods below is idempotent
 * (no side effects on the underlying data source) and that {@see self::count()} returns
 * the size of the current window, NOT the total — this differs from the page paginator
 * intentionally because the two interfaces serve different mental models.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @phpstan-extends IteratorAggregate<array-key, T>
 */
interface CursorPaginatorInterface extends IteratorAggregate, Countable
{
    /** @phpstan-return int<1, max> */
    public function getLimit(): int;

    public function getDirection(): Direction;

    public function hasNext(): bool;

    public function hasPrevious(): bool;

    /** Opaque token to fetch the next window, or null when at the end of the result set. */
    public function getNextCursor(): string|null;

    /** Opaque token to fetch the previous window, or null when at the start of the result set. */
    public function getPreviousCursor(): string|null;

    /**
     * Total number of items matching the underlying query, when known.
     *
     * Returns null when the adapter chose not to compute a total (the keyset-friendly
     * default — a full count defeats the O(limit) advantage of cursor pagination).
     *
     * @phpstan-return int<0, max>|null
     */
    public function getTotalItems(): int|null;

    /** @return Traversable<array-key, T> */
    public function getIterator(): Traversable;

    /**
     * Number of items in the current window (NOT the total result-set size).
     */
    public function count(): int;
}
