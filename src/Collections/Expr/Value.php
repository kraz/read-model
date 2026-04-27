<?php

/**
 * DISCLAIMER: The bigger part or all of the source code in this file is taken from the "Doctrine Collections"
 * [doctrine/collections](https://github.com/doctrine/collections). The file may have modifications from the
 * original source code in order to comply with the current requirements of this library. The author of these
 * changes does not pretend or claim any ownership or authorship of the original source code.
 */

declare(strict_types=1);

namespace Kraz\ReadModel\Collections\Expr;

use Override;

final readonly class Value implements Expression
{
    public function __construct(private mixed $value)
    {
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    #[Override]
    public function visit(ExpressionVisitor $visitor): mixed
    {
        return $visitor->walkValue($this);
    }
}
