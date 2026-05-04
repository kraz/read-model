<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Query\Fixtures;

use Kraz\ReadModel\DataSourceBuilder;
use Kraz\ReadModel\DataSourceReadDataProvider;
use Kraz\ReadModel\ReadDataProviderInterface;

/** @implements ReadDataProviderInterface<PersonFixture> */
class PersonReadModel implements ReadDataProviderInterface
{
    /** @use DataSourceReadDataProvider<PersonFixture> */
    use DataSourceReadDataProvider;

    public const string FIELD_ID         = 'id';
    public const string FIELD_NAME       = 'name';
    public const string FIELD_AGE        = 'age';
    public const string FIELD_FIRST_NAME = 'first_name';
    public const string FIELD_LAST_NAME  = 'last_name';
    public const string FIELD_TAG        = 'tag';

    protected function createDataSource(): ReadDataProviderInterface
    {
        return new DataSourceBuilder()
            ->withPagination(1, 3)
            ->create([
                new PersonFixture(id: 1, name: 'Alice', age: 30),
                new PersonFixture(id: 2, name: 'Bob', age: 25),
                new PersonFixture(id: 3, name: 'Carol', age: 40),
                new PersonFixture(id: 4, name: 'Dan', age: 35),
                new PersonFixture(id: 5, name: 'Eve', age: 22),
            ]);
    }
}
