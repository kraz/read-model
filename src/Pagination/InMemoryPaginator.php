<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination;

use EmptyIterator;
use Exception;
use InvalidArgumentException;
use IteratorIterator;
use LimitIterator;
use Override;
use ReturnTypeWillChange;
use Traversable;

use function ceil;
use function iterator_count;
use function max;

/**
 * @template T of object|array<string, mixed>
 * @implements PaginatorInterface<T>
 */
final readonly class InMemoryPaginator implements PaginatorInterface
{
    /** @phpstan-var int<0, max> */
    private int $offset;
    /** @phpstan-var int<0, max> */
    private int $limit;
    /** @phpstan-var int<0, max> */
    private int $lastPage;

    /**
     * @phpstan-param Traversable<T> $items
     * @phpstan-param int<0, max> $totalItems
     * @phpstan-param int<0, max> $currentPage
     * @phpstan-param int<0, max> $itemsPerPage
     */
    public function __construct(
        private Traversable $items,
        private int $totalItems,
        private int $currentPage,
        private int $itemsPerPage,
    ) {
        if ($totalItems < 0) {
            throw new InvalidArgumentException('Expected a value greater than or equal to 0.');
        }

        if ($currentPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($itemsPerPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        $this->offset = ($currentPage - 1) * $itemsPerPage;
        $this->limit  = $itemsPerPage;

        /** @phpstan-var int<0, max> $lastPage */
        $lastPage       = (int) max(1, ceil($totalItems / $itemsPerPage));
        $this->lastPage = $lastPage;
    }

    #[Override]
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    #[Override]
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    #[Override]
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    #[Override]
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /** @throws Exception */
    #[Override]
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /** @return Traversable<array-key, T> */
    #[ReturnTypeWillChange]
    public function getIterator(): Traversable
    {
        if ($this->currentPage > $this->lastPage) {
            return new EmptyIterator();
        }

        return new LimitIterator(new IteratorIterator($this->items), $this->offset, $this->limit);
    }
}
