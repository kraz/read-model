<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Countable;
use IteratorAggregate;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Traversable;

/**
 * Provides domain specific notation for working with a Read Model.
 *
 * @phpstan-template T of object|array<string, mixed>
 * @phpstan-extends IteratorAggregate<array-key, T>
 */
interface ReadDataProviderInterface extends IteratorAggregate, Countable
{
    /**
     * Get query expression instance for easier flow calls.
     */
    public function qry(): QueryExpression;

    /**
     * Get filter expression instance for easier flow calls.
     */
    public function expr(): FilterExpression;

    /**
     * Check if the data is in pagination mode.
     */
    public function isPaginated(): bool;

    /**
     * Check if there is any data available.
     */
    public function isEmpty(): bool;

    /**
     * The current data is filtered exclusively for obtaining a one or more records by their identifier.
     */
    public function isValue(): bool;

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
     * @return T[]|ReadResponse<T>
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
     * Enable data pagination.
     *
     * @phpstan-param int<0, max> $page
     * @phpstan-param int<0, max> $itemsPerPage
     *
     * @phpstan-return static<T>
     */
    public function withPagination(int $page, int $itemsPerPage): static;

    /**
     * Remove the data pagination and work with the whole data set.
     *
     * @phpstan-return static<T>
     */
    public function withoutPagination(): static;

    /**
     * Get list of the currently applied query expressions
     *
     * @return QueryExpression[]
     */
    public function queryExpressions(): array;

    /**
     * Apply query expression.
     *
     * @phpstan-return static<T>
     */
    public function withQueryExpression(QueryExpression $queryExpression): static;

    /**
     * Remove query expression.
     *
     * When `$undo` is `TRUE` the query expressions are reverted to the state before calling the last
     * `withQueryExpression`. When `$undo` is `FALSE` (the default behavior) it clears all applied query expressions.
     *
     * @phpstan-return static<T>
     */
    public function withoutQueryExpression(bool $undo = false): static;

    /**
     * Add query modifier function.
     *
     * @phpstan-return static<T>
     */
    public function withQueryModifier(callable $modifier): static;

    /**
     * Remove query modifier.
     *
     * When `$undo` is `TRUE` the query modifiers are reverted to the state before calling the last
     * `withQueryModifier`. When `$undo` is `FALSE` (the default behavior) it clears all applied query modifiers.
     *
     * @phpstan-return static<T>
     */
    public function withoutQueryModifier(bool $undo = false): static;

    /**
     * Apply a specification for filtering.
     *
     * The specification's getQueryExpression() is used for query-level filtering optimization,
     * while isSatisfiedBy() is called on each element during iteration.
     *
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @phpstan-return static<T>
     */
    public function withSpecification(SpecificationInterface $specification): static;

    /**
     * Remove specification.
     *
     * When `$undo` is `TRUE` the specifications are reverted to the state before calling the last
     * `withSpecification`. When `$undo` is `FALSE` (the default behavior) it clears all applied specifications.
     *
     * @phpstan-return static<T>
     */
    public function withoutSpecification(bool $undo = false): static;

    /**
     * Apply query expression and/or pagination with single request payload
     *
     * @phpstan-return static<T>
     */
    public function withQueryRequest(QueryRequest $queryRequest): static;

    /**
     * Apply query expression and/or pagination from single input array
     *
     * @phpstan-param array<string, mixed>  $input
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool>   $fieldsIgnoreCase
     *
     * @phpstan-return static<T>
     */
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static;

    /**
     * Apply query expression and/or pagination from single request object
     *
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool>   $fieldsIgnoreCase
     *
     * @phpstan-return static<T>
     */
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static;
}
