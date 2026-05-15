<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayAccess;
use InvalidArgumentException;
use RuntimeException;

use function in_array;

/**
 * Structured response for cursor-paginated reads.
 *
 * Mirrors the shape of {@see ReadResponse} (which is offset-paginated) but exposes
 * cursor-specific fields. Total item count is optional — null indicates the adapter
 * chose not to compute it, which is the keyset-friendly default.
 *
 * @phpstan-template T of object|array<string, mixed>
 * @template-implements ArrayAccess<string, T[]|string|bool|int<0, max>|null>
 */
class CursorReadResponse implements ArrayAccess
{
    /** @phpstan-var T[]|null */
    public array|null $data = null;

    public string|null $nextCursor = null;

    public string|null $previousCursor = null;

    public bool $hasNext = false;

    public bool $hasPrevious = false;

    /** @phpstan-var int<0, max>|null */
    public int|null $totalItems = null;

    final public function __construct()
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array((string) $offset, ['data', 'nextCursor', 'previousCursor', 'hasNext', 'hasPrevious', 'totalItems'], true);
    }

    /** @return T[]|string|bool|int<0, max>|null */
    public function offsetGet(mixed $offset): mixed
    {
        return match ((string) $offset) {
            'data' => $this->data,
            'nextCursor' => $this->nextCursor,
            'previousCursor' => $this->previousCursor,
            'hasNext' => $this->hasNext,
            'hasPrevious' => $this->hasPrevious,
            'totalItems' => $this->totalItems,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('Invalid operation. Can not modify cursor read response!');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('Invalid operation. Can not modify cursor read response!');
    }

    /**
     * @phpstan-param T[]              $data
     * @phpstan-param int<0, max>|null $totalItems
     */
    public static function create(
        array $data,
        string|null $nextCursor,
        string|null $previousCursor,
        bool $hasNext,
        bool $hasPrevious,
        int|null $totalItems = null,
    ): static {
        if ($totalItems !== null && $totalItems < 0) {
            throw new InvalidArgumentException('Expected a non-negative integer.');
        }

        /** @phpstan-var static<T> $instance */
        $instance                 = new static();
        $instance->data           = $data;
        $instance->nextCursor     = $nextCursor;
        $instance->previousCursor = $previousCursor;
        $instance->hasNext        = $hasNext;
        $instance->hasPrevious    = $hasPrevious;
        $instance->totalItems     = $totalItems;

        return $instance;
    }
}
