<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\QueryExpression;
use Kraz\ReadModel\Query\QueryExpressionProviderInterface;
use Kraz\ReadModel\Query\SortExpression;
use Kraz\ReadModel\Specification\SpecificationInterface;
use Override;

use function count;
use function is_callable;
use function max;

/**
 * Provides basic behavior for a builder of ReadDataProviderInterface.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 */
trait ReadDataProviderBuilder
{
    /** @use ReadDataProviderComposition<T> */
    use ReadDataProviderComposition;

    private mixed $itemNormalizer = null;
    /** @phpstan-var string[]|string|null */
    private array|string|null $rootAlias = null;
    /** @phpstan-var string[]|string|null */
    private array|string|null $rootIdentifier = null;
    /** @var array<string, string>|null */
    private array|null $fieldMapping                                       = null;
    private ReadModelDescriptorFactoryInterface|null $descriptorFactory    = null;
    private QueryExpressionProviderInterface|null $queryExpressionProvider = null;

    /** @phpstan-return self<T> */
    #[Override]
    public function andWhere(FilterExpression ...$expr): self
    {
        // Modify the latest query expression or create new one if not exist
        $index                          = max(count($this->queryExpressions) - 1, 0);
        $item                           = $this->queryExpressions[$index] ?? QueryExpression::create();
        $this->queryExpressions[$index] = $item->andWhere(...$expr);

        return $this;
    }

    /** @phpstan-return self<T> */
    #[Override]
    public function orWhere(FilterExpression ...$expr): self
    {
        // Modify the latest query expression or create new one if not exist
        $index                          = max(count($this->queryExpressions) - 1, 0);
        $item                           = $this->queryExpressions[$index] ?? QueryExpression::create();
        $this->queryExpressions[$index] = $item->orWhere(...$expr);

        return $this;
    }

    /** @phpstan-return self<T> */
    #[Override]
    public function sortBy(string $field, string $dir = SortExpression::DIR_ASC): self
    {
        // Modify the latest query expression or create new one if not exist
        $index                          = max(count($this->queryExpressions) - 1, 0);
        $item                           = $this->queryExpressions[$index] ?? QueryExpression::create();
        $this->queryExpressions[$index] = $item->sortBy($field, $dir);

        return $this;
    }

    #[Override]
    public function withFieldMapping(array|null $fieldMapping): static
    {
        /** @phpstan-var static<T> $clone */
        $clone               = clone $this;
        $clone->fieldMapping = $fieldMapping;

        return $clone;
    }

    #[Override]
    public function withRootAlias(array|string|null $rootAlias): static
    {
        /** @phpstan-var static<T> $clone */
        $clone            = clone $this;
        $clone->rootAlias = $rootAlias;

        return $clone;
    }

    #[Override]
    public function withRootIdentifier(array|string|null $rootIdentifier): static
    {
        /** @phpstan-var static<T> $clone */
        $clone                 = clone $this;
        $clone->rootIdentifier = $rootIdentifier;

        return $clone;
    }

    /**
     * Apply the current builder state to a ReadDataProviderInterface
     *
     * @phpstan-param J $dataProvider
     *
     * @phpstan-return J
     *
     * @phpstan-template J of ReadDataProviderInterface<object|array<string, mixed>>
     */
    #[Override]
    public function apply(ReadDataProviderInterface $dataProvider): ReadDataProviderInterface
    {
        $queryExpressionProvider = $this->queryExpressionProvider !== null ? clone $this->queryExpressionProvider : null;

        if ($this->rootAlias !== null) {
            $queryExpressionProvider ??= $this->getOrCreateQueryExpressionProvider();
            $queryExpressionProvider->setRootAlias($this->rootAlias);
        }

        if ($this->rootIdentifier !== null) {
            $queryExpressionProvider ??= $this->getOrCreateQueryExpressionProvider();
            $queryExpressionProvider->setRootIdentifier($this->rootIdentifier);
        }

        if ($this->fieldMapping !== null) {
            $queryExpressionProvider ??= $this->getOrCreateQueryExpressionProvider();
            $queryExpressionProvider->setFieldMapping($this->fieldMapping);
        }

        if ($queryExpressionProvider !== null) {
            $dataProvider = $dataProvider->withQueryExpressionProvider($queryExpressionProvider);
        } elseif ($this->descriptorFactory !== null) {
            $dataProvider = $dataProvider->withDescriptorFactory($this->descriptorFactory);
        }

        if ($this->itemNormalizer !== null) {
            $dataProvider = $dataProvider->withItemNormalizer($this->itemNormalizer);
        }

        if ($this->pagination !== null) {
            [$page, $itemsPerPage] = $this->pagination;
            $dataProvider          = $dataProvider->withPagination($page, $itemsPerPage);
        }

        /** @phpstan-var SpecificationInterface<object|array<string, mixed>> $specification */
        foreach ($this->specifications as $specification) {
            $dataProvider = $dataProvider->withSpecification($specification, true);
        }

        foreach ($this->queryModifiers as $modifier) {
            if (! is_callable($modifier)) {
                continue;
            }

            $dataProvider = $dataProvider->withQueryModifier($modifier, true);
        }

        foreach ($this->queryExpressions as $qe) {
            $dataProvider = $dataProvider->withQueryExpression($qe, true);
        }

        if ($this->readModelDescriptor !== null) {
            $dataProvider = $dataProvider->withReadModelDescriptor($this->readModelDescriptor);
        }

        /** @phpstan-var J $dataProvider */
        return $dataProvider;
    }
}
