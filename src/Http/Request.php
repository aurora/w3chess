<?php

declare(strict_types=1);

namespace W3Chess\Http;

/**
 * Typed access to the current request, replacing the original's manual
 * query-string parsing (queryParam()/query2String()/hexDec()) with PHP's own
 * GET/POST superglobals — PHP already url-decodes these the same way (and
 * more correctly, e.g. it does not need a hand-rolled hexdec()). The request
 * method dispatch (GET query string vs. raw POST body) matches the original:
 * exactly one of $_GET/$_POST is consulted, never both merged.
 */
final readonly class Request
{
    /** @param array<string, string> $params */
    private function __construct(
        private array $params,
        public string $scriptName,
        public string $serverName,
    ) {
    }

    public static function fromGlobals(): self
    {
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

        /** @var array<string, string> $params */
        $params = [];
        foreach ($isPost ? $_POST : $_GET as $key => $value) {
            if (is_string($value)) {
                $params[$key] = $value;
            }
        }

        return new self(
            params: $params,
            scriptName: $_SERVER['SCRIPT_NAME'] ?? '',
            serverName: $_SERVER['SERVER_NAME'] ?? '',
        );
    }

    public function get(string $name): ?string
    {
        return $this->params[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->params);
    }

    public function int(string $name, int $default = 0): int
    {
        $value = $this->get($name);

        return $value === null ? $default : (int) $value;
    }

    public function acceptLanguage(): string
    {
        return $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    }

    /**
     * ViewAction's page-arrow links are <input type=image name="NUM5">: when clicked,
     * browsers submit "NUM5.x"/"NUM5.y" (PHP turns the dot into an underscore) instead
     * of a plain "NUM=5" pair, so get('NUM') never finds it. This mirrors the original's
     * fallback of scanning the raw query for "NUM" followed by digits.
     */
    public function imageButtonNumber(string $namePrefix): ?int
    {
        foreach (array_keys($this->params) as $key) {
            if (preg_match('/^'.preg_quote($namePrefix, '/').'(\d+)_[xy]$/', $key, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
