<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Http\Request;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/**
 * Ports joingame(): shows the "fill in the missing player" form for an open
 * ("X…") game, which resubmits as ACTION=NEW&JOINID=... (always WHITE=1,
 * matching the original's hardcoded hidden field — newgame() then remaps
 * "next"/"other" from that).
 */
final readonly class JoinGameAction implements ActionInterface
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

        $nextFilled = $game->nextMail !== '';

        return $this->presenter->render('pages/join', [
            'game' => $game,
            'existingNick' => $nextFilled ? $game->nextNick : $game->waitingNick,
            'existingMail' => $nextFilled ? $game->nextMail : $game->waitingMail,
            'existingNickField' => $nextFilled ? 'NICK1' : 'NICK2',
            'existingMailField' => $nextFilled ? 'MAIL1' : 'MAIL2',
            'missingNickField' => $nextFilled ? 'NICK2' : 'NICK1',
            'missingMailField' => $nextFilled ? 'MAIL2' : 'MAIL1',
        ]);
    }
}
