<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Game\Game;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Game\MailAddressValidator;
use W3Chess\Http\Request;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/**
 * Ports changemail(): the "settings" form (SPECIAL=EMAIL in ResumeGameAction)
 * lets the current next-to-move player update their mail address and set (or
 * clear) a fixed password. The original writes this in two rewriteData()
 * calls; here both changes are folded into a single save() with the same
 * end state, since nothing ever observes the intermediate write.
 */
final readonly class ChangeMailAction implements ActionInterface
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

        $swap = $request->int('SWAP');

        $pass = $request->get('PASS');
        if ($pass === null || $pass !== $game->pass) {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), null, $id, $swap);
        }

        $mail = $request->get('MAIL1');
        if ($mail === null) {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), $game->pass, $id, $swap);
        }
        if (MailAddressValidator::validate($mail) !== 0) {
            return $this->presenter->errorBox($t->get('ERRORMAILVALID'), $game->pass, $id, $swap);
        }

        $nextHasFixedPass = $game->nextHasFixedPass;
        $noMailToNext = $game->noMailToNext;
        $newPass = $game->pass;

        $fixPass = $request->get('FIXPASS');
        if ($fixPass !== null) {
            if (strlen($fixPass) > 10) {
                return $this->presenter->errorBox($t->get('ERRORPASSLONG'), $game->pass, $id, $swap);
            }

            if ($fixPass === '') {
                $nextHasFixedPass = false;
                $noMailToNext = false;
            } else {
                $fixPassRetyped = $request->get('FIXPASSA');
                if ($fixPassRetyped !== null) {
                    if ($fixPass !== $fixPassRetyped) {
                        return $this->presenter->errorBox($t->get('ERRORPASSMATCH'), $game->pass, $id, $swap);
                    }
                    $newPass = substr($fixPass, 0, 10);
                    $nextHasFixedPass = true;
                }

                $noMailToNext = strcasecmp($request->get('NOMAILTOME') ?? '', 'on') === 0;
            }
        }

        $updated = new Game(
            id: $game->id,
            nextNick: $game->nextNick,
            nextMail: $mail,
            waitingNick: $game->waitingNick,
            waitingMail: $game->waitingMail,
            pass: $newPass,
            waitingFixedPass: $game->waitingFixedPass,
            nextHasFixedPass: $nextHasFixedPass,
            noMailToNext: $noMailToNext,
            noMailToWaiting: $game->noMailToWaiting,
            board: $game->board,
            moves: $game->moves,
            moveCount: $game->moveCount,
            message: $game->message,
        );
        $this->games->save($updated);

        return $this->presenter->backToBoard($t->get('SPECIALSETTINGSCHANGED'), $updated->pass, $id, $swap);
    }
}
