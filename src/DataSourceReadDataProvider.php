<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Pagination\Cursor\CursorPaginatorInterface;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Override;
use RuntimeException;
use Traversable;

use function is_callable;

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
    public function isCursored(): bool
    {
        return $this->dataSource()->isCursored();
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
    public function getResult(): array|ReadResponse|CursorReadResponse
    {
        return $this->dataSource()->getResult();
    }

    #[Override]
    public function getListResult(): array
    {
        return $this->dataSource()->getListResult();
    }

    #[Override]
    public function getPaginationResult(): ReadResponse
    {
        return $this->dataSource()->getPaginationResult();
    }

    #[Override]
    public function getCursorResult(): CursorReadResponse
    {
        return $this->dataSource()->getCursorResult();
    }

    #[Override]
    public function specificationsIterator(array $specifications, int|null $limit = null, int $offset = 0, int|null $batchSize = null): Traversable
    {
        return $this->dataSource()->specificationsIterator($specifications, $limit, $offset, $batchSize);
    }

    #[Override]
    public function paginator(): PaginatorInterface|null
    {
        return $this->dataSource()->paginator();
    }

    #[Override]
    public function cursorPaginator(): CursorPaginatorInterface|null
    {
        return $this->dataSource()->cursorPaginator();
    }

    #[Override]
    public function withDefaultPagination(): static
    {
        return $this->withPagination(1, self::DEFAULT_PAGE_SIZE);
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

    #[Override]
    public function withDefaultLimit(): static
    {
        return $this->withLimit(self::DEFAULT_LIMIT_SIZE, 0);
    }

    #[Override]
    public function withLimit(int $limit, int|null $offset = null): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withLimit($limit, $offset);

        return $clone;
    }

    #[Override]
    public function withoutLimit(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutLimit($undo);

        return $clone;
    }

    #[Override]
    public function withDefaultCursor(): static
    {
        return $this->withCursor(null, self::DEFAULT_CURSOR_SIZE);
    }

    #[Override]
    public function withCursor(string|null $cursor, int $limit): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withCursor($cursor, $limit);

        return $clone;
    }

    #[Override]
    public function withoutCursor(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutCursor($undo);

        return $clone;
    }

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
    public function specifications(): array
    {
        return $this->dataSource()->specifications();
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
    public function withReadModelDescriptor(ReadModelDescriptor $readModelDescriptor): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withReadModelDescriptor($readModelDescriptor);

        return $clone;
    }

    #[Override]
    public function withoutReadModelDescriptor(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutReadModelDescriptor($undo);

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
    public function withReadModel(object|string $model): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withReadModel($model);

        return $clone;
    }

    #[Override]
    public function withQueryExpressionProvider(QueryExpressionProviderInterface $queryExpressionProvider): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryExpressionProvider($queryExpressionProvider);

        return $clone;
    }

    #[Override]
    public function withoutQueryExpressionProvider(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutQueryExpressionProvider($undo);

        return $clone;
    }

    #[Override]
    public function withDescriptorFactory(ReadModelDescriptorFactoryInterface $descriptorFactory): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withDescriptorFactory($descriptorFactory);

        return $clone;
    }

    #[Override]
    public function withoutDescriptorFactory(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutDescriptorFactory($undo);

        return $clone;
    }

    #[Override]
    public function withItemNormalizer(callable $itemNormalizer): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withItemNormalizer($itemNormalizer);

        return $clone;
    }

    #[Override]
    public function withoutItemNormalizer(bool $undo = false): static
    {
        $clone             = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutItemNormalizer($undo);

        return $clone;
    }

    #[Override]
    public function handleInput(array $input, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        $clone      = clone $this;
        $dataSource = $clone->dataSource();
        $method     = [$dataSource::class, 'applyInputTo'];
        if (! is_callable($method)) {
            throw new RuntimeException('Can not apply the requested input to this data source!');
        }

        return $method($clone, $input, $fieldsOperator, $fieldsIgnoreCase);
    }

    #[Override]
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        $clone      = clone $this;
        $dataSource = $clone->dataSource();
        $method     = [$dataSource::class, 'applyRequestTo'];
        if (! is_callable($method)) {
            throw new RuntimeException('Can not apply the request to this data source!');
        }

        return $method($clone, $request, $fieldsOperator, $fieldsIgnoreCase);
    }
}
