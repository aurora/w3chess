<?php

declare(strict_types=1);

namespace W3Chess\Chess;

/** Mirrors the original isMate() return codes (0=normal .. 4=giveup). */
enum MateStatus: int
{
    case Normal = 0;
    case Mate = 1;
    case Check = 2;
    case Remis = 3;
    case GiveUp = 4;
}
