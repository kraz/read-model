<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use ArrayIterator;
use IteratorAggregate;
use Kraz\ReadModel\Collections\ArrayCollection;
use Kraz\ReadModel\Pagination\InMemoryPaginator;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProvider;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Kraz\ReadModel\Tools\CollectionUtils;
use Kraz\ReadModel\Tools\TraversableTransformer;
use LogicException;
use Override;
use Traversable;

use function array_filter;
use function array_slice;
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
    /** @use ReadDataProviderComposition<T> */
    use ReadDataProviderComposition;
    /** @use ReadDataProviderAccess<T> */
    use ReadDataProviderAccess;

    /** @phpstan-param ReadDataProviderInterface<T>|PaginatorInterface<T>|IteratorAggregate<array-key, T>|iterable<T>|null $data */
    public function __construct(
        private ReadDataProviderInterface|PaginatorInterface|IteratorAggregate|iterable|null $data,
        callable|null $itemNormalizer = null,
    ) {
        if ($itemNormalizer === null) {
            return;
        }

        $this->itemNormalizer = $itemNormalizer;
    }

    protected function createDefaultDescriptorFactory(): ReadModelDescriptorFactoryInterface
    {
        return new ReadModelDescriptorFactory();
    }

    protected function createDefaultQueryExpressionProvider(ReadModelDescriptorFactoryInterface $factory): QueryExpressionProviderInterface
    {
        $provider = new QueryExpressionProvider($factory);
        $provider->setRootIdentifier('id');

        return $provider;
    }

    #[Override]
    public function getIterator(): Traversable
    {
        $hasSpecs = count($this->specifications) > 0;

        if ($hasSpecs && $this->pagination !== null) {
            throw new LogicException('Cannot use pagination when specifications are set.');
        }

        $paginator = $hasSpecs ? null : $this->paginator();

        if ($paginator !== null) {
            $iterator = $paginator->getIterator();
        } else {
            $iterator = new ArrayIterator($this->filteredItems());
        }

        $itemNormalizer = $this->itemNormalizer ?? static fn (mixed $item): mixed => $item;
        /** @phpstan-var Traversable<array-key, T> $items */
        $items = new TraversableTransformer($iterator, $itemNormalizer(...))->getIterator();

        yield from $items;
    }

    #[Override]
    public function isPaginated(): bool
    {
        if (count($this->specifications) > 0) {
            return false;
        }

        return $this->paginator() !== null;
    }

    #[Override]
    public function isEmpty(): bool
    {
        $this->assertNoSpecifications();

        return $this->totalCount() === 0;
    }

    #[Override]
    public function count(): int
    {
        $this->assertNoSpecifications();

        $paginator = $this->paginator();
        if ($paginator !== null) {
            return $paginator->count();
        }

        return count($this->filteredItems());
    }

    #[Override]
    public function totalCount(): int
    {
        $this->assertNoSpecifications();

        $paginator = $this->paginator();
        if ($paginator !== null) {
            return $paginator->getTotalItems();
        }

        return count($this->filteredItems());
    }

    #[Override]
    public function data(): array
    {
        $data = iterator_to_array($this->getIterator());
        if ($this->isValue()) {
            $rootIdentifier = $this->getOrCreateQueryExpressionProvider()->requireSingleRootIdentifier();
            $values         = $this->collectInputValues();
            $this->assertAllValuesFound($data, $values, $rootIdentifier);

            return CollectionUtils::sortByIndex($data, $rootIdentifier, $values);
        }

        return $data;
    }

    #[Override]
    public function getResult(): array|ReadResponse
    {
        $this->assertNoSpecifications();

        $data = $this->data();

        if ($this->isValue()) {
            return $data;
        }

        $page  = $this->isPaginated() ? ($this->paginator()?->getCurrentPage() ?? 1) : 1;
        $total = $this->totalCount();

        /** @phpstan-var ReadResponse<T> $result */
        $result = ReadResponse::create($data, $page, $total);

        return $result;
    }

    /** @phpstan-return PaginatorInterface<T>|null */
    #[Override]
    public function paginator(): PaginatorInterface|null
    {
        $this->assertNoSpecifications();

        if ($this->pagination !== null) {
            $items                 = $this->filteredItems();
            [$page, $itemsPerPage] = $this->pagination;

            /** @phpstan-var PaginatorInterface<T> $paginator */
            $paginator = new InMemoryPaginator(new ArrayIterator($items), count($items), $page, $itemsPerPage);

            return $paginator;
        }

        if (count($this->queryExpressions) > 0 || count($this->queryModifiers) > 0) {
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

    private function assertNoSpecifications(): void
    {
        if (count($this->specifications) > 0) {
            throw new LogicException('Cannot use this method when specifications are set. Use getIterator() or data() instead.');
        }
    }

    #[Override]
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        throw new LogicException('Unsupported operation. The data source can not handle requests.');
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

            if (count($this->specifications) > 0 && $this->limit !== null) {
                foreach ($this->queryExpressions as $qe) {
                    $provider = $provider->withQueryExpression($qe, true);
                }

                /** @phpstan-var array{int<0, max>, int<0, max>|null} $limit */
                $limit                      = $this->limit;
                [$limitValue, $offsetValue] = $limit;

                /** @phpstan-var non-empty-array<array-key, SpecificationInterface<object|array<string, mixed>>> $specs */
                $specs = $this->specifications;
                /** @phpstan-var list<T> $items */
                $items = iterator_to_array($provider->specificationsIterator($specs, $limitValue, $offsetValue ?? 0));

                return $items;
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

                $descriptor = $this->readModelDescriptor;
                if ($descriptor === null) {
                    $first      = $collection->first();
                    $descriptor = is_object($first)
                        ? $this->getOrCreateDescriptorFactory()->createReadModelDescriptorFrom($first)
                        : null;
                }

                $queryExpressionProvider = $this->getOrCreateQueryExpressionProvider();
                $mergedValues            = $this->collectInputValues();
                if (count($mergedValues) > 0) {
                    foreach ($allQEs as $queryExpression) {
                        /** @phpstan-var ArrayCollection<array-key, T> $collection */
                        $collection = $queryExpressionProvider->apply(
                            $collection,
                            $queryExpression,
                            $descriptor,
                            [],
                            QueryExpressionProviderInterface::INCLUDE_DATA_FILTER | QueryExpressionProviderInterface::INCLUDE_DATA_SORT,
                        );
                    }

                    /** @phpstan-var ArrayCollection<array-key, T> $collection */
                    $collection = $queryExpressionProvider->apply(
                        $collection,
                        QueryExpression::create()->withValues($mergedValues),
                        $descriptor,
                    );
                } else {
                    foreach ($allQEs as $queryExpression) {
                        /** @phpstan-var ArrayCollection<array-key, T> $collection */
                        $collection = $queryExpressionProvider->apply($collection, $queryExpression, $descriptor);
                    }
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

        if ($this->limit !== null) {
            [$limitValue, $offsetValue] = $this->limit;
            /** @phpstan-var list<T> $values */
            $values = array_values(array_slice($values, $offsetValue ?? 0, $limitValue));
        }

        return $values;
    }
}
