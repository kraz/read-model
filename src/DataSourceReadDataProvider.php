<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryRequest;
use Override;
use Traversable;

/** @template-covariant T of object|array<string, mixed> */
trait DataSourceReadDataProvider
{
    /** @phpstan-var ReadDataProviderInterface<T>|null */
    private ReadDataProviderInterface|null $dataSource = null;

    /** @phpstan-return ReadDataProviderInterface<T> */
    abstract protected function createDataSource(): ReadDataProviderInterface;

    /** @phpstan-return ReadDataProviderInterface<T> */
    private function dataSource(): ReadDataProviderInterface
    {
        return $this->dataSource ??= $this->createDataSource();
    }

    public function reset(): static
    {
        $this->dataSource = $this->createDataSource();

        return $this;
    }

    public function qry(): QueryExpression
    {
        return QueryExpression::create();
    }

    public function expr(): FilterExpression
    {
        return FilterExpression::create();
    }

    #[Override]
    public function count(): int
    {
        return $this->dataSource()->count();
    }

    #[Override]
    public function totalCount(): int
    {
        return $this->dataSource()->totalCount();
    }

    #[Override]
    public function isPaginated(): bool
    {
        return $this->dataSource()->isPaginated();
    }

    #[Override]
    public function isEmpty(): bool
    {
        return $this->dataSource()->isEmpty();
    }

    #[Override]
    public function isValue(): bool
    {
        return $this->dataSource()->isValue();
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return $this->dataSource()->getIterator();
    }

    #[Override]
    public function data(): array
    {
        return $this->dataSource()->data();
    }

    #[Override]
    public function getResult(): array|ReadResponse
    {
        return $this->dataSource()->getResult();
    }

    #[Override]
    public function paginator(): PaginatorInterface|null
    {
        return $this->dataSource()->paginator();
    }

    #[Override]
    public function withPagination(int $page, int $itemsPerPage): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withPagination($page, $itemsPerPage);

        return $clone;
    }

    #[Override]
    public function withoutPagination(): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutPagination();

        return $clone;
    }

    /** @return QueryExpression[] */
    #[Override]
    public function queryExpressions(): array
    {
        return $this->dataSource()->queryExpressions();
    }

    #[Override]
    public function withQueryExpression(QueryExpression $queryExpression): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryExpression($queryExpression);

        return $clone;
    }

    #[Override]
    public function withoutQueryExpression(): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutQueryExpression();

        return $clone;
    }

    #[Override]
    public function withQueryModifier(callable $modifier): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryModifier($modifier);

        return $clone;
    }

    #[Override]
    public function withoutQueryModifier(): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutQueryModifier();

        return $clone;
    }

    #[Override]
    public function withQueryRequest(QueryRequest $queryRequest): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryRequest($queryRequest);

        return $clone;
    }

    #[Override]
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->handleInput($input, $fieldsOperator, $fieldsIgnoreCase);

        return $clone;
    }

    #[Override]
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->handleRequest($request, $fieldsOperator, $fieldsIgnoreCase);

        return $clone;
    }
}
