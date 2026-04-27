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

/**
 * Comparison of a field with a value by the given operator.
 */
final readonly class Comparison implements Expression
{
    public const string EQ          = '=';
    public const string NEQ         = '<>';
    public const string LT          = '<';
    public const string LTE         = '<=';
    public const string GT          = '>';
    public const string GTE         = '>=';
    public const string IS          = '='; // no difference with EQ
    public const string IN          = 'IN';
    public const string NIN         = 'NIN';
    public const string CONTAINS    = 'CONTAINS';
    public const string MEMBER_OF   = 'MEMBER_OF';
    public const string STARTS_WITH = 'STARTS_WITH';
    public const string ENDS_WITH   = 'ENDS_WITH';

    private Value $value;

    public function __construct(private string $field, private string $op, mixed $value, private bool $caseSensitive = true)
    {
        if (! ($value instanceof Value)) {
            $value = new Value($value);
        }

        $this->value = $value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): Value
    {
        return $this->value;
    }

    public function getOperator(): string
    {
        return $this->op;
    }

    public function isCaseSensitive(): bool
    {
        return $this->caseSensitive;
    }

    #[Override]
    public function visit(ExpressionVisitor $visitor): mixed
    {
        return $visitor->walkComparison($this);
    }
}
