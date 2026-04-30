<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Specification;

use Kraz\ReadModel\Query\QueryExpression;

/** @phpstan-template T of object|array<string, mixed> */
interface SpecificationInterface
{
    /**
     * Returns true if the given item satisfies this specification.
     *
     * The type and state of `$item` depend on the read model implementation and the
     * underlying data source. Items may be raw, partially normalized, or fully normalized
     * depending on the query type and the pipeline stage at which this method is called.
     * Refer to the documentation of the specific data source implementation for details.
     *
     * @phpstan-param T $item
     */
    public function isSatisfiedBy(object|array $item): bool;

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function and(SpecificationInterface $specification): SpecificationInterface;

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function andNot(SpecificationInterface $specification): SpecificationInterface;

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function or(SpecificationInterface $specification): SpecificationInterface;

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function orNot(SpecificationInterface $specification): SpecificationInterface;

    /** @return SpecificationInterface<T> */
    public function invert(): SpecificationInterface;

    public function inverted(): bool;

    public function getQueryExpression(): QueryExpression|null;
}
