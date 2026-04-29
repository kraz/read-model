<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Pagination;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Provides domain specific notation for obtaining paginated data information.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @phpstan-extends IteratorAggregate<array-key, T>
 */
interface PaginatorInterface extends IteratorAggregate, Countable
{
    /** @phpstan-return int<0, max> */
    public function getCurrentPage(): int;

    /** @phpstan-return int<0, max> */
    public function getItemsPerPage(): int;

    /** @phpstan-return int<0, max> */
    public function getLastPage(): int;

    /** @phpstan-return int<0, max> */
    public function getTotalItems(): int;

    /** @return Traversable<array-key, T> */
    public function getIterator(): Traversable;
}
