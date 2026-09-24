<?php

declare(strict_types=1);

namespace W3Chess\Tests\Chess;

use PHPUnit\Framework\TestCase;
use W3Chess\Chess\Board;

final class BoardTest extends TestCase
{
    public function testStartPositionHasRooksInCorners(): void
    {
        $board = Board::start();

        self::assertSame('r', $board->pieceAt(0, 0));
        self::assertSame('r', $board->pieceAt(7, 0));
        self::assertSame('R', $board->pieceAt(0, 7));
        self::assertSame('R', $board->pieceAt(7, 7));
        self::assertSame('e', $board->pieceAt(4, 4));
    }

    public function testOutOfBoundsReturnsSpace(): void
    {
        $board = Board::start();

        self::assertSame(' ', $board->pieceAt(-1, 0));
        self::assertSame(' ', $board->pieceAt(8, 0));
        self::assertSame(' ', $board->pieceAt(0, 8));
    }

    public function testWithPieceAtIsImmutable(): void
    {
        $board = Board::start();
        $moved = $board->withPieceAt(4, 4, 'Q');

        self::assertSame('e', $board->pieceAt(4, 4));
        self::assertSame('Q', $moved->pieceAt(4, 4));
    }
}
