<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use Kraz\ReadModel\Specification\SpecificationInterface;

use function count;
use function max;

/**
 * Fetches items from a provider in batches using limit/offset, filters them through specifications
 * in memory, and stops as soon as the requested number of matching items is collected.
 *
 * @phpstan-template T of object|array<string, mixed>
 */
class EagerSpecificationFetcher
{
    /**
     * @phpstan-param ReadDataProviderInterface<T>                        $provider
     * @phpstan-param array<SpecificationInterface<contravariant T>>      $specifications
     * @phpstan-param int<0, max>                                         $limit
     * @phpstan-param int<0, max>                                         $offset
     *
     * @phpstan-return list<T>
     */
    public function fetch(
        ReadDataProviderInterface $provider,
        array $specifications,
        int $limit,
        int $offset = 0,
    ): array {
        $batchSize   = max(1, $limit + $offset);
        $collected   = [];
        $skipped     = 0;
        $batchOffset = 0;

        while (true) {
            /** @phpstan-var ReadDataProviderInterface<T> $batchProvider */
            $batchProvider = $provider->withLimit($batchSize, $batchOffset);
            /** @phpstan-var list<T> $batch */
            $batch = $batchProvider->data();

            if (count($batch) === 0) {
                break;
            }

            foreach ($batch as $item) {
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

                $collected[] = $item;

                if (count($collected) >= $limit) {
                    return $collected;
                }
            }

            $batchOffset += $batchSize;
        }

        return $collected;
    }
}
