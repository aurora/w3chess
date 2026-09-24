<?php

declare(strict_types=1);

namespace W3Chess\Game;

use W3Chess\Config\AppConfig;

/** Ports deleteoldgames(): purges expired games and, optionally, summarises open ("X…") games for the start page. */
final readonly class GameMaintenance
{
    public function __construct(
        private GameRepository $games,
        private AppConfig $config,
    ) {
    }

    /**
     * @return array{totalActive: int, openGames: list<array{id: GameId, nick: string, message: ?string}>}
     */
    public function sweepAndListOpenGames(bool $collectList): array
    {
        $totalActive = 0;
        $openGames = [];

        foreach ($this->games->allIds() as $id) {
            $mtime = $this->games->lastModified($id);
            if ($mtime === null) {
                continue;
            }

            $daysOld = intdiv(time() - $mtime, 86400);
            if ($this->config->allowRemove && $daysOld >= $this->config->deleteAfterDays) {
                $this->games->delete($id);

                continue;
            }

            if (!$collectList) {
                continue;
            }
            $totalActive++;

            if ($id->isOpen()) {
                $game = $this->games->load($id);
                if ($game !== null) {
                    $openGames[] = [
                        'id' => $id,
                        'nick' => $game->nextNick !== '' ? $game->nextNick : $game->waitingNick,
                        'message' => $game->messageText(),
                    ];
                }
            }
        }

        return ['totalActive' => $totalActive, 'openGames' => $openGames];
    }
}
