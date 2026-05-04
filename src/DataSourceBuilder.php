<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use InvalidArgumentException;
use IteratorAggregate;
use Kraz\ReadModel\Pagination\PaginatorInterface;
use LogicException;
use Override;

/**
 * @phpstan-template-covariant T of object|array<string, mixed>
 * @implements ReadDataProviderCompositionInterface<T>
 * @implements ReadDataProviderBuilderInterface<T>
 */
class DataSourceBuilder implements ReadDataProviderCompositionInterface, ReadDataProviderBuilderInterface
{
    /** @use ReadDataProviderBuilder<T> */
    use ReadDataProviderBuilder;

    private mixed $data = null;

    /**
     * @phpstan-param ReadDataProviderInterface<J>|PaginatorInterface<J>|IteratorAggregate<array-key, J>|iterable<J>|null $data
     *
     * @phpstan-return static<J>
     *
     * @phpstan-template J of object|array<string, mixed>
     */
    public function withData(ReadDataProviderInterface|PaginatorInterface|IteratorAggregate|iterable|null $data): static
    {
        /** @phpstan-var static<J> $clone */
        $clone       = clone $this;
        $clone->data = $data;

        return $clone;
    }

    /**
     * @phpstan-param ReadDataProviderInterface<J>|PaginatorInterface<J>|IteratorAggregate<array-key, J>|iterable<J>|null $data
     *
     * @return ($data is null ? DataSource<object|array<string, mixed>> : DataSource<J>)
     *
     * @phpstan-template J of object|array<string, mixed>
     */
    public function create(ReadDataProviderInterface|PaginatorInterface|IteratorAggregate|iterable|null $data = null): DataSource
    {
        $data ??= $this->data;
        if ($data === null) {
            throw new InvalidArgumentException('The data source has no data assigned! Expected a value other than null.');
        }

        /** @phpstan-var DataSource<J> $dataSource */
        $dataSource = new DataSource($data);

        return $this->apply($dataSource);
    }

    #[Override]
    public function handleRequest(object $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        throw new LogicException('Unsupported operation. The data source builder can not handle requests.');
    }
}
