<?php

/**
 * DISCLAIMER: The bigger part or all of the source code in this file is taken from the "Doctrine Collections"
 * [doctrine/collections](https://github.com/doctrine/collections). The file may have modifications from the
 * original source code in order to comply with the current requirements of this library. The author of these
 * changes does not pretend or claim any ownership or authorship of the original source code.
 */

declare(strict_types=1);

namespace Kraz\ReadModel\Collections\Expr;

/**
 * An Expression visitor walks a graph of expressions and turns them into a
 * query for the underlying implementation.
 */
abstract class ExpressionVisitor
{
    /**
     * Converts a comparison expression into the target query language output.
     */
    abstract public function walkComparison(Comparison $comparison): mixed;

    /**
     * Converts a value expression into the target query language part.
     */
    abstract public function walkValue(Value $value): mixed;

    /**
     * Converts a composite expression into the target query language output.
     */
    abstract public function walkCompositeExpression(CompositeExpression $expr): mixed;

    /**
     * Dispatches walking an expression to the appropriate handler.
     */
    public function dispatch(Expression $expr): mixed
    {
        return $expr->visit($this);
    }
}
