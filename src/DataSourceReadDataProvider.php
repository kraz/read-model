<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Override;
use Traversable;

/** @phpstan-template-covariant T of object|array<string, mixed> */
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
    public function withoutPagination(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutPagination($undo);

        return $clone;
    }

    /** @return QueryExpression[] */
    #[Override]
    public function queryExpressions(): array
    {
        return $this->dataSource()->queryExpressions();
    }

    #[Override]
    public function withQueryExpression(QueryExpression $queryExpression, bool $append = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryExpression($queryExpression, $append);

        return $clone;
    }

    #[Override]
    public function withoutQueryExpression(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutQueryExpression($undo);

        return $clone;
    }

    #[Override]
    public function withQueryModifier(callable $modifier, bool $append = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryModifier($modifier, $append);

        return $clone;
    }

    #[Override]
    public function withoutQueryModifier(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutQueryModifier($undo);

        return $clone;
    }

    #[Override]
    public function withSpecification(SpecificationInterface $specification, bool $append = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withSpecification($specification, $append);

        return $clone;
    }

    #[Override]
    public function withoutSpecification(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutSpecification($undo);

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
