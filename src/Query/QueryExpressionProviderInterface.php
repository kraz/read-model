<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use Kraz\ReadModel\ReadModelDescriptor;

interface QueryExpressionProviderInterface
{
    public const int INCLUDE_DATA_ALL    = 65535;
    public const int INCLUDE_DATA_FILTER = 1;
    public const int INCLUDE_DATA_SORT   = 2;
    public const int INCLUDE_DATA_VALUES = 4;

    /**
     * @phpstan-param ExpectedType $data
     * @phpstan-param array<string, mixed> $options
     *
     * @phpstan-return ExpectedType
     *
     * @phpstan-template ExpectedType of object
     */
    public function apply(mixed $data, QueryExpression $queryExpression, ReadModelDescriptor|null $descriptor = null, array $options = [], int $includeData = self::INCLUDE_DATA_ALL): mixed;
}
