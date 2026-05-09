<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayAccess;
use InvalidArgumentException;
use RuntimeException;

use function in_array;

/**
 * @phpstan-template T of object|array<string, mixed>
 * @template-implements ArrayAccess<string, T[]|int<0, max>|null>
 */
class ReadResponse implements ArrayAccess
{
    /** @phpstan-var T[]|null */
    public array|null $data = null;
    /** @phpstan-var int<0, max>|null */
    public int|null $page = null;
    /** @phpstan-var int<0, max>|null */
    public int|null $total = null;

    final public function __construct()
    {
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

    /**
     * @phpstan-param T[] $data
     * @phpstan-param int<0, max> $page
     * @phpstan-param int<0, max> $total
     */
    public static function create(array $data, int $page, int $total): static
    {
        if ($page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($total < 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        /** @phpstan-var static<T> $instance */
        $instance        = new static();
        $instance->data  = $data;
        $instance->page  = $page;
        $instance->total = $total;

        return $instance;
    }
}
