<?php

declare(strict_types=1);

namespace W3Chess\Game;

use W3Chess\Chess\Board;

/**
 * The state of one game, as stored in a single file under DATAPATH.
 *
 * Field 1 ("next*") always describes whoever moves next, field 2 ("waiting*")
 * the other player — not fixed "white"/"black" identities. PieceMovedAction
 * swaps them after every move, which is how the original keeps $pass (the
 * password mailed out) always matching the person who is actually meant to
 * move next. This mirrors the on-disk format exactly (see GameRepository).
 */
final readonly class Game
{
    public function __construct(
        public GameId $id,
        public string $nextNick,
        public string $nextMail,
        public string $waitingNick,
        public string $waitingMail,
        /** Password for the next move, or one of the sentinels [REMIS]/[GIVEUP]/[MATE]. */
        public string $pass,
        /** The waiting player's own fixed password, or '' if they use the rotating one. */
        public string $waitingFixedPass,
        /** Whether the next-to-move player has set a fixed password (instead of a fresh one each move). */
        public bool $nextHasFixedPass,
        public bool $noMailToNext,
        public bool $noMailToWaiting,
        public Board $board,
        public ?string $moves,
        public int $moveCount,
        /** Free-text message, still carrying its leading '@'/'[REMIS?]@' marker as on disk. */
        public ?string $message,
    ) {
    }

    public function isFinished(): bool
    {
        return $this->pass === '[REMIS]' || $this->pass === '[GIVEUP]' || $this->pass === '[MATE]';
    }

    public function isRemisRequested(): bool
    {
        return $this->message !== null && str_starts_with($this->message, '[REMIS?]@');
    }

    /** The message text with its marker stripped, or null if there is none. */
    public function messageText(): ?string
    {
        if ($this->message === null) {
            return null;
        }

        $at = strpos($this->message, '@');
        if ($at === false) {
            return null;
        }

        return substr($this->message, $at + 1);
    }
}
