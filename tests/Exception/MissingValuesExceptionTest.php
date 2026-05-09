<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Exception;

use Kraz\ReadModel\Exception\MissingValuesException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(MissingValuesException::class)]
final class MissingValuesExceptionTest extends TestCase
{
    public function testMessageIncludesIntegerValues(): void
    {
        $e = new MissingValuesException([1, 2, 3]);

        self::assertSame('Missing values: 1, 2, 3', $e->getMessage());
    }

    public function testMessageIncludesStringValues(): void
    {
        $e = new MissingValuesException(['foo', 'bar']);

        self::assertSame('Missing values: foo, bar', $e->getMessage());
    }

    public function testMessageWithSingleValue(): void
    {
        $e = new MissingValuesException([42]);

        self::assertSame('Missing values: 42', $e->getMessage());
    }

    public function testGetValuesReturnsProvidedList(): void
    {
        $e = new MissingValuesException([10, 20, 30]);

        self::assertSame([10, 20, 30], $e->getValues());
    }

    public function testGetItemsReturnsNullByDefault(): void
    {
        $e = new MissingValuesException([1]);

        self::assertNull($e->getItems());
    }

    public function testGetItemsReturnsProvidedItems(): void
    {
        $item = new stdClass();
        $e    = new MissingValuesException([1], [$item]);

        self::assertSame([$item], $e->getItems());
    }

    public function testGetItemsReturnsAssociativeArrayItems(): void
    {
        $items = [['id' => 1, 'name' => 'Alice']];
        $e     = new MissingValuesException([99], $items);

        self::assertSame($items, $e->getItems());
    }

    public function testIsLogicException(): void
    {
        $e = new MissingValuesException([1]);

        self::assertInstanceOf(LogicException::class, $e);
    }

    public function testDefaultCodeIsZero(): void
    {
        $e = new MissingValuesException([1]);

        self::assertSame(0, $e->getCode());
    }

    public function testCustomCodeIsPreserved(): void
    {
        $e = new MissingValuesException([1], null, 42);

        self::assertSame(42, $e->getCode());
    }

    public function testPreviousExceptionIsChained(): void
    {
        $previous = new RuntimeException('root cause');
        $e        = new MissingValuesException([1], null, 0, $previous);

        self::assertSame($previous, $e->getPrevious());
    }
}
