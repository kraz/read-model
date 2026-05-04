<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Query\FilterExpression;
use Kraz\ReadModel\Query\SortExpression;

/**
 * Provides basic behavior for a builder of ReadDataProviderInterface.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 */
interface ReadDataProviderBuilderInterface
{
    /**
     * Apply where filter expression.
     *
     * Note: this adds a query expression if one does not exist already. If there are already more than one query
     * expression assigned then it will use the last one!
     *
     * @phpstan-return self<T>
     */
    public function andWhere(FilterExpression ...$expr): self;

    /**
     * Apply where filter expression.
     *
     * Note: this adds a query expression if one does not exist already. If there are already more than one query
     * expression assigned then it will use the last one!
     *
     * @phpstan-return self<T>
     */
    public function orWhere(FilterExpression ...$expr): self;

    /**
     * Apply sorting.
     *
     * Note: this adds a query expression if one does not exist already. If there are already more than one query
     * expression assigned then it will use the last one!
     *
     * @phpstan-return self<T>
     */
    public function sortBy(string $field, string $dir = SortExpression::DIR_ASC): self;

    /**
     * @phpstan-param array<string, string>|null $fieldMapping
     *
     * @phpstan-return static<T>
     */
    public function withFieldMapping(array|null $fieldMapping): static;

    /**
     * @phpstan-param string[]|string|null $rootAlias
     *
     * @phpstan-return static<T>
     */
    public function withRootAlias(array|string|null $rootAlias): static;

    /**
     * @phpstan-param string[]|string|null $rootIdentifier
     *
     * @phpstan-return static<T>
     */
    public function withRootIdentifier(array|string|null $rootIdentifier): static;

    /**
     * Apply the current builder state to a ReadDataProviderInterface
     *
     * @phpstan-param J $dataProvider
     *
     * @phpstan-return J
     *
     * @phpstan-template J of ReadDataProviderInterface<object|array<string, mixed>>
     */
    public function apply(ReadDataProviderInterface $dataProvider): ReadDataProviderInterface;
}
