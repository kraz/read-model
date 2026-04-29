<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Countable;
use IteratorAggregate;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryRequest;
use Traversable;

/**
 * @phpstan-template T of object|array<string, mixed>
 * @phpstan-extends IteratorAggregate<array-key, T>
 */
interface ReadDataProviderInterface extends IteratorAggregate, Countable
{
    /** @phpstan-return int<0, max> */
    public function totalCount(): int;

    public function isPaginated(): bool;

    public function isEmpty(): bool;

    public function isValue(): bool;

    public function qry(): QueryExpression;

    public function expr(): FilterExpression;

    /** @return Traversable<array-key, T> */
    public function getIterator(): Traversable;

    /** @return T[] */
    public function data(): array;

    /** @return T[]|ReadResponse<T> */
    public function getResult(): array|ReadResponse;

    /** @return PaginatorInterface<T>|null */
    public function paginator(): PaginatorInterface|null;

    /**
     * @phpstan-param int<0, max> $page
     * @phpstan-param int<0, max> $itemsPerPage
     *
     * @phpstan-return static<T>
     */
    public function withPagination(int $page, int $itemsPerPage): static;

    /** @phpstan-return static<T> */
    public function withoutPagination(): static;

    /** @return QueryExpression[] */
    public function queryExpressions(): array;

    /** @phpstan-return static<T> */
    public function withQueryExpression(QueryExpression $queryExpression): static;

    /** @phpstan-return static<T> */
    public function withoutQueryExpression(bool $undo = false): static;

    /** @phpstan-return static<T> */
    public function withQueryModifier(callable $modifier): static;

    /** @phpstan-return static<T> */
    public function withoutQueryModifier(bool $undo = false): static;

    /** @phpstan-return static<T> */
    public function withQueryRequest(QueryRequest $queryRequest): static;

    /**
     * @phpstan-param array<string, mixed>  $input
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool>   $fieldsIgnoreCase
     *
     * @phpstan-return static<T>
     */
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static;

    /**
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool>   $fieldsIgnoreCase
     *
     * @phpstan-return static<T>
     */
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static;
}
