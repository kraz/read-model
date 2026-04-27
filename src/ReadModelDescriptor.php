<?php

declare(strict_types=1);

namespace Kraz\ReadModel;

use function array_combine;
use function array_replace;
use function array_values;

final readonly class ReadModelDescriptor
{
    public function __construct(
        /** @phpstan-var array<int, string> */
        public array $properties,
        /** @phpstan-var array<string, string> */
        public array $operators,
        /** @phpstan-var array<string, bool> */
        public array $ignoreCase,
        /** @phpstan-var array<string, string> */
        public array $fieldMap,
    ) {
    }

    public function merge(ReadModelDescriptor ...$descriptor): self
    {
        $properties = array_combine($this->properties, $this->properties);
        $operators  = $this->operators;
        $ignoreCase = $this->ignoreCase;
        $fieldMap   = $this->fieldMap;

        foreach ($descriptor as $item) {
            $properties = array_replace($properties, array_combine($item->properties, $item->properties));
            $operators  = array_replace($operators, $item->operators);
            $ignoreCase = array_replace($ignoreCase, $item->ignoreCase);
            $fieldMap   = array_replace($fieldMap, $item->fieldMap);
        }

        return new ReadModelDescriptor(array_values($properties), $operators, $ignoreCase, $fieldMap);
    }
}
