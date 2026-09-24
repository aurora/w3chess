<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/**
 * Move legality, ported statement-for-statement from the original
 * canmove()/checkMove()/wayFree()/pieceFree()/interncm() (w3chess.c via
 * w3chess.php). The algorithm is intentionally left as-is (not redesigned):
 * existing games on disk depend on its exact rule semantics, and a "cleaner"
 * reimplementation would risk subtly changing which moves are legal.
 *
 * canMove() uses two calling conventions, preserved from the original:
 *  - "apply a move": $fromx/$fromy are display coordinates
 *    (fromx = ord('A')+file, fromy = 8-rank), used by Actions applying an
 *    actual move typed/clicked by a player.
 *  - "probe a square": $fromx == 0 or $fromy == 0, used internally by
 *    pieceFree()/isMate() to ask "is the opponent piece sitting at ($x,$y)
 *    able to reach some square", without caring where it moves from.
 */
final readonly class MoveValidator
{
    public function __construct(
        private Board $board,
        private int $moveNumber,
        private ?string $moveHistory,
    ) {
    }

    public function board(): Board
    {
        return $this->board;
    }

    public function canMove(int $fromx, int $fromy, int $x, int $y): bool
    {
        // Pieces out of board?
        if ($fromx > ord('H') || $x > ord('H') || $fromy > 8 || $y > 8) {
            return false;
        }

        // First ("probe") or second ("apply") step of a move?
        $moveto = ($fromx !== 0 && $fromy !== 0);

        // Normalise fromx/fromy to 0..7, beginning at top left.
        if ($fromy > 0) {
            $fromy = 8 - $fromy;
        }
        if ($fromx > 0) {
            $fromx -= ord('A');
        }

        // Colour to move next: false = white, true = black.
        $currentIsBlack = ($this->moveNumber % 2) === 1;

        $piece = $this->board->pieceAt($x, $y);
        $fpiece = $this->board->pieceAt($fromx, $fromy);

        // Global rules.
        if ($fpiece === 'e' && $piece === 'e') {
            return false;
        }
        if ($piece === ' ' || $fpiece === ' ') {
            return false;
        }
        // Only move one of your own pieces.
        if (!$moveto && ($currentIsBlack === PieceColor::isUpper($piece) || $piece === 'e')) {
            return false;
        }
        // Only move TO a field that is none of your own pieces.
        if ($moveto && $currentIsBlack !== PieceColor::isUpper($piece) && $piece !== 'e') {
            return false;
        }

        // Must not be in check *after* moving.
        if ($moveto) {
            $probeBoard = $this->board->withPieceAt($x, $y, $this->board->pieceAt($fromx, $fromy))->withPieceAt($fromx, $fromy, 'e');
            $detector = new CheckDetector($probeBoard);
            $kingIndex = $detector->whereIsMyKing(! $currentIsBlack);
            if ($kingIndex !== null) {
                $king = $currentIsBlack ? 'k' : 'K';
                if ($detector->kingIsAttacked($kingIndex % 8, intdiv($kingIndex, 8), $king)) {
                    return false;
                }
            }
        }

        // The piece has to be "free" (able to move somewhere) when merely probing it.
        if (!$moveto && !$this->pieceFree($piece, $x, $y)) {
            return false;
        }

        if ($moveto) {
            $dx = abs($fromx - $x);
            $dy = abs($fromy - $y);

            // The Knight moves in an L-shape and may jump over other pieces.
            if (strtolower($fpiece) === 'n') {
                if ($dx + $dy !== 3 || $dx * $dy !== 2) {
                    return false;
                }
            }

            // The Black Pawn.
            if ($fpiece === 'p') {
                if ($fromx === $x && $fromy === $y - 1 && $this->board->pieceAt($x, $y) === 'e') {
                    return true;
                }
                if ($fromx === $x && $fromy === $y - 2 && $fromy === 1 && $this->board->pieceAt($x, $y - 1) === 'e' && $this->board->pieceAt($x, $y) === 'e') {
                    return true;
                }
                if ($fromx === $x - 1 && $fromy === $y - 1 && $this->board->pieceAt($x, $y) !== 'e' && !PieceColor::isLower($this->board->pieceAt($x, $y))) {
                    return true;
                }
                if ($fromx === $x + 1 && $fromy === $y - 1 && $this->board->pieceAt($x, $y) !== 'e' && !PieceColor::isLower($this->board->pieceAt($x, $y))) {
                    return true;
                }
                // En passant.
                if ($y === 5 && $fromy === 4 && $fromx > 0 && $x === $fromx - 1) {
                    $enp = sprintf('P%s2%s4Pe', chr(ord('A') + $fromx - 1), chr(ord('A') + $fromx - 1));
                    if ($this->moveHistory !== null && substr($this->moveHistory, -7) === $enp) {
                        return true;
                    }
                }
                if ($y === 5 && $fromy === 4 && $fromx < 7 && $x === $fromx + 1) {
                    $enp = sprintf('P%s2%s4Pe', chr(ord('A') + $fromx + 1), chr(ord('A') + $fromx + 1));
                    if ($this->moveHistory !== null && substr($this->moveHistory, -7) === $enp) {
                        return true;
                    }
                }

                return false;
            }

            // The White Pawn.
            if ($fpiece === 'P') {
                if ($fromx === $x && $fromy === $y + 1 && $this->board->pieceAt($x, $y) === 'e') {
                    return true;
                }
                if ($fromx === $x && $fromy === $y + 2 && $fromy === 6 && $this->board->pieceAt($x, $y + 1) === 'e' && $this->board->pieceAt($x, $y) === 'e') {
                    return true;
                }
                if ($fromx === $x - 1 && $fromy === $y + 1 && $this->board->pieceAt($x, $y) !== 'e' && !PieceColor::isUpper($this->board->pieceAt($x, $y))) {
                    return true;
                }
                if ($fromx === $x + 1 && $fromy === $y + 1 && $this->board->pieceAt($x, $y) !== 'e' && !PieceColor::isUpper($this->board->pieceAt($x, $y))) {
                    return true;
                }
                // En passant.
                if ($y === 2 && $fromy === 3 && $fromx > 0 && $x === $fromx - 1) {
                    $enp = sprintf('p%s7%s5pe', chr(ord('A') + $fromx - 1), chr(ord('A') + $fromx - 1));
                    if ($this->moveHistory !== null && substr($this->moveHistory, -7) === $enp) {
                        return true;
                    }
                }
                if ($y === 2 && $fromy === 3 && $fromx < 7 && $x === $fromx + 1) {
                    $enp = sprintf('p%s7%s5pe', chr(ord('A') + $fromx + 1), chr(ord('A') + $fromx + 1));
                    if ($this->moveHistory !== null && substr($this->moveHistory, -7) === $enp) {
                        return true;
                    }
                }

                return false;
            }

            // The King.
            if (strtolower($fpiece) === 'k') {
                $history = $this->moveHistory ?? '';
                $detector = new CheckDetector($this->board);

                if ($fpiece === 'k') { // black king
                    if ($fromx === 4 && $fromy === 0 && $x === 6 && $y === 0) { // castle king side
                        if (str_contains($history, 'kE8')) return false;
                        if (str_contains($history, 'rH8')) return false;
                        if ($this->board->pieceAt(5, 0) !== 'e') return false;
                        if ($this->board->pieceAt(6, 0) !== 'e') return false;
                        if ($detector->kingIsAttacked(4, 0, $fpiece)) return false;
                        if ($detector->kingIsAttacked(5, 0, $fpiece)) return false;
                        if ($detector->kingIsAttacked(6, 0, $fpiece)) return false;

                        return true;
                    }
                    if ($fromx === 4 && $fromy === 0 && $x === 2 && $y === 0) { // castle queen side
                        if (str_contains($history, 'kE8')) return false;
                        if (str_contains($history, 'rA8')) return false;
                        if ($this->board->pieceAt(1, 0) !== 'e') return false;
                        if ($this->board->pieceAt(2, 0) !== 'e') return false;
                        if ($this->board->pieceAt(3, 0) !== 'e') return false;
                        if ($detector->kingIsAttacked(4, 0, $fpiece)) return false;
                        if ($detector->kingIsAttacked(3, 0, $fpiece)) return false;
                        if ($detector->kingIsAttacked(2, 0, $fpiece)) return false;

                        return true;
                    }
                } else { // white king
                    if ($fromx === 4 && $fromy === 7 && $x === 6 && $y === 7) { // castle king side
                        if (str_contains($history, 'KE1')) return false;
                        if (str_contains($history, 'RH1')) return false;
                        if ($this->board->pieceAt(5, 7) !== 'e') return false;
                        if ($this->board->pieceAt(6, 7) !== 'e') return false;
                        if ($detector->kingIsAttacked(4, 7, $fpiece)) return false;
                        if ($detector->kingIsAttacked(5, 7, $fpiece)) return false;
                        if ($detector->kingIsAttacked(6, 7, $fpiece)) return false;

                        return true;
                    }
                    if ($fromx === 4 && $fromy === 7 && $x === 2 && $y === 7) { // castle queen side
                        if (str_contains($history, 'KE1')) return false;
                        if (str_contains($history, 'RA1')) return false;
                        if ($this->board->pieceAt(1, 7) !== 'e') return false;
                        if ($this->board->pieceAt(2, 7) !== 'e') return false;
                        if ($this->board->pieceAt(3, 7) !== 'e') return false;
                        if ($detector->kingIsAttacked(4, 7, $fpiece)) return false;
                        if ($detector->kingIsAttacked(3, 7, $fpiece)) return false;
                        if ($detector->kingIsAttacked(2, 7, $fpiece)) return false;

                        return true;
                    }
                }

                // The King may only move 1 field (outside of castling, handled above).
                if ($dx > 1 || $dy > 1) {
                    return false;
                }

                return true;
            }

            $direction = $this->checkMove($fromx, $fromy, $x, $y);

            // The Queen can only move diagonally and orthogonally.
            if (strtolower($fpiece) === 'q' && $direction === 0) {
                return false;
            }
            // The Rook can only move orthogonally.
            if (strtolower($fpiece) === 'r' && ($direction % 2 === 1 || $direction === 0)) {
                return false;
            }
            // The Bishop can only move diagonally.
            if (strtolower($fpiece) === 'b' && $direction % 2 === 0) {
                return false;
            }
        }

        // If there is no rule against it, the move is allowed.
        return true;
    }

    /** Internal helper: can the piece sitting at ($x,$y) reach ($tox,$toy)? Used by pieceFree(). */
    private function interncm(int $fromx, int $fromy, int $x, int $y): bool
    {
        if ($fromx > 7 || $fromx < 0 || $x > 7 || $x < 0 || $fromy > 7 || $fromy < 0 || $y > 7 || $y < 0) {
            return false;
        }

        return $this->canMove(ord('A') + $fromx, 8 - $fromy, $x, $y);
    }

    /** Is $piece (assumed to sit at $x,$y) able to move anywhere at all? */
    private function pieceFree(string $piece, int $x, int $y): bool
    {
        switch (strtolower($piece)) {
            case 'q':
                for ($i = 1; $i <= 7; $i++) {
                    if ($this->interncm($x, $y, $x - $i, $y)) return true;
                    if ($this->interncm($x, $y, $x + $i, $y)) return true;
                    if ($this->interncm($x, $y, $x, $y - $i)) return true;
                    if ($this->interncm($x, $y, $x, $y + $i)) return true;
                    if ($this->interncm($x, $y, $x - $i, $y + $i)) return true;
                    if ($this->interncm($x, $y, $x + $i, $y + $i)) return true;
                    if ($this->interncm($x, $y, $x - $i, $y - $i)) return true;
                    if ($this->interncm($x, $y, $x + $i, $y - $i)) return true;
                }

                return false;
            case 'b':
                for ($i = 1; $i <= 7; $i++) {
                    if ($this->interncm($x, $y, $x - $i, $y + $i)) return true;
                    if ($this->interncm($x, $y, $x + $i, $y + $i)) return true;
                    if ($this->interncm($x, $y, $x - $i, $y - $i)) return true;
                    if ($this->interncm($x, $y, $x + $i, $y - $i)) return true;
                }

                return false;
            case 'r':
                for ($i = 1; $i <= 7; $i++) {
                    if ($this->interncm($x, $y, $x - $i, $y)) return true;
                    if ($this->interncm($x, $y, $x + $i, $y)) return true;
                    if ($this->interncm($x, $y, $x, $y - $i)) return true;
                    if ($this->interncm($x, $y, $x, $y + $i)) return true;
                }

                return false;
            case 'n':
                return $this->interncm($x, $y, $x - 1, $y - 2)
                    || $this->interncm($x, $y, $x + 1, $y - 2)
                    || $this->interncm($x, $y, $x - 1, $y + 2)
                    || $this->interncm($x, $y, $x + 1, $y + 2)
                    || $this->interncm($x, $y, $x + 2, $y - 1)
                    || $this->interncm($x, $y, $x - 2, $y - 1)
                    || $this->interncm($x, $y, $x - 2, $y + 1)
                    || $this->interncm($x, $y, $x + 2, $y + 1);
            case 'p':
                return $this->interncm($x, $y, $x - 1, $y - 1)
                    || $this->interncm($x, $y, $x, $y - 1)
                    || $this->interncm($x, $y, $x, $y - 2)
                    || $this->interncm($x, $y, $x + 1, $y - 1)
                    || $this->interncm($x, $y, $x - 1, $y + 1)
                    || $this->interncm($x, $y, $x, $y + 1)
                    || $this->interncm($x, $y, $x, $y + 2)
                    || $this->interncm($x, $y, $x + 1, $y + 1);
            case 'k':
                return $this->interncm($x, $y, $x - 1, $y)
                    || $this->interncm($x, $y, $x - 1, $y - 1)
                    || $this->interncm($x, $y, $x - 1, $y + 1)
                    || $this->interncm($x, $y, $x, $y - 1)
                    || $this->interncm($x, $y, $x, $y + 1)
                    || $this->interncm($x, $y, $x + 1, $y)
                    || $this->interncm($x, $y, $x + 1, $y - 1)
                    || $this->interncm($x, $y, $x + 1, $y + 1)
                    || $this->interncm($x, $y, $x - 2, $y)
                    || $this->interncm($x, $y, $x + 2, $y);
            default: // should never happen
                return true;
        }
    }

    private function wayFree(int $fromx, int $fromy, int $tox, int $toy, int $xsgn, int $ysgn): bool
    {
        for ($x = $fromx + $xsgn, $y = $fromy + $ysgn; $x !== $tox || $y !== $toy; $x += $xsgn, $y += $ysgn) {
            if ($this->board->pieceAt($x, $y) !== 'e') {
                return false;
            }
        }

        return true;
    }

    /** Returns a numpad-style direction code (1-9, 0 = no straight/diagonal line) if the path to $tox/$toy is clear. */
    private function checkMove(int $fromx, int $fromy, int $tox, int $toy): int
    {
        $xd = $tox - $fromx;
        $yd = $fromy - $toy;

        if (abs($xd) !== abs($yd) && $xd * $yd !== 0) {
            return 0;
        }

        if ($xd < 0) {
            if ($yd < 0) return 1 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, -1, 1);
            if ($yd > 0) return 7 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, -1, -1);
            return 4 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, -1, 0);
        }
        if ($xd > 0) {
            if ($yd < 0) return 3 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, 1, 1);
            if ($yd > 0) return 9 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, 1, -1);
            return 6 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, 1, 0);
        }
        if ($yd < 0) return 2 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, 0, 1);
        if ($yd > 0) return 8 * (int) $this->wayFree($fromx, $fromy, $tox, $toy, 0, -1);

        return 0;
    }

}
