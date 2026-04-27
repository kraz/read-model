<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayAccess;
use RuntimeException;

use function in_array;

/**
 * @phpstan-template T
 * @phpstan-type ReadResponseType = array{data: T[], page: int, total: int}
 * @template-implements ArrayAccess<string, T[]|int|null>
 */
class ReadResponse implements ArrayAccess
{
    /** @phpstan-var T[]|null */
    public array|null $data = null;
    public int|null $page   = null;
    public int|null $total  = null;

    final public function __construct()
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array((string) $offset, ['data', 'page', 'total']);
    }

    /** @return T[]|int|null */
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

    /** @phpstan-param T[] $data */
    public static function create(array $data, int $page, int $total): static
    {
        $instance        = new static();
        $instance->data  = $data;
        $instance->page  = $page;
        $instance->total = $total;

        return $instance;
    }
}
