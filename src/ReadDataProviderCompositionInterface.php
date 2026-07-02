<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Specification\SpecificationInterface;

/**
 * Provides domain specific notation for composing a Read Model.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 */
interface ReadDataProviderCompositionInterface
{
    public const int DEFAULT_PAGE_SIZE   = 25;
    public const int DEFAULT_LIMIT_SIZE  = 25;
    public const int DEFAULT_CURSOR_SIZE = 25;

    /**
     * Get query expression instance for easier flow calls.
     */
    public function qry(): QueryExpression;

    /**
     * Get filter expression instance for easier flow calls.
     */
    public function expr(): FilterExpression;

    /**
     * The current data is filtered exclusively for obtaining a one or more records by their identifier.
     */
    public function isValue(): bool;

    /**
     * Enable data pagination with default page size and positioning on the first page.
     *
     * Setting this clears any limit and/or offset already set.
     * Setting this clears any cursor already set.
     *
     * @phpstan-return static<T>
     */
    public function withDefaultPagination(): static;

    /**
     * Enable data pagination.
     *
     * Setting this clears any limit and/or offset already set.
     * Setting this clears any cursor already set.
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
     * When `$undo` is `TRUE` the pagination is reverted to the state before calling the last
     * `withPagination`. When `$undo` is `FALSE` (the default behavior) it clears the pagination completely.
     *
     * @phpstan-return static<T>
     */
    public function withoutPagination(bool $undo = false): static;

    /**
     * Enable the limit and offset for the returned items with the default preset values.
     *
     * Setting this clears any limit and/or offset already set.
     * Setting this clears any cursor already set.
     *
     * @phpstan-return static<T>
     */
    public function withDefaultLimit(): static;

    /**
     * Limit the number of returned items, with an optional offset.
     *
     * Setting this clears any active pagination.
     * Setting this clears any cursor already set.
     *
     * @phpstan-param int<0, max> $limit
     * @phpstan-param int<0, max>|null $offset
     *
     * @phpstan-return static<T>
     */
    public function withLimit(int $limit, int|null $offset = null): static;

    /**
     * Remove the active limit/offset.
     *
     * When `$undo` is `TRUE` the limit is reverted to the state before calling the last
     * `withLimit`. When `$undo` is `FALSE` (the default behavior) it clears the limit completely.
     *
     * @phpstan-return static<T>
     */
    public function withoutLimit(bool $undo = false): static;

    /**
     * Enable data cursor-based pagination with default cursor size and positioning on the first page.
     *
     * Setting this clears any limit and/or offset already set.
     * Setting this clears any pagination already set.
     *
     * @phpstan-return static<T>
     */
    public function withDefaultCursor(): static;

    /**
     * Enable cursor-based (keyset) pagination.
     *
     * Pass `null` (or omit) for the first page when no cursor has yet been issued; pass
     * the opaque token from a previous response's `nextCursor`/`previousCursor` for
     * subsequent pages. `$limit` is the maximum number of items the next window should
     * contain. Setting cursor mode clears any active page-based pagination and any
     * active limit/offset. Cannot be combined with specifications (mirrors the existing
     * page+spec restriction).
     *
     * Setting this clears any limit and/or offset already set.
     * Setting this clears any pagination already set.
     *
     * @phpstan-param int<0, max> $limit
     *
     * @phpstan-return static<T>
     */
    public function withCursor(string|null $cursor, int $limit): static;

    /**
     * Remove cursor pagination state.
     *
     * When `$undo` is `TRUE` the cursor is reverted to the state before calling the last
     * `withCursor`. When `$undo` is `FALSE` (the default behavior) it clears cursor state
     * completely.
     *
     * @phpstan-return static<T>
     */
    public function withoutCursor(bool $undo = false): static;

    /**
     * Get list of the currently applied query expressions
     *
     * @phpstan-return QueryExpression[]
     */
    public function queryExpressions(): array;

