<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use Kraz\ReadModel\Collections\Selectable;
use Kraz\ReadModel\ReadModelDescriptor;
use Kraz\ReadModel\ReadModelDescriptorFactoryInterface;
use Override;

use function count;
use function is_string;

/** @phpstan-import-type QueryExpressionHelperOptions from QueryExpressionHelper */
class QueryExpressionProvider implements QueryExpressionProviderInterface
{
    /** @phpstan-var array<string, string> */
    private array $fieldMapping = [];

    /** @phpstan-var string|string[] */
    private array|string $rootAlias = [];

    /** @phpstan-var string|string[] */
    private array|string $rootIdentifier = [];

    public function __construct(
        private ReadModelDescriptorFactoryInterface $descriptorFactory,
    ) {
    }

    #[Override]
    public function setFieldMapping(array $fieldMapping): self
    {
        $this->fieldMapping = $fieldMapping;

        return $this;
    }

    #[Override]
    public function getFieldMapping(): array
    {
        return $this->fieldMapping;
    }

    #[Override]
    public function setRootAlias(array|string $rootAlias): self
    {
        $this->rootAlias = $rootAlias;

        return $this;
    }

    #[Override]
    public function getRootAlias(): array|string
    {
        return $this->rootAlias;
    }

    #[Override]
    public function setRootIdentifier(array|string $rootIdentifier): self
    {
        $this->rootIdentifier = $rootIdentifier;

        return $this;
    }

    #[Override]
    public function getRootIdentifier(): array|string
    {
        return $this->rootIdentifier;
    }

    /**
     * @phpstan-param Selectable<array-key, T> $data
     * @phpstan-param QueryExpressionHelperOptions $options
     *
     * @phpstan-return Selectable<array-key, T>
     *
     * @phpstan-template T
     */
    #[Override]
    public function apply(mixed $data, QueryExpression $queryExpression, ReadModelDescriptor|null $descriptor = null, array $options = [], int $includeData = self::INCLUDE_DATA_ALL): Selectable
    {
        /** @phpstan-var ReadModelDescriptor|class-string|null $optDescriptor */
        $optDescriptor = $options['read_model_descriptor'] ?? null;
        if ($descriptor === null && is_string($optDescriptor)) {
            $optDescriptor = $this->descriptorFactory->createReadModelDescriptorFrom($optDescriptor);
        }

        if ($descriptor === null && $optDescriptor instanceof ReadModelDescriptor) {
            $descriptor = $optDescriptor;
        }

        if (count($this->fieldMapping) > 0) {
            $options['field_map'] = $this->fieldMapping;
        }

        $rootAlias = is_string($this->rootAlias) ? [$this->rootAlias] : $this->rootAlias;
        if (count($rootAlias) > 0) {
            $options['root_alias'] = $rootAlias;
        }

        $rootIdentifier = is_string($this->rootIdentifier) ? [$this->rootIdentifier] : $this->rootIdentifier;
        if (count($rootIdentifier) > 0) {
            $options['root_identifier'] = $rootIdentifier;
        }

        return QueryExpressionHelper::create($data, $descriptor, $options)->apply($queryExpression, $includeData);
    }
}
