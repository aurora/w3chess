<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/** White pieces are the uppercase board characters (P R N B Q K), black the lowercase ones. */
final class PieceColor
{
    public static function isUpper(string $c): bool
    {
        return $c !== '' && ctype_upper($c);
    }

    public static function isLower(string $c): bool
    {
        return $c !== '' && ctype_lower($c);
    }

    private function __construct()
    {
    }
}
