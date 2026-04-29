<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Specification;

/**
 * @phpstan-template T of object|array<string, mixed>
 * @phpstan-extends AbstractSpecification<T>
 */
abstract class CompositeSpecification extends AbstractSpecification
{
    /** @var SpecificationInterface<T>[] */
    private array $specifications = [];

    /** @return SpecificationInterface<T>[] */
    protected function specifications(): array
    {
        return $this->specifications;
    }

    /**
     * @phpstan-param SpecificationInterface<T> ...$specifications
     *
     * @return SpecificationInterface<T>
     */
    public function with(SpecificationInterface ...$specifications): SpecificationInterface
    {
        $this->specifications = $specifications;

        return $this;
    }
}
