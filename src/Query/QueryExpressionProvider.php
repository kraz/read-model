<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use Kraz\ReadModel\Collections\Selectable;
use Kraz\ReadModel\ReadModelDescriptor;
use Kraz\ReadModel\ReadModelDescriptorFactoryInterface;
use Override;

use function is_string;

/** @phpstan-import-type QueryExpressionHelperOptions from QueryExpressionHelper */
class QueryExpressionProvider implements QueryExpressionProviderInterface
{
    public function __construct(
        private ReadModelDescriptorFactoryInterface $descriptorFactory,
    ) {
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

        return QueryExpressionHelper::create($data, $descriptor, $options)->apply($queryExpression, $includeData);
    }
}
