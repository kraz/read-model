<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayAccess;
use InvalidArgumentException;
use RuntimeException;

use function in_array;

/**
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @template-implements ArrayAccess<string, object[]|array<array<string, mixed>>|int<0, max>|null>
 */
class ReadResponse implements ArrayAccess
{
    public function __construct(
        /** @phpstan-var T[]|null */
        public readonly array|null $data = null,
        /** @phpstan-var int<0, max>|null */
        public readonly int|null $page = null,
        /** @phpstan-var int<0, max>|null */
        public readonly int|null $total = null,
    ) {
        $this->validate();
    }

    protected function validate(): void
    {
        if ($this->page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($this->total < 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array((string) $offset, ['data', 'page', 'total']);
    }

    /** @return T[]|int<0, max>|null */
    public function offsetGet(mixed $offset): mixed
    {
        return match ((string) $offset) {
            'data' => $this->data,
            'page' => $this->page,
            'total' => $this->total,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('Invalid operation. Can not modify read response!');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('Invalid operation. Can not modify read response!');
    }
}
