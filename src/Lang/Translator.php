<?php

declare(strict_types=1);

namespace W3Chess\Lang;

/**
 * Loads config/lang/{locale}.php and looks up strings by key, with printf-style
 * placeholders (the original app used PHP constants as printf() format
 * strings, e.g. RESTITLE = "Chess match: %s against %s").
 */
final class Translator
{
    /** @param array<string, string> $strings */
    private function __construct(
        public readonly string $locale,
        private readonly array $strings,
    ) {
    }

    public static function forLocale(string $langDir, string $locale): self
    {
        /** @var array<string, string> $strings */
        $strings = require $langDir.'/'.$locale.'.php';

        return new self($locale, $strings);
    }

    /**
     * Picks the best available locale for an Accept-Language header, falling
     * back to $default. Does simple RFC 4647-ish quality-value negotiation.
     *
     * @param list<string> $available
     */
    public static function negotiateLocale(string $acceptLanguageHeader, string $default, array $available): string
    {
        $header = trim($acceptLanguageHeader);
        if ($header === '') {
            return $default;
        }

        $ranked = [];
        foreach (explode(',', $header) as $index => $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $segments = explode(';', $part);
            $tag = strtolower(trim($segments[0]));
            $quality = 1.0;
            foreach (array_slice($segments, 1) as $param) {
                $param = trim($param);
                if (str_starts_with($param, 'q=') && is_numeric(substr($param, 2))) {
                    $quality = (float) substr($param, 2);
                }
            }

            // Stable-sort by quality, preserving header order as a tie-breaker.
            $ranked[] = ['tag' => str_replace('-', '_', $tag), 'quality' => $quality, 'order' => $index];
        }

        usort($ranked, static fn (array $a, array $b): int => $b['quality'] <=> $a['quality'] ?: $a['order'] <=> $b['order']);

        foreach ($ranked as $candidate) {
            if ($candidate['tag'] === '*') {
                return $default;
            }
            if (in_array($candidate['tag'], $available, true)) {
                return $candidate['tag'];
            }
            // Match the primary subtag, e.g. "pt" against available "pt_br", or "de_at" against available "de".
            $primary = explode('_', $candidate['tag'])[0];
            foreach ($available as $locale) {
                if (explode('_', $locale)[0] === $primary) {
                    return $locale;
                }
            }
        }

        return $default;
    }

    public function get(string $key, string|int|float ...$args): string
    {
        $value = $this->strings[$key] ?? $key;

        return $args === [] ? $value : sprintf($value, ...$args);
    }
}
