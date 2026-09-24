<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/** Parses the concatenated 7-char move tokens stored in a game's move history. */
final class MoveList
{
    /** @return list<Move> */
    public static function parse(?string $moves): array
    {
        if ($moves === null || $moves === '') {
            return [];
        }

        $result = [];
        for ($offset = 0; $offset + 7 <= strlen($moves); $offset += 7) {
            $result[] = Move::decode(substr($moves, $offset, 7));
        }

        return $result;
    }

    /**
     * Applies one freshly-played move to the current live board (used by
     * PieceMovedAction). Unlike replayForView(), this correctly places the
     * piece for an en passant capture: the token's resultPiece is 'O' only
     * as a marker for display purposes, the actual piece is still
     * $move->movingPiece (en passant is always a pawn move).
     */
    public static function applyToLiveBoard(Board $board, Move $move): Board
    {
        if ($move->isEnPassant()) {
            $board = $board->withPieceAt($move->fromIndex() % 8, intdiv($move->fromIndex(), 8), 'e');
            $capturedIndex = $move->movingPiece === 'P' ? $move->toIndex() + 8 : $move->toIndex() - 8;
            $board = $board->withPieceAt($capturedIndex % 8, intdiv($capturedIndex, 8), 'e');

            return $board->withPieceAt($move->toIndex() % 8, intdiv($move->toIndex(), 8), $move->movingPiece);
        }

        if ($castled = self::applyCastle($board, $move, requireKing: true)) {
            return $castled;
        }

        $board = $board->withPieceAt($move->toIndex() % 8, intdiv($move->toIndex(), 8), $move->resultPiece);

        return $board->withPieceAt($move->fromIndex() % 8, intdiv($move->fromIndex(), 8), 'e');
    }

    /**
     * Replays $count moves from the initial position (used by ViewAction to
     * show a past board state). Bug-compatible with the original view():
     * for an en passant capture, the to-square is deliberately left as-is
     * (neither square is explicitly set to the moving pawn) — a pre-existing
     * quirk of the original history viewer that live gameplay never hits,
     * since the live board is always the one actually saved to disk.
     */
    public static function replayForView(?string $moves, int $count): Board
    {
        $board = Board::start();
        $list = self::parse($moves);

        foreach (array_slice($list, 0, $count) as $move) {
            // Note: the original view() matches castle squares without checking that a
            // king actually made the move (unlike piecemoved()/applyToLiveBoard) — kept
            // as-is for bug-compatible history replay.
            if (!$move->isEnPassant() && ($castled = self::applyCastle($board, $move, requireKing: false))) {
                $board = $castled;

                continue;
            }

            if (!$move->isEnPassant()) {
                $board = $board->withPieceAt($move->fromIndex() % 8, intdiv($move->fromIndex(), 8), 'e');
                $board = $board->withPieceAt($move->toIndex() % 8, intdiv($move->toIndex(), 8), $move->resultPiece);

                continue;
            }

            // En passant: only the from-square and the captured pawn's square are cleared.
            $board = $board->withPieceAt($move->fromIndex() % 8, intdiv($move->fromIndex(), 8), 'e');
            $capturedIndex = $move->movingPiece === 'P' ? $move->toIndex() + 8 : $move->toIndex() - 8;
            $board = $board->withPieceAt($capturedIndex % 8, intdiv($capturedIndex, 8), 'e');
        }

        return $board;
    }

    private static function applyCastle(Board $board, Move $move, bool $requireKing): ?Board
    {
        if ($requireKing && strtoupper($move->movingPiece) !== 'K') {
            return null;
        }

        return match ($move->squares()) {
            'E8G8' => $board->withPieceAt(4, 0, 'e')->withPieceAt(7, 0, 'e')->withPieceAt(6, 0, 'k')->withPieceAt(5, 0, 'r'),
            'E8C8' => $board->withPieceAt(4, 0, 'e')->withPieceAt(0, 0, 'e')->withPieceAt(2, 0, 'k')->withPieceAt(3, 0, 'r'),
            'E1G1' => $board->withPieceAt(4, 7, 'e')->withPieceAt(7, 7, 'e')->withPieceAt(6, 7, 'K')->withPieceAt(5, 7, 'R'),
            'E1C1' => $board->withPieceAt(4, 7, 'e')->withPieceAt(0, 7, 'e')->withPieceAt(2, 7, 'K')->withPieceAt(3, 7, 'R'),
            default => null,
        };
    }
}
