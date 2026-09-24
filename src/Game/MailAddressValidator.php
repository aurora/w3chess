<?php

declare(strict_types=1);

namespace W3Chess\Game;

/**
 * Ports checkmail(): a deliberately loose sanity check (not real RFC
 * validation) — just enough to reject obviously-broken addresses before
 * mailing to them. Returns 0 for "looks ok", a negative code otherwise
 * (kept from the original, even though callers only ever check `< 0`).
 */
final class MailAddressValidator
{
    public static function validate(?string $mail): int
    {
        if ($mail === null) {
            return -1;
        }
        if ($mail === '') {
            return -2;
        }
        $at = strpos($mail, '@');
        if ($at === false) {
            return -3;
        }
        $dot = strrpos($mail, '.');
        if ($dot === false) {
            return -4;
        }
        if ($at > $dot) {
            return -5;
        }
        if ($at === 0) {
            return -6;
        }
        if (($dot - $at - 1) < 2) {
            return -7;
        }
        $tldLength = strlen(substr($mail, $dot)) - 1;
        if ($tldLength !== 2 && $tldLength !== 3 && $tldLength !== 4) {
            return -8;
        }

        return 0;
    }
}
