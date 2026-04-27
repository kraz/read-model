<?php

/**
 * DISCLAIMER: The bigger part or all of the source code in this file is taken from the "Doctrine Collections"
 * [doctrine/collections](https://github.com/doctrine/collections). The file may have modifications from the
 * original source code in order to comply with the current requirements of this library. The author of these
 * changes does not pretend or claim any ownership or authorship of the original source code.
 */

declare(strict_types=1);

namespace Kraz\ReadModel\Collections;

use Kraz\ReadModel\Collections\Expr\Comparison;
use Kraz\ReadModel\Collections\Expr\CompositeExpression;
use Kraz\ReadModel\Collections\Expr\Expression;
use Kraz\ReadModel\Collections\Expr\Value;

/**
 * Builder for Expressions in the {@link Selectable} interface.
 *
 * Important Notice for interoperable code: You have to use scalar
 * values only for comparisons, otherwise the behavior of the comparison
 * may be different between implementations (Array vs ORM vs ODM).
 */
final class ExpressionBuilder
{
    public function andX(Expression ...$expressions): CompositeExpression
    {
        return new CompositeExpression(CompositeExpression::TYPE_AND, $expressions);
    }

    public function orX(Expression ...$expressions): CompositeExpression
    {
        return new CompositeExpression(CompositeExpression::TYPE_OR, $expressions);
    }

    public function not(Expression $expression): CompositeExpression
    {
        return new CompositeExpression(CompositeExpression::TYPE_NOT, [$expression]);
    }

    public function eq(string $field, mixed $value, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::EQ, new Value($value), $caseSensitive);
    }

    public function gt(string $field, mixed $value): Comparison
    {
        return new Comparison($field, Comparison::GT, new Value($value));
    }

    public function lt(string $field, mixed $value): Comparison
    {
        return new Comparison($field, Comparison::LT, new Value($value));
    }

    public function gte(string $field, mixed $value): Comparison
    {
        return new Comparison($field, Comparison::GTE, new Value($value));
    }

    public function lte(string $field, mixed $value): Comparison
    {
        return new Comparison($field, Comparison::LTE, new Value($value));
    }

    public function neq(string $field, mixed $value, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::NEQ, new Value($value), $caseSensitive);
    }

    public function isNull(string $field): Comparison
    {
        return new Comparison($field, Comparison::EQ, new Value(null));
    }

    public function isNotNull(string $field): Comparison
    {
        return new Comparison($field, Comparison::NEQ, new Value(null));
    }

    /** @param mixed[] $values */
    public function in(string $field, array $values, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::IN, new Value($values), $caseSensitive);
    }

    /** @param mixed[] $values */
    public function notIn(string $field, array $values, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::NIN, new Value($values), $caseSensitive);
    }

    public function contains(string $field, mixed $value, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::CONTAINS, new Value($value), $caseSensitive);
    }

    public function memberOf(string $field, mixed $value): Comparison
    {
        return new Comparison($field, Comparison::MEMBER_OF, new Value($value));
    }

    public function startsWith(string $field, mixed $value, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::STARTS_WITH, new Value($value), $caseSensitive);
    }

    public function endsWith(string $field, mixed $value, bool $caseSensitive = true): Comparison
    {
        return new Comparison($field, Comparison::ENDS_WITH, new Value($value), $caseSensitive);
    }
}
