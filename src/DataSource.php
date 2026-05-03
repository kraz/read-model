<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayIterator;
use IteratorAggregate;
use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Pagination\InMemoryPaginator;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\QueryExpressionProvider;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Tools\TraversableTransformer;
use LogicException;
use Override;
use Traversable;

use function array_filter;
use function array_values;
use function count;
use function is_array;
use function is_object;
use function iterator_to_array;

/**
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @phpstan-implements ReadDataProviderInterface<T>
 */
class DataSource implements ReadDataProviderInterface
{
    /** @use BasicReadDataProvider<T> */
    use BasicReadDataProvider;

    private QueryExpressionProviderInterface|null $queryExpressionProvider = null;
    private ReadModelDescriptorFactoryInterface|null $descriptorFactory    = null;

    /** @phpstan-var callable */
    private mixed $itemNormalizer;

    /** @phpstan-param ReadDataProviderInterface<T>|PaginatorInterface<T>|IteratorAggregate<array-key, T>|iterable<T>|null $data */
    public function __construct(
        private ReadDataProviderInterface|PaginatorInterface|IteratorAggregate|iterable|null $data,
        callable|null $itemNormalizer = null,
    ) {
        $this->itemNormalizer = $itemNormalizer ?? static fn (mixed $item): mixed => $item;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        $paginator = $this->paginator();
        if ($paginator !== null) {
            $iterator = $paginator->getIterator();
        } else {
            $iterator = new ArrayIterator($this->filteredItems());
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
        return $this->totalCount() === 0;
    }

    #[Override]
    public function count(): int
    {
        $paginator = $this->paginator();
        if ($paginator !== null) {
            return $paginator->count();
        }

        return count($this->filteredItems());
    }

    #[Override]
    public function totalCount(): int
    {
        $paginator = $this->paginator();
        if ($paginator !== null) {
            return $paginator->getTotalItems();
        }

        return count($this->filteredItems());
    }

    #[Override]
    public function data(): array
    {
        return iterator_to_array($this->getIterator());
    }

    #[Override]
    public function getResult(): array|ReadResponse
    {
        if ($this->isValue()) {
            return $this->data();
        }

        $data  = $this->data();
        $page  = $this->isPaginated() ? ($this->paginator()?->getCurrentPage() ?? 1) : 1;
        $total = $this->totalCount();

        /** @phpstan-var ReadResponse<T> $result */
        $result = ReadResponse::create($data, $page, $total);

        return $result;
    }

    #[Override]
    public function paginator(): PaginatorInterface|null
    {
        if ($this->pagination !== null) {
            $items                 = $this->filteredItems();
            [$page, $itemsPerPage] = $this->pagination;

            /** @phpstan-var PaginatorInterface<T> $paginator */
            $paginator = new InMemoryPaginator(new ArrayIterator($items), count($items), $page, $itemsPerPage);

            return $paginator;
        }

        if (count($this->queryExpressions) > 0 || count($this->queryModifiers) > 0 || count($this->specifications) > 0) {
            return null;
        }

        if ($this->data instanceof PaginatorInterface) {
            /** @phpstan-var PaginatorInterface<T> $paginator */
            $paginator = $this->data;

            return $paginator;
        }

        if ($this->data instanceof ReadDataProviderInterface) {
            /** @phpstan-var PaginatorInterface<T>|null $paginator */
            $paginator = $this->data->paginator();

            return $paginator;
        }

        return null;
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

    /** @phpstan-return list<T> */
    private function filteredItems(): array
    {
        $specQEs = [];
        foreach ($this->specifications as $specification) {
            $qe = $specification->getQueryExpression();
            if ($qe === null || $qe->isEmpty()) {
                continue;
            }

            $specQEs[] = $qe;
        }

        $allQEs = [...$specQEs, ...$this->queryExpressions];

        if (! ($this->data instanceof ReadDataProviderInterface) && count($this->queryModifiers) > 0) {
            throw new LogicException('Unsupported operation. The data source does not support query modifier.');
        }

        if ($this->data instanceof ReadDataProviderInterface) {
            $provider = $this->data;

            foreach ($this->queryModifiers as $modifier) {
                /** @phpstan-var ReadDataProviderInterface<T> $provider */
                $provider = $provider->withQueryModifier($modifier, true);
            }

            foreach ($allQEs as $qe) {
                $provider = $provider->withQueryExpression($qe, true);
            }

            /** @phpstan-var array<array-key, T> $items */
            $items = $provider->data();
        } elseif ($this->data === null) {
            $items = [];
        } else {
            if (is_array($this->data)) {
                /** @phpstan-var array<array-key, T> $items */
                $items = $this->data;
            } elseif ($this->data instanceof IteratorAggregate) {
                /** @phpstan-var array<array-key, T> $items */
                $items = iterator_to_array($this->data->getIterator());
            } else {
                /** @phpstan-var array<array-key, T> $items */
                $items = iterator_to_array($this->data);
            }

            if (count($items) > 0 && count($allQEs) > 0) {
                /** @phpstan-var ArrayCollection<array-key, T> $collection */
                $collection = new ArrayCollection($items);
                $first      = $collection->first();
                $descriptor = is_object($first)
                    ? $this->getDescriptorFactory()->createReadModelDescriptorFrom($first)
                    : null;

                foreach ($allQEs as $queryExpression) {
                    /** @phpstan-var ArrayCollection<array-key, T> $collection */
                    $collection = $this->getQueryExpressionProvider()->apply($collection, $queryExpression, $descriptor);
                }

                $items = array_values($collection->toArray());
            }
        }

        if (count($this->specifications) > 0) {
            $specifications = $this->specifications;
            $items          = array_values(array_filter($items, static function (mixed $item) use ($specifications): bool {
                foreach ($specifications as $specification) {
                    /** @phpstan-var T $item */
                    if (! $specification->isSatisfiedBy($item)) {
                        return false;
                    }
                }

                return true;
            }));
        }

        /** @phpstan-var list<T> $values */
        $values = array_values($items);

        return $values;
    }
}
