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
     * Apply where filter expression. Always appends a new query expression.
     *
     * @phpstan-return static<T>
     */
    public function andWhere(FilterExpression ...$expr): static;

    /**
     * Apply where filter expression. Always appends a new query expression.
     *
     * @phpstan-return static<T>
     */
    public function orWhere(FilterExpression ...$expr): static;

    /**
     * Apply sorting. Always appends a new query expression.
     *
     * @phpstan-return static<T>
     */
    public function sortBy(string $field, string $dir = SortExpression::DIR_ASC): static;

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
