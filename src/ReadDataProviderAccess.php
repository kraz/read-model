<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use InvalidArgumentException;
use Kraz\ReadModel\Exception\MissingValuesException;
use Override;
use Traversable;

use function array_column;
use function array_diff;
use function array_values;
use function count;
use function max;
use function sprintf;

/**
 * Provides basic behavior for accessing data in ReadDataProviderInterface.
 *
 * @phpstan-template-covariant T of object|array<string, mixed>
 */
trait ReadDataProviderAccess
{
    #[Override]
    public function specificationsIterator(array $specifications, int|null $limit = null, int $offset = 0, int|null $batchSize = null): Traversable
    {
        if ($limit !== null && $limit <= 0) {
            throw new InvalidArgumentException(sprintf('The limit must be positive number, %d was given!', $limit));
        }

        if ($offset < 0) {
            throw new InvalidArgumentException(sprintf('The offset can not be negative number, %d was given!', $offset));
        }

        if ($limit !== null && $batchSize !== null && $batchSize < $limit + $offset) {
            throw new InvalidArgumentException(sprintf('The batch size can not be lower than %d', $limit + $offset));
        }

        if ($batchSize !== null && $batchSize < 1) {
            throw new InvalidArgumentException('The batch size can not be lower than 1');
        }

        $base = $this;
        foreach ($specifications as $specification) {
            $qe = $specification->getQueryExpression();
            if ($qe === null || $qe->isEmpty()) {
                continue;
            }

            $base = $base->withQueryExpression($qe, true);
        }

        $batchSize ??= max(1, ($limit ?? 0) + $offset);
        $collected   = 0;
        $skipped     = 0;
        $batchOffset = 0;

        while (true) {
            /** @phpstan-var ReadDataProviderInterface<T> $batchProvider */
            $batchProvider = $base->withLimit($batchSize, $batchOffset);
            $count         = 0;

            foreach ($batchProvider->getIterator() as $item) {
                $count++;
                foreach ($specifications as $specification) {
                    /** @phpstan-var T $item */
                    if (! $specification->isSatisfiedBy($item)) {
                        continue 2;
                    }
                }

                if ($skipped < $offset) {
                    $skipped++;

                    continue;
                }

                yield $item;

                $collected++;
                if ($limit !== null && $collected >= $limit) {
                    break 2;
                }
            }

            if ($count === 0) {
                break;
            }

            $batchOffset += $batchSize;
        }
    }

    /**
     * @phpstan-param T[] $data
     * @phpstan-param list<int|string> $values
     */
    private function assertAllValuesFound(array $data, array $values, string $indexField): void
    {
        $found         = array_column($data, $indexField);
        $missingValues = array_values(array_diff($values, $found));
        if (count($missingValues) > 0) {
            throw new MissingValuesException($missingValues, $data);
        }
    }
}
