<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination\Cursor;

use ArrayIterator;
use Closure;
use Kraz\ReadModel\Exception\InvalidCursorException;
use Kraz\ReadModel\Query\SortExpression;
use LogicException;
use Override;
use ReturnTypeWillChange;
use Traversable;

use function array_reverse;
use function array_slice;
use function array_values;
use function count;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function method_exists;
use function property_exists;
use function ucfirst;

/**
 * Reference in-memory implementation of cursor (keyset) pagination.
 *
 * Operates on a fully materialized, pre-sorted item set. The caller is responsible for
 * passing items already sorted by `$effectiveSort` AND for ensuring `$effectiveSort`
 * contains a stable tiebreaker — without one, ties at the window boundary cause
 * duplicate or skipped rows.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @phpstan-implements CursorPaginatorInterface<T>
 */
final class InMemoryCursorPaginator implements CursorPaginatorInterface
{
    /** @phpstan-var list<T> */
    private array $window = [];

    private bool $hasNext = false;

    private bool $hasPrevious = false;

    private string|null $nextCursor = null;

    private string|null $previousCursor = null;

    private Direction $direction;

    /** @phpstan-var Closure(mixed, string): mixed */
    private Closure $fieldAccessor;

    private bool $computed = false;

    /**
     * @phpstan-param array<array-key, T>             $items            Filtered and sorted by $effectiveSort.
     * @phpstan-param int<1, max>                     $limit
     * @phpstan-param int<0, max>|null                $totalItems       null = unknown (keyset-friendly default).
     * @phpstan-param (Closure(mixed, string): mixed)|null $fieldAccessor Reads a single field value off an item;
     *                                                                    defaults to public-property / getter / array-key fallback.
     */
    public function __construct(
        private readonly array $items,
        private readonly SortExpression $effectiveSort,
        private readonly int $limit,
        private readonly CursorCodecInterface $codec,
        private readonly Cursor|null $cursor = null,
        private readonly int|null $totalItems = null,
        Closure|null $fieldAccessor = null,
    ) {
        if ($limit < 1) {
            throw new LogicException('Cursor limit must be a positive integer.');
        }

        if ($effectiveSort->isSortEmpty()) {
            throw new LogicException('Cursor pagination requires a non-empty sort expression.');
        }

        if ($cursor !== null && $cursor->getSortSignature() !== Cursor::signatureFor($effectiveSort)) {
            throw new InvalidCursorException('Cursor was issued under a different sort order.');
        }

        $this->direction = $cursor?->getDirection() ?? Direction::FORWARD;

        $fieldAccessor     ??= self::defaultFieldAccessor(...);
        $this->fieldAccessor = $fieldAccessor;
    }

    #[Override]
    public function getLimit(): int
    {
        return $this->limit;
    }

    #[Override]
    public function getDirection(): Direction
    {
        return $this->direction;
    }

    #[Override]
    public function hasNext(): bool
    {
        $this->compute();

        return $this->hasNext;
    }

    #[Override]
    public function hasPrevious(): bool
    {
        $this->compute();

        return $this->hasPrevious;
    }

    #[Override]
    public function getNextCursor(): string|null
    {
        $this->compute();

        return $this->nextCursor;
    }

    #[Override]
    public function getPreviousCursor(): string|null
    {
        $this->compute();

        return $this->previousCursor;
    }

    #[Override]
    public function getTotalItems(): int|null
    {
        return $this->totalItems;
    }

    /** @return Traversable<array-key, T> */
    #[ReturnTypeWillChange]
    #[Override]
    public function getIterator(): Traversable
    {
        $this->compute();

        return new ArrayIterator($this->window);
    }

    #[Override]
    public function count(): int
    {
        $this->compute();

        return count($this->window);
    }

