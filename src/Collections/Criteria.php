<?php

/**
 * DISCLAIMER: The bigger part or all of the source code in this file is taken from the "Doctrine Collections"
 * [doctrine/collections](https://github.com/doctrine/collections). The file may have modifications from the
 * original source code in order to comply with the current requirements of this library. The author of these
 * changes does not pretend or claim any ownership or authorship of the original source code.
 */

declare(strict_types=1);

namespace Kraz\ReadModel\Collections;

use Kraz\ReadModel\Collections\Expr\CompositeExpression;
use Kraz\ReadModel\Collections\Expr\Expression;

/**
 * Criteria for filtering Selectable collections.
 *
 * @phpstan-consistent-constructor
 */
final class Criteria
{
    private static ExpressionBuilder|null $expressionBuilder = null;

    /** @phpstan-var array<string, Order> */
    private array $orderings = [];

    private int|null $firstResult = null;
    private int|null $maxResults  = null;

    /**
     * Creates an instance of the class.
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Returns the expression builder.
     */
    public static function expr(): ExpressionBuilder
    {
        if (self::$expressionBuilder === null) {
            self::$expressionBuilder = new ExpressionBuilder();
        }

        return self::$expressionBuilder;
    }

    /**
     * Construct a new Criteria.
     *
     * @phpstan-param array<string, Order>|null $orderings
     */
    public function __construct(
        private Expression|null $expression = null,
        array|null $orderings = null,
        int $firstResult = 0,
        int|null $maxResults = null,
    ) {
        $this->setFirstResult($firstResult);
        $this->setMaxResults($maxResults);

        if ($orderings === null) {
            return;
        }

        $this->orderBy($orderings);
    }

    /**
     * Sets the where expression to evaluate when this Criteria is searched for.
     *
     * @return $this
     */
    public function where(Expression $expression): static
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Appends the where expression to evaluate when this Criteria is searched for
     * using an AND with previous expression.
     *
     * @return $this
     */
    public function andWhere(Expression $expression): static
    {
        if ($this->expression === null) {
            return $this->where($expression);
        }

        $this->expression = new CompositeExpression(
            CompositeExpression::TYPE_AND,
            [$this->expression, $expression],
        );

        return $this;
    }

    /**
     * Appends the where expression to evaluate when this Criteria is searched for
     * using an OR with previous expression.
     *
     * @return $this
     */
    public function orWhere(Expression $expression): static
    {
        if ($this->expression === null) {
            return $this->where($expression);
        }

        $this->expression = new CompositeExpression(
            CompositeExpression::TYPE_OR,
            [$this->expression, $expression],
        );

        return $this;
    }

    /**
     * Gets the expression attached to this Criteria.
     */
    public function getWhereExpression(): Expression|null
    {
        return $this->expression;
    }

    /**
     * Gets the current orderings of this Criteria.
     *
     * @return array<string, Order>
     */
    public function orderings(): array
    {
        return $this->orderings;
    }

    /**
     * Sets the ordering of the result of this Criteria.
     *
     * Keys are field and values are the order, being a valid Order enum case.
     *
     * @see Order::Ascending
     * @see Order::Descending
     *
     * @phpstan-param array<string, Order> $orderings
     *
     * @return $this
     */
    public function orderBy(array $orderings): static
    {
        $this->orderings = $orderings;

        return $this;
    }

    /**
     * Gets the current first result option of this Criteria.
     */
    public function getFirstResult(): int|null
    {
        return $this->firstResult;
    }

    /**
     * Set the number of first result that this Criteria should return.
     *
     * @param int $firstResult The value to set.
     *
     * @return $this
     */
    public function setFirstResult(int $firstResult): static
    {
        $this->firstResult = $firstResult;

        return $this;
    }

    /**
     * Gets maxResults.
     */
    public function getMaxResults(): int|null
    {
        return $this->maxResults;
    }

    /**
     * Sets maxResults.
     *
     * @param int|null $maxResults The value to set.
     *
     * @return $this
     */
    public function setMaxResults(int|null $maxResults): static
    {
        $this->maxResults = $maxResults;

        return $this;
    }
}
