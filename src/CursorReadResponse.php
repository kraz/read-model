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
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @template-implements ArrayAccess<string, object[]|array<array<string, mixed>>|string|bool|int<0, max>|null>
 */
class CursorReadResponse implements ArrayAccess
{
    public function __construct(
        /** @phpstan-var T[]|null */
        public readonly array|null $data = null,
        public readonly string|null $nextCursor = null,
        public readonly string|null $previousCursor = null,
        public readonly bool $hasNext = false,
        public readonly bool $hasPrevious = false,
        /** @phpstan-var int<0, max>|null */
        public readonly int|null $totalItems = null,
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->totalItems !== null && $this->totalItems < 0) {
            throw new InvalidArgumentException('Expected a non-negative integer.');
        }
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
}
