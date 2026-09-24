<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/** Display info for one board character, mirroring the original piecepointer(). */
final readonly class PieceIcon
{
    public function __construct(
        public string $shortName,
        public string $longName,
        public string $imageUrl,
        public string $smallImageUrl,
        public string $smallSelectedImageUrl,
    ) {
    }
}
