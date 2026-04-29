<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Query;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

use function array_filter;
use function array_find;
use function array_key_exists;
use function array_map;
use function array_search;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

/**
 * @phpstan-type SortItem = array{
 *     field: string,
 *     dir: string,
 * }
 * @phpstan-type SortComposite = SortItem|SortItem[]
 */
final class SortExpression implements JsonSerializable, Stringable
{
    public const string DIR_ASC  = 'asc';
    public const string DIR_DESC = 'desc';

    /** @phpstan-param SortComposite $sort */
    private function __construct(
        private array $sort = [],
    ) {
    }

    private function by(string $field, string $dir): self
    {
        $cloned = clone $this;
        $cloned->reset($field);

        if (($cloned->sort['field'] ?? null) !== null) {
            /** @phpstan-var SortItem $item */
            $item         = $cloned->sort;
            $cloned->sort = [$item];
        }

        if (count($cloned->sort) > 0) {
            $cloned->sort[] = ['field' => $field, 'dir' => $dir];
        } else {
            $cloned->sort = ['field' => $field, 'dir' => $dir];
        }

        return $cloned;
    }

    public function asc(string ...$field): self
    {
        $result = clone $this;
        foreach ($field as $item) {
            $result = $result->reset($item);
            $result = $result->by($item, self::DIR_ASC);
        }

        return $result;
    }

    public function desc(string ...$field): self
    {
        $result = clone $this;
        foreach ($field as $item) {
            $result = $result->reset($item);
            $result = $result->by($item, self::DIR_DESC);
        }

        return $result;
    }

    public function reset(string|null $field = null): self
    {
        $clone = clone $this;
        if ($field !== null) {
            $items = $clone->items();
            foreach ($items as $index => ['field' => $itemField]) {
                if ($field === $itemField) {
                    unset($items[$index]);
                    $clone->sort = array_values($items);
                    break;
                }
            }
        } else {
            $clone->sort = [];
        }

        return $clone;
    }

    public function invert(): self
    {
        return self::create(array_map(static function (array $item) {
            $item['dir'] = match ($item['dir']) {
                self::DIR_ASC => self::DIR_DESC,
                self::DIR_DESC => self::DIR_ASC,
                default => $item['dir'],
            };

            return $item;
        }, $this->items()));
    }

    /** @phpstan-return SortComposite */
    public function sort(): array
    {
        return $this->sort;
    }

    public function dir(string $field): string|null
    {
        $items = $this->items();
        /** @phpstan-var SortItem|array<never, never> $item */
        $item = array_find($items, static fn (array $item) => $field === $item['field']) ?? [];

        return $item['dir'] ?? null;
    }

    public function num(string $field): int|null
    {
        $items = $this->items();
        /** @phpstan-var SortItem|array<never, never> $item */
        $item  = array_find($items, static fn (array $item) => $field === $item['field']) ?? [];
        $index = array_search($item, $items, true);

        return $index === false ? null : ((int) $index) + 1;
    }

    public function count(): int
    {
        return count($this->items());
    }

    public function isSortEmpty(): bool
    {
        return $this->count() === 0;
    }

    /** @phpstan-return SortItem[] */
    public function items(): array
    {
        /** @phpstan-var SortItem[] $items */
        $items = array_values($this->sort) === $this->sort ? $this->sort : [$this->sort];

        return $items;
    }

    /** @return SortItem[] */
    public function toArray(): array
    {
        return array_values(array_filter($this->items()));
    }

    public function __toString(): string
    {
        if ($this->isSortEmpty()) {
            return '';
        }

        $json = json_encode($this->sort);

        return $json === false ? '' : $json;
    }

    /** @phpstan-return array{sort: SortItem[]} */
    public function __serialize(): array
    {
        return ['sort' => $this->toArray()];
    }

    /** @phpstan-param array{sort?: SortComposite|null} $data */
    public function __unserialize(array $data): void
    {
        $this->sort = $data['sort'] ?? [];
    }

    public function __clone()
    {
        $this->sort = $this->items();
    }

    /** @phpstan-return SortItem[]|null */
    public function jsonSerialize(): array|null
    {
        $items = array_filter($this->toArray());

        return count($items) > 0 ? $items : null;
    }

    /**
     * @phpstan-param list<array<string, mixed>>|array<string, mixed> $sort
     * @phpstan-param array<string, string> $mapping
     *
     * @phpstan-return ($sort is list<array<string, mixed>> ? list<array<string, mixed>> : array<string, mixed>)
     */
    public static function applyFieldMapping(array $sort, array $mapping): array
    {
        $isList = array_values($sort) === $sort;
        /** @phpstan-var list<array<string, mixed>> $result */
        $result = array_map(static function (array $item) use ($mapping) {
            $field = $item['field'] ?? null;
            if ($field !== null && array_key_exists($field, $mapping)) {
                $item['field'] = $mapping[$field];
            }

            return $item;
        }, $isList ? $sort : [$sort]);

        return $isList ? $result : $result[0];
    }

    /** @phpstan-param SortComposite|array<never, never>|string $sort */
    public static function create(string|array $sort = []): self
    {
        if (is_string($sort)) {
            /** @phpstan-var SortComposite|array<never, never>|false|null $data */
            $data = json_decode($sort, true);
            if ($data !== null && ! is_array($data)) {
                throw new InvalidArgumentException('Expected null or an array.');
            }

            $sort = $data;
        }

        return new self(is_array($sort) ? $sort : []);
    }
}
