<?php

declare(strict_types=1);

namespace W3Chess\Game;

/**
 * A validated game id: an optional leading 'X' (marks a game still waiting
 * for a second player, see isnumber() in the original) followed by digits,
 * at least 8 characters total. Constructing one from untrusted input is the
 * only way GameRepository accepts a game id, which closes a path-traversal
 * gap the original had (it read/wrote DATAPATH.'/'.$ID straight from the
 * request without ever validating $ID against isnumber()).
 */
final readonly class GameId implements \Stringable
{
    private const string PATTERN = '/^X?[0-9]{7,}$/';

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (!self::isValid($value)) {
            throw new \InvalidArgumentException("Invalid game id: \"$value\".");
        }

        return new self($value);
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null || !self::isValid($value)) {
            return null;
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        return strlen($value) >= 8 && preg_match(self::PATTERN, $value) === 1;
    }

    public function isOpen(): bool
    {
        return $this->value[0] === 'X';
    }

    /** Same id without the leading 'X', once a second player has joined. */
    public function withoutOpenMarker(): self
    {
        return $this->isOpen() ? new self(substr($this->value, 1)) : $this;
    }

    public function withOpenMarker(): self
    {
        return $this->isOpen() ? $this : new self('X'.$this->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
