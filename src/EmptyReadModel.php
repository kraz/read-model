<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

/** @implements ReadDataProviderInterface<array<never, never>> */
class EmptyReadModel implements ReadDataProviderInterface
{
    /** @use DataSourceReadDataProvider<array<never, never>> */
    use DataSourceReadDataProvider;

    protected function createDataSource(): ReadDataProviderInterface
    {
        return new DataSource([]);
    }
}
