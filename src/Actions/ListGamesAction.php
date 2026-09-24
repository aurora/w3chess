<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Game\GameRepository;
use W3Chess\Http\Request;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/** Ports listgames(): used directly for the public ACTION=LIST, and by AdminAction for the full admin table. */
final readonly class ListGamesAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private GameRepository $games,
    ) {
    }

    public function handle(Request $request): string
    {
        return $this->render(onlyList: true);
    }

    public function render(bool $onlyList): string
    {
        $rows = [];
        foreach ($this->games->allIds() as $id) {
            $mtime = $this->games->lastModified($id);
            if ($mtime === null) {
                continue;
            }
            $game = $this->games->load($id);
            if ($game === null) {
                continue;
            }

            $rows[] = [
                'id' => (string) $id,
                'isOpen' => $id->isOpen(),
                'moveCount' => $game->moveCount,
                'nick1' => $game->nextNick,
                'mail1' => $game->nextMail,
                'nick2' => $game->waitingNick,
                'mail2' => $game->waitingMail,
                'lastAccess' => $this->ctime($mtime),
                'pass' => $game->pass,
            ];
        }

        return $this->presenter->render('pages/games-list', ['onlyList' => $onlyList, 'rows' => $rows]);
    }

    private function ctime(int $timestamp): string
    {
        return sprintf('%s %s %2d %02d:%02d:%02d %4d', date('D', $timestamp), date('M', $timestamp), (int) date('j', $timestamp), (int) date('H', $timestamp), (int) date('i', $timestamp), (int) date('s', $timestamp), (int) date('Y', $timestamp));
    }
}
