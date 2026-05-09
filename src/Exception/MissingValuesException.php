<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Exception;

use LogicException;
use SensitiveParameter;
use Throwable;

use function implode;
use function sprintf;

class MissingValuesException extends LogicException
{
    /**
     * @param list<int|string>                                   $values
     * @param array<array-key, object|array<string, mixed>>|null $items
     */
    public function __construct(
        private readonly array $values,
        #[SensitiveParameter]
        private readonly array|null $items = null,
        int $code = 0,
        Throwable|null $previous = null,
    ) {
        parent::__construct(sprintf('Missing values: %s', implode(', ', $this->values)), $code, $previous);
    }

    /** @phpstan-return list<int|string> */
    public function getValues(): array
    {
        return $this->values;
    }

    /** @phpstan-return array<array-key, object|array<string, mixed>>|null */
    public function getItems(): array|null
    {
        return $this->items;
    }
}
