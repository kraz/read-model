<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayIterator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use Kraz\ReadModel\Collections\Criteria;
use Kraz\ReadModel\Collections\ReadableCollection;
use Kraz\ReadModel\Pagination\InMemoryPaginator;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProvider;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\QueryRequest;
use Kraz\ReadModel\Tools\TraversableTransformer;
use LogicException;
use Override;
use Traversable;

use function count;
use function is_array;
use function is_object;
use function iterator_count;
use function iterator_to_array;

/**
 * @template T of object|array<string, mixed>
 * @implements ReadDataProviderInterface<T>
 */
class DataSource implements ReadDataProviderInterface
{
    use BasicReadDataProvider;

    private QueryExpressionProviderInterface|null $queryExpressionProvider = null;
    private ReadModelDescriptorFactoryInterface|null $descriptorFactory    = null;
    /** @phpstan-var ReadableCollection<array-key, T>|null */
    private ReadableCollection|null $all = null;
    private bool $paginationDisabled     = false;
    private int|null $page               = null;
    private int|null $itemsPerPage       = null;

    /** @phpstan-var callable */
    private mixed $itemNormalizer;

    /** @phpstan-param ReadDataProviderInterface<T>|PaginatorInterface<T>|IteratorAggregate<array-key, T>|iterable<T>|null $data */
    public function __construct(private ReadDataProviderInterface|PaginatorInterface|IteratorAggregate|iterable|null $data, callable|null $itemNormalizer = null)
    {
        $this->itemNormalizer = $itemNormalizer ?? static fn (mixed $item): mixed => $item;
    }

    /** @return Traversable<array-key, T> */
    #[Override]
    public function getIterator(): Traversable
    {
        $data     = $this->paginator() ?? $this->data;
        $iterator = null;

        if ($data instanceof IteratorAggregate) {
            $iterator = $data->getIterator();
        }

        if ($data instanceof Traversable) {
            $iterator = $data;
        }

        if (is_array($data)) {
            $iterator = new ArrayIterator($data);
        }

        if ($iterator === null) {
            return;
        }

        if ($iterator instanceof Iterator) {
            $iterator->rewind();
        }

        $itemNormalizer = $this->itemNormalizer;
        /** @phpstan-var Traversable<array-key, T> $items */
        $items = new TraversableTransformer($iterator, $itemNormalizer(...))->getIterator();

        yield from $items;
    }

    #[Override]
    public function isPaginated(): bool
    {
        return $this->paginator() !== null;
    }

    #[Override]
    public function isEmpty(): bool
    {
        if ($this->data === null) {
            return true;
        }

        if ($this->data instanceof ReadDataProviderInterface) {
            return $this->data->isEmpty();
        }

        foreach ($this->getIterator() as $item) {
            return ! is_object($item) && ! is_array($item);
        }

        return true;
    }

    #[Override]
    public function count(): int
    {
        if ($this->data === null) {
            return 0;
        }

        if ($this->data instanceof ReadDataProviderInterface) {
            return $this->data->count();
        }

        $paginator = $this->paginator();
        if ($paginator !== null) {
            return $paginator->count();
        }

        if (is_array($this->data)) {
            return count($this->data);
        }

        return iterator_count($this->getIterator());
    }

    #[Override]
    public function totalCount(): int
    {
        if ($this->data === null) {
            return 0;
        }

        if ($this->data instanceof ReadDataProviderInterface) {
            return $this->data->totalCount();
        }

        $paginator = $this->paginator();
        if ($paginator !== null) {
            return $paginator->getTotalItems();
        }

        $data = $this->data();

        return count($data);
    }

    /** @phpstan-return T[] */
    #[Override]
    public function data(): array
    {
        return iterator_to_array($this->getIterator());
    }

    /** @phpstan-return T[]|ReadResponse<T> */
    #[Override]
    public function getResult(): array|ReadResponse
    {
        if ($this->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data = $this->data;

            return $data->getResult();
        }

        if ($this->isValue()) {
            return $this->data();
        }

        $data  = $this->data();
        $page  = $this->isPaginated() ? ($this->paginator()?->getCurrentPage() ?? 1) : 1;
        $total = $this->totalCount();

        /** @phpstan-var ReadResponse<T> $result  */
        $result = ReadResponse::create($data, $page, $total);

        return $result;
    }

    /** @return PaginatorInterface<T>|null */
    #[Override]
    public function paginator(): PaginatorInterface|null
    {
        if ($this->paginationDisabled || $this->data === null) {
            return null;
        }

        if ($this->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var PaginatorInterface<T> $paginator */
            $paginator = $this->data->paginator();

            return $paginator;
        }

        if ($this->data instanceof PaginatorInterface) {
            /** @phpstan-var PaginatorInterface<T> $paginator */
            $paginator = $this->data;

            return $paginator;
        }

        if ($this->page === null || $this->itemsPerPage === null) {
            return null;
        }

        $items = null;

        if ($this->data instanceof IteratorAggregate) {
            /** @phpstan-var Traversable<array-key, T> $items */
            $items = $this->data->getIterator();
        }

        if ($this->data instanceof Traversable) {
            /** @phpstan-var Traversable<array-key, T> $items */
            $items = $this->data;
        }

        if (is_array($this->data)) {
            /** @phpstan-var Traversable<array-key, T> $items */
            $items = new ArrayIterator($this->data);
        }

        if (! $items instanceof Traversable) {
            return null;
        }

        $items = iterator_to_array($items);
        $count = count($items);

        /** @phpstan-var Traversable<array-key, T> $items */
        $items = new ArrayIterator($items);

        $itemNormalizer = $this->itemNormalizer;
        /** @phpstan-var Traversable<array-key, T> $items */
        $items = new TraversableTransformer($items, $itemNormalizer(...))->getIterator();

        return new InMemoryPaginator($items, $count, $this->page, $this->itemsPerPage);
    }

