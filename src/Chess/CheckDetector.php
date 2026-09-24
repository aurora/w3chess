<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/**
 * Check/checkmate detection, ported from kingIsAttacked()/whereIsMyKing()/
 * isMate(). Kept algorithmically identical to the original for the same
 * reason as MoveValidator: existing games depend on its exact behaviour.
 */
final readonly class CheckDetector
{
    public function __construct(private Board $board)
    {
    }

    /** Index (0..63) of the white (or black) king, or null if it is not on the board. */
    public function whereIsMyKing(bool $white): ?int
    {
        $pos = strpos($this->board->asString(), $white ? 'K' : 'k');

        return $pos === false ? null : $pos;
    }

    /** Is the square ($x,$y) attacked by a piece of the opposite colour to $king ('K' or 'k')? */
    public function kingIsAttacked(int $x, int $y, string $king): bool
    {
        $ownColorBlocking = array_fill(0, 8, false);

        // A Bishop or Queen of the opposite colour attacks along a diagonal
        // if no other piece is in the way first.
        for ($i = 1; $i <= 7; $i++) {
            // A piece blocks the ray for the rest of this loop once seen, regardless of
            // its colour; it only counts as an *attacker* on the first (nearest) square,
            // which is why the blocking flag must be set before the attack check below.
            $p = $this->board->pieceAt($x + $i, $y + $i); // top-left to bottom-right
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[0] = true;
            if (!$ownColorBlocking[0] && ($p === 'B' || $p === 'b' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[0] = true;

            $p = $this->board->pieceAt($x - $i, $y + $i); // top-right to bottom-left
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[1] = true;
            if (!$ownColorBlocking[1] && ($p === 'B' || $p === 'b' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[1] = true;

            $p = $this->board->pieceAt($x + $i, $y - $i); // bottom-left to top-right
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[2] = true;
            if (!$ownColorBlocking[2] && ($p === 'B' || $p === 'b' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[2] = true;

            $p = $this->board->pieceAt($x - $i, $y - $i); // bottom-right to top-left
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[3] = true;
            if (!$ownColorBlocking[3] && ($p === 'B' || $p === 'b' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[3] = true;

            $p = $this->board->pieceAt($x, $y + $i); // top to bottom
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[4] = true;
            if (!$ownColorBlocking[4] && ($p === 'R' || $p === 'r' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[4] = true;

            $p = $this->board->pieceAt($x, $y - $i); // bottom to top
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[5] = true;
            if (!$ownColorBlocking[5] && ($p === 'R' || $p === 'r' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[5] = true;

            $p = $this->board->pieceAt($x + $i, $y); // left to right
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[6] = true;
            if (!$ownColorBlocking[6] && ($p === 'R' || $p === 'r' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[6] = true;

            $p = $this->board->pieceAt($x - $i, $y); // right to left
            if (PieceColor::isUpper($king) === PieceColor::isUpper($p) && $p !== 'e') $ownColorBlocking[7] = true;
            if (!$ownColorBlocking[7] && ($p === 'R' || $p === 'r' || $p === 'Q' || $p === 'q')) return true;
            if ($p !== 'e') $ownColorBlocking[7] = true;
        }

        // A Pawn could attack diagonally from one square away.
        if ($king === 'K') {
            if ($this->board->pieceAt($x - 1, $y - 1) === 'p') return true;
            if ($this->board->pieceAt($x + 1, $y - 1) === 'p') return true;
        } else {
            if ($this->board->pieceAt($x - 1, $y + 1) === 'P') return true;
            if ($this->board->pieceAt($x + 1, $y + 1) === 'P') return true;
        }

        // A Knight could attack from any of its 8 L-shaped offsets.
        foreach ([[-2, -1], [2, -1], [-1, -2], [1, -2], [-2, 1], [2, 1], [-1, 2], [1, 2]] as [$dx, $dy]) {
            $p = $this->board->pieceAt($x + $dx, $y + $dy);
            if (PieceColor::isUpper($p) !== PieceColor::isUpper($king) && strtoupper($p) === 'N') {
                return true;
            }
        }

        // The other king could attack an adjacent square (only one king exists per colour).
        $otherKing = $king === 'k' ? 'K' : 'k';
        foreach ([[1, 0], [1, 1], [1, -1], [0, 1], [0, -1], [-1, 0], [-1, 1], [-1, -1]] as [$dx, $dy]) {
            if ($this->board->pieceAt($x + $dx, $y + $dy) === $otherKing) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ports isMate(): checks the [REMIS]/[GIVEUP]/[MATE] sentinels first (see
     * Game::status), then counts legal destinations for the side to move
     * and reports whether their king is in check.
     */
    public function evaluate(MoveValidator $validator, int $moveNumber, string $pass): MateStatus
    {
        if ($pass === '[REMIS]') {
            return MateStatus::Remis;
        }
        if ($pass === '[GIVEUP]') {
            return MateStatus::GiveUp;
        }
        if ($pass === '[MATE]') {
            return MateStatus::Mate;
        }

        $possibleMoves = 0;
        for ($i1 = 0; $i1 < 8; $i1++) {
            for ($i2 = 0; $i2 < 8; $i2++) {
                if ($validator->canMove(0, 0, $i1, $i2)) {
                    $possibleMoves++;
                }
            }
        }

        if ($possibleMoves === 0) {
            return MateStatus::Mate; // No move possible anymore.
        }

        $whiteToMove = ($moveNumber % 2) === 0;
        $kingIndex = $this->whereIsMyKing($whiteToMove);
        $inCheck = $kingIndex !== null
            && $this->kingIsAttacked($kingIndex % 8, intdiv($kingIndex, 8), $whiteToMove ? 'K' : 'k');

        return $inCheck ? MateStatus::Check : MateStatus::Normal;
    }
}
