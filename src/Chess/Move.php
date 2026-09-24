<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/**
 * One played move, in the 7-character on-disk/on-wire format used throughout
 * the original app (game files, mail bodies, move list rendering):
 *
 *   [0]     piece that was standing on the from-square before the move
 *   [1..2]  from-square, e.g. "E2" (file A-H, rank 1-8)
 *   [3..4]  to-square, e.g. "E4"
 *   [5]     piece now standing on the to-square (promotion target), or the
 *           literal 'O' as a sentinel meaning "this was an en passant capture"
 *   [6]     piece that was captured on the to-square, or 'e' if none
 *           (en passant captures leave this 'e', since the captured pawn
 *           never stood on the to-square)
 */
final readonly class Move
{
    public function __construct(
        public string $movingPiece,
        public string $fromFile,
        public string $fromRank,
        public string $toFile,
        public string $toRank,
        public string $resultPiece,
        public string $capturedPiece,
    ) {
    }

    public static function decode(string $token): self
    {
        return new self($token[0], $token[1], $token[2], $token[3], $token[4], $token[5], $token[6]);
    }

    public function encode(): string
    {
        return $this->movingPiece.$this->fromFile.$this->fromRank.$this->toFile.$this->toRank.$this->resultPiece.$this->capturedPiece;
    }

    /** The 4-character square pair (e.g. "E1G1"), used to detect castling moves. */
    public function squares(): string
    {
        return $this->fromFile.$this->fromRank.$this->toFile.$this->toRank;
    }

    public function isEnPassant(): bool
    {
        return $this->resultPiece === 'O';
    }

    public function fromIndex(): int
    {
        return (ord('8') - ord($this->fromRank)) * 8 + (ord($this->fromFile) - ord('A'));
    }

    public function toIndex(): int
    {
        return (ord('8') - ord($this->toRank)) * 8 + (ord($this->toFile) - ord('A'));
    }
}
