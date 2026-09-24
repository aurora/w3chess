<?php

declare(strict_types=1);

namespace W3Chess\Tests\Chess;

use PHPUnit\Framework\TestCase;
use W3Chess\Chess\Board;
use W3Chess\Chess\MoveValidator;

/**
 * canMove()'s "apply" calling convention (used here): $fromx = ord('A') +
 * file (0-7), $fromy = 8 - rank (1-8); $x/$y are the raw 0-7 destination
 * column/row, matching how ResumeGameAction/PieceMovedAction call it.
 */
final class MoveValidatorTest extends TestCase
{
    /**
     * canMove()'s from-square parameters are the *raw* file letter code and rank digit
     * (e.g. from the request's MOVE="E2E4"), not board row/column — it normalises them
     * internally (fromy = 8 - fromy, fromx -= ord('A')) to match the destination's own
     * 0-7 board coordinates.
     */
    private static function at(string $file, int $rank): array
    {
        return [ord(strtoupper($file)), $rank];
    }

    public function testWhitePawnCanAdvanceTwoSquaresFromStart(): void
    {
        $validator = new MoveValidator(Board::start(), 0, null);
        [$fromX, $fromY] = self::at('E', 2);

        self::assertTrue($validator->canMove($fromX, $fromY, 4, 4)); // e2-e4
    }

    public function testWhitePawnCannotAdvanceThreeSquares(): void
    {
        $validator = new MoveValidator(Board::start(), 0, null);
        [$fromX, $fromY] = self::at('E', 2);

        self::assertFalse($validator->canMove($fromX, $fromY, 4, 3)); // e2-e5
    }

    public function testKnightCanJumpOverPawnsFromStart(): void
    {
        $validator = new MoveValidator(Board::start(), 0, null);
        [$fromX, $fromY] = self::at('G', 1);

        self::assertTrue($validator->canMove($fromX, $fromY, 5, 5)); // Ng1-f3
    }

    public function testCannotMoveOpponentsPiece(): void
    {
        $validator = new MoveValidator(Board::start(), 0, null); // white to move
        [$fromX, $fromY] = self::at('E', 7); // a black pawn

        self::assertFalse($validator->canMove($fromX, $fromY, 4, 4));
    }

    public function testCannotCaptureOwnPiece(): void
    {
        $validator = new MoveValidator(Board::start(), 0, null);
        [$fromX, $fromY] = self::at('A', 1); // white rook

        self::assertFalse($validator->canMove($fromX, $fromY, 0, 6)); // Ra1-a2 (own pawn there)
    }

    public function testEnPassantCaptureIsLegalRightAfterTheDoubleStep(): void
    {
        // White pawn already on e5, black just played d7-d5 (the required last move for e.p.).
        $board = Board::start()
            ->withPieceAt(4, 3, 'P')->withPieceAt(4, 6, 'e') // white pawn e2->e5 (pre-placed for the test)
            ->withPieceAt(3, 3, 'p')->withPieceAt(3, 1, 'e'); // black pawn d7->d5
        $history = 'pD7D5Pe'; // black pawn d7-d5, as the just-played move
        $validator = new MoveValidator($board, 1, $history); // move 1 = white to move again

        [$fromX, $fromY] = self::at('E', 5);
        self::assertTrue($validator->canMove($fromX, $fromY, 3, 2)); // e5xd6 e.p.
    }

    public function testCastlingKingSideIsLegalWhenSquaresAreClearAndUnattacked(): void
    {
        // Clear f1 (bishop) and g1 (knight) so the king has a clear path; everything
        // else stays as the start position, so nothing attacks e1/f1/g1.
        $board = Board::start()->withPieceAt(5, 7, 'e')->withPieceAt(6, 7, 'e');
        $validator = new MoveValidator($board, 0, null);
        [$fromX, $fromY] = self::at('E', 1);

        self::assertTrue($validator->canMove($fromX, $fromY, 6, 7)); // O-O
    }
}