    private function compute(): void
    {
        if ($this->computed) {
            return;
        }

        $this->computed = true;

        $items     = array_values($this->items);
        $direction = $this->direction;

        // Walk in the natural direction. For BACKWARD we reverse the sequence AND invert
        // the sort comparator — the moving boundary is "what comes before the pivot in
        // the original ordering", which equals "what comes after the pivot when the
        // sequence and the sort are both flipped".
        $compareSort = $direction === Direction::BACKWARD
            ? $this->effectiveSort->invert()
            : $this->effectiveSort;

        if ($direction === Direction::BACKWARD) {
            $items = array_reverse($items);
        }

        $startOffset = 0;
        if ($this->cursor !== null) {
            $position = $this->cursor->getPosition();
            $total    = count($items);
            for ($i = 0; $i < $total; $i++) {
                $cmp = $this->tupleCompare($items[$i], $position, $compareSort);
                if ($cmp > 0) {
                    break;
                }

                $startOffset = $i + 1;
            }
        }

        $remaining = array_slice($items, $startOffset, $this->limit + 1);
        $hasMore   = count($remaining) > $this->limit;
        $window    = $hasMore ? array_slice($remaining, 0, $this->limit) : $remaining;

        if ($direction === Direction::BACKWARD) {
            // Caller always sees items in the natural sort order, regardless of which
            // way we navigated to reach them.
            $window = array_reverse($window);
        }

        $this->window = array_values($window);

        if ($direction === Direction::FORWARD) {
            $this->hasNext     = $hasMore;
            $this->hasPrevious = $this->cursor !== null;
        } else {
            $this->hasPrevious = $hasMore;
            // We can always step "forward" again to where we came from.
            $this->hasNext = true;
        }

        $signature     = Cursor::signatureFor($this->effectiveSort);
        $windowCount   = count($this->window);
        $sortItems     = array_values($this->effectiveSort->items());
        $fieldAccessor = $this->fieldAccessor;

        if ($windowCount === 0) {
            return;
        }

        if ($this->hasNext) {
            $last             = $this->window[$windowCount - 1];
            $this->nextCursor = $this->codec->encode(new Cursor(
                Direction::FORWARD,
                $this->extractPosition($last, $sortItems, $fieldAccessor),
                $signature,
            ));
        }

        if (! $this->hasPrevious) {
            return;
        }

        $first                = $this->window[0];
        $this->previousCursor = $this->codec->encode(new Cursor(
            Direction::BACKWARD,
            $this->extractPosition($first, $sortItems, $fieldAccessor),
            $signature,
        ));
    }

    /**
     * @phpstan-param T                                    $item
     * @phpstan-param list<array{field: string, value: scalar|null}> $position
     */
    private function tupleCompare(mixed $item, array $position, SortExpression $sort): int
    {
        foreach ($sort->items() as $idx => $sortItem) {
            $field      = $sortItem['field'];
            $dir        = $sortItem['dir'];
            $pivotEntry = $position[$idx] ?? null;
            if ($pivotEntry === null || $pivotEntry['field'] !== $field) {
                throw new InvalidCursorException('Cursor position does not match the effective sort.');
            }

            /** @phpstan-var T $item */
            $itemValue = ($this->fieldAccessor)($item, $field);
            if ($itemValue !== null && ! is_int($itemValue) && ! is_float($itemValue) && ! is_string($itemValue) && ! is_bool($itemValue)) {
                throw new InvalidCursorException('Field "' . $field . '" yielded a non-scalar value; cursor pagination requires scalar sort keys.');
            }

            $cmp = $this->compareScalars($itemValue, $pivotEntry['value']);
            if ($dir === SortExpression::DIR_DESC) {
                $cmp = -$cmp;
            }

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        return 0;
    }

    /**
     * @phpstan-param T                                                  $item
     * @phpstan-param list<array{field: string, dir: string}>            $sortItems
     * @phpstan-param Closure(mixed, string): mixed                      $accessor
     *
     * @phpstan-return list<array{field: string, value: scalar|null}>
     */
    private function extractPosition(mixed $item, array $sortItems, Closure $accessor): array
    {
        $position = [];
        foreach ($sortItems as $sortItem) {
            /** @phpstan-var mixed $value */
            $value = $accessor($item, $sortItem['field']);
            if ($value !== null && ! is_int($value) && ! is_float($value) && ! is_string($value) && ! is_bool($value)) {
                throw new InvalidCursorException('Field "' . $sortItem['field'] . '" produced a non-scalar value when extracting the cursor position.');
            }

            $position[] = ['field' => $sortItem['field'], 'value' => $value];
        }

        return $position;
    }

    private function compareScalars(int|float|string|bool|null $a, int|float|string|bool|null $b): int
    {
        // Nulls sort first. This matches the most common SQL "NULLS FIRST" semantics; an
        // adapter that wants the opposite can pass its own pre-sorted items.
        if ($a === null && $b === null) {
            return 0;
        }

        if ($a === null) {
            return -1;
        }

        if ($b === null) {
            return 1;
        }

        return $a <=> $b;
    }

    private static function defaultFieldAccessor(mixed $item, string $field): mixed
    {
        if (is_object($item)) {
            if (property_exists($item, $field)) {
                return $item->{$field};
            }

            $getter = 'get' . ucfirst($field);
            if (method_exists($item, $getter)) {
                /** @phpstan-var mixed $value */
                $value = $item->{$getter}();

                return $value;
            }

            return null;
        }

        if (is_array($item)) {
            return $item[$field] ?? null;
        }

        return null;
    }
}
