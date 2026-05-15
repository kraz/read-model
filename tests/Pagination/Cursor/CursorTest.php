<?php

declare(strict_types=1);

namespace Kraz\ReadModel\Tests\Pagination\Cursor;

use Kraz\ReadModel\Pagination\Cursor\Cursor;
use Kraz\ReadModel\Pagination\Cursor\Direction;
use Kraz\ReadModel\Query\SortExpression;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursor::class)]
#[CoversClass(Direction::class)]
final class CursorTest extends TestCase
{
    public function testGettersExposeConstructorArguments(): void
    {
        $cursor = new Cursor(
            Direction::FORWARD,
            [['field' => 'id', 'value' => 42]],
            'sig-abc',
        );

        self::assertSame(Direction::FORWARD, $cursor->getDirection());
        self::assertSame([['field' => 'id', 'value' => 42]], $cursor->getPosition());
        self::assertSame('sig-abc', $cursor->getSortSignature());
    }

    public function testWithDirectionReturnsSameInstanceWhenUnchanged(): void
    {
        $cursor = new Cursor(Direction::FORWARD, [], 'sig');

        self::assertSame($cursor, $cursor->withDirection(Direction::FORWARD));
    }

    public function testWithDirectionFlipsImmutably(): void
    {
        $forward  = new Cursor(Direction::FORWARD, [['field' => 'id', 'value' => 1]], 'sig');
        $backward = $forward->withDirection(Direction::BACKWARD);

        self::assertNotSame($forward, $backward);
        self::assertSame(Direction::FORWARD, $forward->getDirection());
        self::assertSame(Direction::BACKWARD, $backward->getDirection());
        self::assertSame($forward->getPosition(), $backward->getPosition());
        self::assertSame($forward->getSortSignature(), $backward->getSortSignature());
    }

    public function testDirectionInvertIsReciprocal(): void
    {
        self::assertSame(Direction::BACKWARD, Direction::FORWARD->invert());
        self::assertSame(Direction::FORWARD, Direction::BACKWARD->invert());
    }

    public function testSignatureForIsStableAcrossEquivalentSorts(): void
    {
        $a = SortExpression::create()->asc('id')->desc('age');
        $b = SortExpression::create()->asc('id')->desc('age');

        self::assertSame(Cursor::signatureFor($a), Cursor::signatureFor($b));
    }

    public function testSignatureForChangesWhenDirectionChanges(): void
    {
        $asc  = SortExpression::create()->asc('id');
        $desc = SortExpression::create()->desc('id');

        self::assertNotSame(Cursor::signatureFor($asc), Cursor::signatureFor($desc));
    }

    public function testSignatureForChangesWhenFieldsReorder(): void
    {
        $ab = SortExpression::create()->asc('a')->asc('b');
        $ba = SortExpression::create()->asc('b')->asc('a');

        self::assertNotSame(Cursor::signatureFor($ab), Cursor::signatureFor($ba));
    }
}
