<?php

declare(strict_types=1);

namespace W3Chess\Admin;

use W3Chess\Config\AppConfig;

/**
 * Admin password storage, modernised from the original's DES/MD5-crypt()
 * (config CRYPT/CRYPTKEY) to password_hash()/password_verify() — agreed with
 * the project owner, since the old hash is fast to brute-force. This makes
 * any pre-existing games/adm.pss incompatible: admin() already treats a
 * missing/unreadable file as "no password set yet" and offers to set one,
 * which is exactly what happens the first time this runs against an old file.
 */
final readonly class AdminAuth
{
    public function __construct(private AppConfig $config)
    {
    }

    public function isConfigured(): bool
    {
        $hash = @file_get_contents($this->config->adminPassFile);

        return $hash !== false && password_get_info($hash)['algo'] !== null;
    }

    public function verify(string $password): bool
    {
        $hash = @file_get_contents($this->config->adminPassFile);
        if ($hash === false) {
            return false;
        }

        return password_verify($password, trim($hash));
    }

    public function save(string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $written = @file_put_contents($this->config->adminPassFile, $hash);
        if ($written === false) {
            return false;
        }
        @chmod($this->config->adminPassFile, 0600);

        return true;
    }
}
