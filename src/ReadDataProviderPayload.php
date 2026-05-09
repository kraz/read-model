<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayIterator;
use InvalidArgumentException;
use ReturnTypeWillChange;
use Traversable;

use function is_array;

/** @phpstan-template T of object|array<string, mixed> */
final readonly class ReadDataProviderPayload
{
    /** @phpstan-var int<0, max> */
    private int $page;
    /** @phpstan-var int<0, max> */
    private int $total;
    /** @phpstan-var T[] */
    private array $data;

    /** @phpstan-param ReadResponse<T>|object{data: T[], page: int<0, max>, total: int<0, max>}|array{data: T[], page: int<0, max>, total: int<0, max>} $data */
    public function __construct(
        array|object $data,
    ) {
        $page = (int) (is_array($data) ? ($data['page'] ?? 1) : ($data->page ?? 1));
        if ($page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $total = (int) (is_array($data) ? ($data['total'] ?? 0) : ($data->total ?? 0));
        if ($total < 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $this->page  = $page;
        $this->total = $total;
        $this->data  = (array) (is_array($data) ? ($data['data'] ?? []) : ($data->data ?? []));
    }

    /** @phpstan-return T[] */
    public function getData(): array
    {
        return $this->data;
    }

    /** @phpstan-return int<0, max> */
    public function getCurrentPage(): int
    {
        return $this->page;
    }

    /** @phpstan-return int<0, max> */
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
