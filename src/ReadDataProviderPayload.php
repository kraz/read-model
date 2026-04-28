<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayIterator;
use ReturnTypeWillChange;
use Traversable;

use function is_array;

/** @phpstan-template T of object|array<string, mixed> */
final readonly class ReadDataProviderPayload
{
    private int $page;
    private int $total;
    /** @phpstan-var array<int, T> */
    private array $data;

    /** @phpstan-param array{data?: array<int, T>, page?: int, total?: int}|object{data?: array<int, T>, page?: int, total?: int} $data */
    public function __construct(
        array|object $data,
    ) {
        $this->page  = (int) (is_array($data) ? ($data['page'] ?? 1) : ($data->page ?? 1));
        $this->total = (int) (is_array($data) ? ($data['total'] ?? 0) : ($data->total ?? 0));
        $this->data  = (array) (is_array($data) ? ($data['data'] ?? []) : ($data->data ?? []));
    }

    /** @return array<int, T> */
    public function getData(): array
    {
        return $this->data;
    }

    public function getCurrentPage(): int
    {
        return $this->page;
    }

    public function getTotalItems(): int
    {
        return $this->total;
    }

    /** @return Traversable<array-key, T> */
    #[ReturnTypeWillChange]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getData());
    }
}
