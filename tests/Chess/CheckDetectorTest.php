<?php

declare(strict_types=1);

namespace W3Chess\Tests\Chess;

use PHPUnit\Framework\TestCase;
use W3Chess\Chess\Board;
use W3Chess\Chess\CheckDetector;
use W3Chess\Chess\MateStatus;
use W3Chess\Chess\MoveValidator;

final class CheckDetectorTest extends TestCase
{
    public function testWhereIsMyKingFindsTheKing(): void
    {
        $detector = new CheckDetector(Board::start());

        self::assertSame(60, $detector->whereIsMyKing(true)); // e1
        self::assertSame(4, $detector->whereIsMyKing(false)); // e8
    }

    public function testWhereIsMyKingReturnsNullWhenAbsent(): void
    {
        $board = Board::start()->withPieceAt(4, 7, 'e'); // remove the white king
        $detector = new CheckDetector($board);

        self::assertNull($detector->whereIsMyKing(true));
    }

    public function testRookGivesCheckAlongAnOpenFile(): void
    {
        // White king on e1, black rook on e8, nothing in between.
        $board = (new Board(str_repeat('e', 64)))
            ->withPieceAt(4, 7, 'K')
            ->withPieceAt(4, 0, 'r');
        $detector = new CheckDetector($board);

        self::assertTrue($detector->kingIsAttacked(4, 7, 'K'));
    }

    public function testRookCheckIsBlockedByAnInterveningPiece(): void
    {
        $board = (new Board(str_repeat('e', 64)))
            ->withPieceAt(4, 7, 'K')
            ->withPieceAt(4, 0, 'r')
            ->withPieceAt(4, 4, 'P'); // blocks the file
        $detector = new CheckDetector($board);

        self::assertFalse($detector->kingIsAttacked(4, 7, 'K'));
    }

    public function testLadderMateIsReportedAsMate(): void
    {
        // Minimal king+rook ladder mate: black king boxed into the h8 corner, white
        // rook checks along the 8th rank (also covering g8), white king covers g7/h7.
        $board = (new Board(str_repeat('e', 64)))
            ->withPieceAt(7, 0, 'k') // Kh8
            ->withPieceAt(0, 0, 'r') // Ra8
            ->withPieceAt(6, 2, 'K'); // Kg6
        $validator = new MoveValidator($board, 1, null); // black to move, in mate
        $detector = new CheckDetector($board);

        self::assertSame(MateStatus::Mate, $detector->evaluate($validator, 1, ''));
    }

    public function testSentinelPassValuesShortCircuitToTheirStatus(): void
    {
        $detector = new CheckDetector(Board::start());
        $validator = new MoveValidator(Board::start(), 0, null);

        self::assertSame(MateStatus::Remis, $detector->evaluate($validator, 0, '[REMIS]'));
        self::assertSame(MateStatus::GiveUp, $detector->evaluate($validator, 0, '[GIVEUP]'));
        self::assertSame(MateStatus::Mate, $detector->evaluate($validator, 0, '[MATE]'));
    }
}
