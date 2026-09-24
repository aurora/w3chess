<?php

declare(strict_types=1);

namespace W3Chess\Mail;

use W3Chess\Config\AppConfig;

/**
 * Sends mail by piping an RFC822 message to the sendmail-compatible binary
 * configured in config/config.php, exactly like the original w3mail()/
 * sendgames(). Header values are stripped of CR/LF to prevent header
 * injection through untrusted nicknames/messages — the original wrote them
 * unsanitised.
 */
final class Mailer
{
    public function __construct(private readonly AppConfig $config)
    {
    }

    /**
     * @return resource|false false if the sendmail binary could not be spawned (e.g.
     *     missing/misconfigured locally) — callers should treat this as "mail not sent"
     *     rather than let it abort the whole request, matching the original's errorwin()
     *     + return on a failed popen(), which showed a notice but kept the page working.
     */
    public function open(string $to): mixed
    {
        return @popen($this->config->sendmail.' '.escapeshellarg($to), 'w');
    }

    /** @param resource $pipe */
    public function header($pipe, string $name, string $value): void
    {
        fwrite($pipe, $name.': '.self::sanitizeHeader($value)."\n");
    }

    /** @param resource $pipe */
    public function write($pipe, string $text): void
    {
        fwrite($pipe, $text);
    }

    /** @param resource $pipe */
    public function close($pipe): void
    {
        pclose($pipe);
    }

    private static function sanitizeHeader(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
    }
}