    /**
     * Apply query expression.
     *
     * When `$append` is `FALSE` (the default) the existing query expressions are replaced by the given one.
     * When `$append` is `TRUE` the given query expression is appended to the current list.
     *
     * @phpstan-return static<T>
     */
    public function withQueryExpression(QueryExpression $queryExpression, bool $append = false): static;

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
     * When `$append` is `FALSE` (the default) the existing query modifiers are replaced by the given one.
     * When `$append` is `TRUE` the given modifier is appended to the current list.
     *
     * @phpstan-return static<T>
     */
    public function withQueryModifier(callable $modifier, bool $append = false): static;

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
     * Get list of the currently applied specifications
     *
     * @phpstan-return SpecificationInterface<covariant T>[]
     */
    public function specifications(): array;

    /**
     * Apply a specification for filtering.
     *
     * The specification's getQueryExpression() is used for query-level filtering optimization,
     * while isSatisfiedBy() is called on each element during iteration.
     *
     * When `$append` is `FALSE` (the default) the existing specifications are replaced by the given one.
     * When `$append` is `TRUE` the given specification is appended to the current list.
     *
     * @phpstan-param SpecificationInterface<contravariant T> $specification
     *
     * @phpstan-return static<T>
     */
    public function withSpecification(SpecificationInterface $specification, bool $append = false): static;

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
     * Apply query expression and/or pagination with single request payload.
     *
     * @phpstan-return static<T>
     */
    public function withQueryRequest(QueryRequest $queryRequest): static;

    /**
     * Assign read model descriptor.
     *
     * @phpstan-return static<T>
     */
    public function withReadModelDescriptor(ReadModelDescriptor $readModelDescriptor): static;

    /**
     * Remove the assigned read model descriptor.
     *
     * When `$undo` is `TRUE` the read model descriptor is reverted to the state before calling the last
     * `withReadModelDescriptor`. When `$undo` is `FALSE` (the default behavior) it clears the read model descriptors
     * previously assigned if any.
     *
     * @phpstan-return static<T>
     */
    public function withoutReadModelDescriptor(bool $undo = false): static;

    /**
     * Assign read model.
     *
     * Note: this will attempt to create model descriptor and if no descriptor factory is assigned it will use the default one!
     *
     * @phpstan-param object|class-string $model
     *
     * @phpstan-return static<T>
     */
    public function withReadModel(object|string $model): static;

    /**
     * Assign query expression provider.
     *
     * @phpstan-return static<T>
     */
    public function withQueryExpressionProvider(QueryExpressionProviderInterface $queryExpressionProvider): static;

    /**
     * Remove the assigned query expression provider.
     *
     * When `$undo` is `TRUE` the query expression provider is reverted to the state before calling the last
     * `withQueryExpressionProvider`. When `$undo` is `FALSE` (the default behavior) it clears the query expression providers
     * previously assigned if any.
     *
     * @phpstan-return static<T>
     */
    public function withoutQueryExpressionProvider(bool $undo = false): static;

    /**
     * Assign descriptor factory.
     *
     * @phpstan-return static<T>
     */
    public function withDescriptorFactory(ReadModelDescriptorFactoryInterface $descriptorFactory): static;

    /**
     * Remove the assigned descriptor factory.
     *
     * When `$undo` is `TRUE` the descriptor factory is reverted to the state before calling the last
     * `withQueryExpressionProvider`. When `$undo` is `FALSE` (the default behavior) it clears the descriptor factories
     * previously assigned if any.
     *
     * @phpstan-return static<T>
     */
    public function withoutDescriptorFactory(bool $undo = false): static;

    /**
     * Assign item normalizer.
     *
     * @phpstan-return static<T>
     */
    public function withItemNormalizer(callable $itemNormalizer): static;

    /**
     * Remove the assigned item normalizer.
     *
     * When `$undo` is `TRUE` the item normalizer is reverted to the state before calling the last
     * `withItemNormalizer`. When `$undo` is `FALSE` (the default behavior) it clears the item normalizers
     * previously assigned if any.
     *
     * @phpstan-return static<T>
     */
    public function withoutItemNormalizer(bool $undo = false): static;

    /**
     * Apply query expression and/or pagination from single input array.
     *
     * @phpstan-param array<string, mixed>  $input
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool>   $fieldsIgnoreCase
     *
     * @phpstan-return static<T>
     */
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static;

    /**
     * Apply query expression and/or pagination from single request object.
     *
     * @phpstan-param array<string, string> $fieldsOperator
     * @phpstan-param array<string, bool>   $fieldsIgnoreCase
     *
     * @phpstan-return static<T>
     */
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static;
}
