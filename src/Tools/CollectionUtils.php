<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tools;

use Kraz\ReadModel\Collections\ArrayCollection;

use function array_search;
use function is_array;
use function is_object;
use function method_exists;
use function usort;

class CollectionUtils
{
    /**
     * Sort $data by maintaining the same order as the elements in $index. The $field is used to get the value
     * from $data and compare it with the values inside $index.
     *
     * @phpstan-param ArrayCollection<array-key, mixed>|mixed[] $data
     * @phpstan-param list<int|string> $index
     *
     * @phpstan-return mixed[]
     */
    public static function sortByIndex(ArrayCollection|array $data, string $field, array $index): array
    {
        if ($data instanceof ArrayCollection) {
            $data = $data->toArray();
        }

        if (empty($data)) {
            return $data;
        }

        usort($data, static function (mixed $a, mixed $b) use ($field, $index): int {
            if (is_array($a) && is_array($b)) {
                return array_search($a[$field], $index, true) <=> array_search($b[$field], $index, true);
            }

            if (is_object($a) && is_object($b)) {
                $getter = 'get' . $field;
                if (method_exists($a, $getter)) {
                    return array_search($a->{$getter}(), $index, true) <=> array_search($b->{$getter}(), $index, true);
                }

                return array_search($a->{$field}, $index, true) <=> array_search($b->{$field}, $index, true);
            }

            return 0;
        });

        return $data;
    }
}
