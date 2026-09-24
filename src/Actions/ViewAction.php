<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Chess\MoveList;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Http\Request;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/** Ports view(): a read-only historical board at move $number (no password required). */
final readonly class ViewAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private GameRepository $games,
    ) {
    }

    public function handle(Request $request): string
    {
        $t = $this->presenter->translator;
        $id = GameId::tryFromString($request->get('ID'));
        if ($id === null) {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), null, null, 0);
        }

        $game = $this->games->load($id);
        if ($game === null) {
            return $this->presenter->errorBox($t->get('ERRORID'), null, null, 0);
        }

        $number = $request->int('NUM', -1);
        if ($number < 0) {
            $number = $request->imageButtonNumber('NUM') ?? 0;
        }
        $number = max(0, $number);

        $swap = $request->int('SWAP', 1);
        if ($swap !== 1 && $swap !== -1) {
            $swap = 1;
        }

        $board = MoveList::replayForView($game->moves, $number);
        $list = MoveList::parse($game->moves);
        $nextMove = $list[$number] ?? null;

        return $this->presenter->render('pages/view', [
            'game' => $game,
            'id' => $id,
            'number' => $number,
            'swap' => $swap,
            'board' => $board,
            'nextMove' => $nextMove,
        ]);
    }
}
