<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Countable;
use IteratorAggregate;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Traversable;

/**
 * Provides domain specific notation for working with a Read Model.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @phpstan-extends ReadDataProviderCompositionInterface<T>
 * @phpstan-extends IteratorAggregate<array-key, T>
 */
interface ReadDataProviderInterface extends ReadDataProviderCompositionInterface, IteratorAggregate, Countable
{
    /**
     * Check if the data is in pagination mode.
     */
    public function isPaginated(): bool;

    /**
     * Check if there is any data available.
     */
    public function isEmpty(): bool;

    /**
     * Ge the total count of data items available.
     *
     * @phpstan-return int<0, max>
     */
    public function totalCount(): int;

    /**
     * Get plain list of data items.
     *
     * @return T[]
     */
    public function data(): array;

    /**
     * Get structured data object, which is more convenient for transferring state.
     *
     * @return T[]|ReadResponse<covariant T>
     */
    public function getResult(): array|ReadResponse;

    /**
     * Get instance of the paginator.
     *
     * @return PaginatorInterface<T>|null
     */
    public function paginator(): PaginatorInterface|null;

    /**
     * Get instance of the data iterator.
     *
     * @return Traversable<array-key, T>
     */
    public function getIterator(): Traversable;

    /**
     * Fetches items in batches using limit/offset, filters them through specifications
     * in memory, and stops as soon as the requested number of matching items is collected.
     *
     * @phpstan-param non-empty-array<SpecificationInterface<contravariant T>> $specifications
     * @phpstan-param int<0, max>|null                                         $limit
     * @phpstan-param int<0, max>                                              $offset
     * @phpstan-param int<0, max>|null                                         $batchSize
     *
     * @return Traversable<array-key, T>
     */
    public function specificationsIterator(array $specifications, int|null $limit = null, int $offset = 0, int|null $batchSize = null): Traversable;
}
