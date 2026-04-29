<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Specification;

use Kraz\ReadModel\Query\QueryExpression;

/** @phpstan-template T of object|array<string, mixed> */
interface SpecificationInterface
{
    /** @phpstan-param T $item */
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
