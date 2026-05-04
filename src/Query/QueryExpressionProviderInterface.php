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

    /** @phpstan-param array<string, string> $fieldMapping */
    public function setFieldMapping(array $fieldMapping): self;

    /** @phpstan-return array<string, string> */
    public function getFieldMapping(): array;

    /** @phpstan-param string[]|string $rootAlias */
    public function setRootAlias(array|string $rootAlias): self;

    /** @phpstan-return string[]|string */
    public function getRootAlias(): array|string;

    /** @phpstan-param string[]|string $rootIdentifier */
    public function setRootIdentifier(array|string $rootIdentifier): self;

    /** @phpstan-return string[]|string */
    public function getRootIdentifier(): array|string;

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
