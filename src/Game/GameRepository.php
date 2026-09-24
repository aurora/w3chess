<?php

declare(strict_types=1);

namespace W3Chess\Game;

use W3Chess\Chess\Board;
use W3Chess\Config\AppConfig;

/**
 * Reads and writes game files under DATAPATH, one file per game, in the
 * exact line format the original readgamedata()/rewriteData() used:
 *
 *   line 0  "[fixedpass:[1:]]nick"  of the next player to move
 *   line 1  mail address of the next player to move
 *   line 2  "[fixedpass:[1:]]nick" of the waiting player
 *   line 3  mail address of the waiting player
 *   line 4  move password, or [REMIS]/[GIVEUP]/[MATE]
 *   line 5  move count
 *   line 6  move history (concatenated 7-char tokens)
 *   line 7  64-character board
 *   line 8  optional free-text message ("@..." or "[REMIS?]@...")
 *
 * Existing game files (and any game ids in flight in mailed links) must keep
 * working, so this format is intentionally not "modernised" into e.g. JSON.
 */
final readonly class GameRepository
{
    public function __construct(private AppConfig $config)
    {
    }

    public function exists(GameId $id): bool
    {
        return is_file($this->path($id));
    }

    public function load(GameId $id): ?Game
    {
        $lines = @file($this->path($id), FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        $rawNextLine = $lines[0] ?? '';
        $nextMail = $lines[1] ?? '';
        $rawWaitingLine = $lines[2] ?? '';
        $waitingMail = $lines[3] ?? '';
        $pass = $lines[4] ?? '';

        $isFinished = $pass === '[REMIS]' || $pass === '[GIVEUP]' || $pass === '[MATE]';
        $isMate = $pass === '[MATE]';

        $nextHasFixedPass = false;
        $noMailToNext = false;
        $waitingFixedPass = '';
        $noMailToWaiting = false;

        if (!$isFinished) {
            [$nextHasFixedPass, $noMailToNext, $fixedPass] = self::parsePrefix($rawNextLine);
            if ($nextHasFixedPass && !$isMate) {
                $pass = $fixedPass;
            }

            [, $noMailToWaiting, $waitingFixedPass] = self::parsePrefix($rawWaitingLine);
        }

        $nextNick = self::stripPrefix($rawNextLine);
        $waitingNick = self::stripPrefix($rawWaitingLine);

        $moveCount = (int) ($lines[5] ?? 0);
        $moves = $moveCount > 0 ? ($lines[6] ?? '') : null;
        $boardString = $lines[7] ?? '';

        if (strlen($boardString) < 64) {
            return null;
        }

        $message = (isset($lines[8]) && $lines[8] !== '') ? $lines[8] : null;

        return new Game(
            id: $id,
            nextNick: $nextNick,
            nextMail: $nextMail,
            waitingNick: $waitingNick,
            waitingMail: $waitingMail,
            pass: $pass,
            waitingFixedPass: $waitingFixedPass,
            nextHasFixedPass: $nextHasFixedPass,
            noMailToNext: $noMailToNext,
            noMailToWaiting: $noMailToWaiting,
            board: new Board($boardString),
            moves: $moves,
            moveCount: $moveCount,
            message: $message,
        );
    }

    /**
     * Writes $game back to disk. $overridePass/$overrideMessage/$overrideMail
     * mirror the original rewriteData()'s $PASS/$MSG/$MAIL parameters: when
     * given, they take precedence over $game's own pass/message/nextMail for
     * this write only (used by the few call sites that rewrite a game before
     * having folded a new value into the Game object itself).
     */
    public function save(
        Game $game,
        ?string $overridePass = null,
        ?string $overrideMessage = null,
        ?string $overrideMail = null,
        bool $swap = false,
    ): void {
        $path = $this->path($game->id);
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open game file for writing: $path");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException("Unable to lock game file: $path");
            }
            ftruncate($handle, 0);
            rewind($handle);

            // $overrideMail always replaces the *next*-to-move player's mail (mirrors the
            // original writing $MAIL into GPLAYER1's slot regardless of $swap).
            $nextSlot = ['nick' => $game->nextNick, 'mail' => $overrideMail ?? $game->nextMail, 'fixedPass' => $game->nextHasFixedPass ? substr($game->pass, 0, 10) : '', 'noMail' => $game->noMailToNext];
            $waitingSlot = ['nick' => $game->waitingNick, 'mail' => $game->waitingMail, 'fixedPass' => $game->waitingFixedPass, 'noMail' => $game->noMailToWaiting];

            $slots = $swap ? [$waitingSlot, $nextSlot] : [$nextSlot, $waitingSlot];

            $out = self::encodeSlot($slots[0]['fixedPass'], $slots[0]['noMail'], $slots[0]['nick'])."\n";
            $out .= $slots[0]['mail']."\n";
            $out .= self::encodeSlot($slots[1]['fixedPass'], $slots[1]['noMail'], $slots[1]['nick'])."\n";
            $out .= $slots[1]['mail']."\n";
            $out .= ($overridePass ?? $game->pass)."\n";
            $out .= $game->moveCount."\n";
            $out .= ($game->moves ?? '')."\n";
            $out .= $game->board->asString();

            $message = $overrideMessage ?? $game->message;
            if ($message !== null) {
                $out .= "\n".$message;
            }

            fwrite($handle, $out);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        @chmod($path, 0600);
    }

    public function delete(GameId $id): void
    {
        @unlink($this->path($id));
    }

    public function rename(GameId $from, GameId $to): bool
    {
        return @rename($this->path($from), $this->path($to));
    }

    public function lastModified(GameId $id): ?int
    {
        $time = @filemtime($this->path($id));

        return $time === false ? null : $time;
    }

    /** @return list<GameId> all game ids currently on disk, in directory order. */
    public function allIds(): array
    {
        $dir = @opendir($this->config->dataPath);
        if ($dir === false) {
            throw new \RuntimeException("Unable to open game directory: {$this->config->dataPath}");
        }

        $ids = [];
        try {
            while (($entry = readdir($dir)) !== false) {
                $id = GameId::tryFromString($entry);
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        } finally {
            closedir($dir);
        }

        return $ids;
    }

    private function path(GameId $id): string
    {
        return $this->config->dataPath.'/'.$id;
    }

    /** @return array{0: bool, 1: bool, 2: string} [hasFixedPass, noMail, fixedPass] */
    private static function parsePrefix(string $line): array
    {
        $colon = strpos($line, ':');
        if ($colon === false) {
            return [false, false, ''];
        }

        $prefix = substr($line, 0, $colon);
        $hasFixedPass = strlen($prefix) > 0;
        $fixedPass = $hasFixedPass ? substr($prefix, 0, 10) : '';

        $after = substr($line, $colon + 1);
        $noMail = str_contains($after, ':') && ($after[0] ?? '') === '1';

        return [$hasFixedPass, $noMail, $fixedPass];
    }

    private static function stripPrefix(string $line): string
    {
        $lastColon = strrpos($line, ':');

        return $lastColon === false ? $line : substr($line, $lastColon + 1);
    }

    private static function encodeSlot(string $fixedPass, bool $noMail, string $nick): string
    {
        if ($fixedPass === '') {
            return $nick;
        }

        return $fixedPass.':'.($noMail ? '1:' : '').$nick;
    }
}
