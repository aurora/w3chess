<?php

declare(strict_types=1);

namespace W3Chess\Game;

/** Ports genPass(): a random 10-character alphanumeric move password. */
final class PasswordGenerator
{
    public function generate(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
