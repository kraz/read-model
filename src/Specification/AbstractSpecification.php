<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Specification;

use Kraz\ReadModel\Query\QueryExpression;

/**
 * @phpstan-template T of object|array<string, mixed>
 * @phpstan-implements SpecificationInterface<T>
 */
abstract class AbstractSpecification implements SpecificationInterface
{
    private bool $inverted = false;

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function and(SpecificationInterface $specification): SpecificationInterface
    {
        /** @phpstan-var CompositeAndSpecification<T> $spec */
        $spec = $this->createCompositeAndSpecification();

        return $spec->with($this, $specification);
    }

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function andNot(SpecificationInterface $specification): SpecificationInterface
    {
        /** @phpstan-var SpecificationInterface<T> $spec */
        $spec = $this->createCompositeAndSpecification()
            ->with($this)
            ->and($this->createCompositeAndSpecification()->with($specification)->invert());

        return $spec;
    }

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function or(SpecificationInterface $specification): SpecificationInterface
    {
        /** @phpstan-var CompositeOrSpecification<T> $spec */
        $spec = $this->createCompositeOrSpecification();

        return $spec->with($this, $specification);
    }

    /**
     * @phpstan-param SpecificationInterface<T> $specification
     *
     * @return SpecificationInterface<T>
     */
    public function orNot(SpecificationInterface $specification): SpecificationInterface
    {
        /** @phpstan-var SpecificationInterface<T> $spec */
        $spec = $this->createCompositeOrSpecification()
            ->with($this)
            ->or($this->createCompositeOrSpecification()->with($specification)->invert());

        return $spec;
    }

    /** @return SpecificationInterface<T> */
    public function invert(): SpecificationInterface
    {
        $cloned           = clone $this;
        $cloned->inverted = ! $this->inverted;

        return $cloned;
    }

    public function inverted(): bool
    {
        return $this->inverted;
    }

    final public function getQueryExpression(): QueryExpression|null
    {
        $query = $this->buildQueryExpression();

        return $this->inverted() ? $query?->invert() : $query;
    }

    protected function buildQueryExpression(): QueryExpression|null
    {
        return null;
    }

    /** @return CompositeAndSpecification<T> */
    protected function createCompositeAndSpecification(): CompositeAndSpecification
    {
        /** @phpstan-var CompositeAndSpecification<T> $spec */
        $spec = new CompositeAndSpecification();

        return $spec;
    }

    /** @return CompositeOrSpecification<T> */
    protected function createCompositeOrSpecification(): CompositeOrSpecification
    {
        /** @phpstan-var CompositeOrSpecification<T> $spec */
        $spec = new CompositeOrSpecification();

        return $spec;
    }
}
