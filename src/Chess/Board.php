<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/**
 * A chess board as a flat 64-character string, one char per square, index
 * = y * 8 + x with x = file (0=a..7=h) and y = 0 (rank 8) .. 7 (rank 1).
 * 'e' = empty, uppercase = white piece, lowercase = black piece
 * (P R N B Q K / p r n b q k). This matches the on-disk game format exactly,
 * so a Board round-trips through GameRepository unchanged.
 */
final readonly class Board
{
    public const string START = 'rnbqkbnrppppppppeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeePPPPPPPPRNBQKBNR';

    private string $squares;

    public function __construct(string $squares)
    {
        if (strlen($squares) < 64) {
            throw new \InvalidArgumentException('A board needs at least 64 characters, got '.strlen($squares).'.');
        }
        $this->squares = substr($squares, 0, 64);
    }

    public static function start(): self
    {
        return new self(self::START);
    }

    /** Returns 'e' for empty, ' ' for out-of-bounds coordinates (matches the original getPieceFromBoard). */
    public function pieceAt(int $x, int $y): string
    {
        if ($x < 0 || $x > 7 || $y < 0 || $y > 7) {
            return ' ';
        }

        return $this->squares[$y * 8 + $x];
    }

    public function withPieceAt(int $x, int $y, string $piece): self
    {
        $squares = $this->squares;
        $squares[$y * 8 + $x] = $piece;

        return new self($squares);
    }

    public function asString(): string
    {
        return $this->squares;
    }
}