    /** @phpstan-return static<T> */
    #[Override]
    public function withPagination(int $page, int $itemsPerPage): static
    {
        if ($page <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        if ($itemsPerPage <= 0) {
            throw new InvalidArgumentException('Expected a positive integer.');
        }

        /** @phpstan-var static<T> $cloned */
        $cloned                     = clone $this;
        $cloned->paginationDisabled = false;

        if ($cloned->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data         = $cloned->data->withPagination($page, $itemsPerPage);
            $cloned->data = $data;
        }

        if ($cloned->data instanceof PaginatorInterface) {
            if (! ($page === $cloned->data->getCurrentPage() && $itemsPerPage === $cloned->data->getItemsPerPage())) {
                throw new InvalidArgumentException('The data provider does not support changing the pagination parameters.');
            }
        }

        $cloned->page         = $page;
        $cloned->itemsPerPage = $itemsPerPage;

        if ($cloned->paginator() === null) {
            throw new InvalidArgumentException('The data provider does not support pagination.');
        }

        return $cloned;
    }

    #[Override]
    public function withoutPagination(): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned                     = clone $this;
        $cloned->paginationDisabled = true;

        if ($cloned->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data         = $cloned->data->withoutPagination();
            $cloned->data = $data;
        }

        $cloned->page         = null;
        $cloned->itemsPerPage = null;

        return $cloned;
    }

    #[Override]
    public function withQueryExpression(QueryExpression $queryExpression): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($cloned->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data         = $cloned->data->withQueryExpression($queryExpression);
            $cloned->data = $data;

            return $cloned;
        }

        if ($cloned->data instanceof ReadableCollection) {
            if ($cloned->all === null) {
                $cloned->all = $cloned->data->matching(new Criteria());
            }

            if ($cloned->data->count() > 0) {
                $model = $cloned->data->first();
                if (! is_object($model)) {
                    throw new LogicException('Unsupported operation. The data source elements are not objects.');
                }

                $descriptor   = $this->getDescriptorFactory()->createReadModelDescriptorFrom($model);
                $cloned->data = $this->getQueryExpressionProvider()->apply($cloned->data, $queryExpression, $descriptor);
            }

            return $cloned;
        }

        throw new LogicException('Unsupported operation. The data source does not support query expression modifier.');
    }

    #[Override]
    public function withoutQueryExpression(): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($cloned->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data         = $cloned->data->withoutQueryExpression();
            $cloned->data = $data;
        }

        if ($cloned->data instanceof ReadableCollection) {
            if ($cloned->all !== null) {
                $cloned->data = $cloned->all;
                $cloned->all  = null;
            }
        }

        return $cloned;
    }

    #[Override]
    public function withQueryRequest(QueryRequest $queryRequest): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;
        if ($queryRequest->getQuery() !== null) {
            $cloned = $cloned->withQueryExpression($queryRequest->getQuery());
        }

        if ($queryRequest->getPage() !== null && $queryRequest->getItemsPerPage() !== null) {
            $cloned = $cloned->withPagination($queryRequest->getPage(), $queryRequest->getItemsPerPage());
        }

        return $cloned;
    }

    #[Override]
    public function queryExpressions(): array
    {
        if ($this->data instanceof ReadDataProviderInterface) {
            return $this->data->queryExpressions();
        }

        return [];
    }

    #[Override]
    public function withQueryModifier(callable $modifier): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($cloned->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data         = $cloned->data->withQueryModifier($modifier);
            $cloned->data = $data;

            return $cloned;
        }

        throw new LogicException('Unsupported operation. The data source does not support query modifier.');
    }

    #[Override]
    public function withoutQueryModifier(): static
    {
        /** @phpstan-var static<T> $cloned */
        $cloned = clone $this;

        if ($cloned->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var ReadDataProviderInterface<T> $data */
            $data         = $cloned->data->withoutQueryModifier();
            $cloned->data = $data;
        }

        return $cloned;
    }

    #[Override]
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        throw new LogicException('Unsupported operation. The data source can not handle requests.');
    }

    public function getQueryExpressionProvider(): QueryExpressionProviderInterface
    {
        return $this->queryExpressionProvider ??= new QueryExpressionProvider($this->getDescriptorFactory());
    }

    public function setQueryExpressionProvider(QueryExpressionProviderInterface|null $queryExpressionProvider): void
    {
        $this->queryExpressionProvider = $queryExpressionProvider;
    }

    public function getDescriptorFactory(): ReadModelDescriptorFactoryInterface
    {
        return $this->descriptorFactory ??= new ReadModelDescriptorFactory();
    }

    public function setDescriptorFactory(ReadModelDescriptorFactoryInterface|null $descriptorFactory): void
    {
        $this->descriptorFactory = $descriptorFactory;
    }
}
