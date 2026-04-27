<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tools;

use Closure;
use IteratorAggregate;
use Traversable;

use function call_user_func;

/**
 * @template TKey
 * @template-covariant TValue
 * @template-implements IteratorAggregate<TKey, TValue>
 */
class TraversableTransformer implements IteratorAggregate
{
    /** @phpstan-param Traversable<TKey, mixed> $innerTraversable */
    public function __construct(
        private Traversable $innerTraversable,
        private Closure $callback,
    ) {
    }

    /** @return Traversable<TKey, TValue> */
    public function getIterator(): Traversable
    {
        /** @phpstan-var TKey  $key */
        foreach ($this->innerTraversable as $key => $value) {
            /** @phpstan-var TValue $newValue */
            $newValue = call_user_func($this->callback, $value);

            yield $key => $newValue;
        }
    }
}
