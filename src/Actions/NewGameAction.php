<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Chess\Board;
use W3Chess\Game\Game;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Game\MailAddressValidator;
use W3Chess\Game\PasswordGenerator;
use W3Chess\Http\Request;
use W3Chess\Mail\GameMailBuilder;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/**
 * Ports newgame(): creates a game file (or, without a second player's mail,
 * an open "X…" game waiting for one), or completes joining one via JOINID.
 * "next"/"other" always mean "whoever is white (moves first)" / "the other
 * player" — the WHITE radio picks which submitted player becomes "next".
 */
final readonly class NewGameAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private GameRepository $games,
        private GameMailBuilder $mailer,
        private PasswordGenerator $passwords,
    ) {
    }

    public function handle(Request $request): string
    {
        $t = $this->presenter->translator;
        $joinId = GameId::tryFromString($request->get('JOINID'));

        $whiteParam = $request->get('WHITE');
        if ($whiteParam === null || $whiteParam === '') {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), null, null, 0);
        }
        $white = ((int) $whiteParam[0]) === 2 ? 2 : 1;

        if ($white === 1) {
            $nextNick = $request->get('NICK1');
            $otherNick = $request->get('NICK2');
            $nextMail = $request->get('MAIL1');
            $otherMail = $request->get('MAIL2');
            $enemyIsOther = true;
        } else {
            $nextNick = $request->get('NICK2');
            $otherNick = $request->get('NICK1');
            $nextMail = $request->get('MAIL2');
            $otherMail = $request->get('MAIL1');
            $enemyIsOther = false;
        }

        if ($nextNick === null || $otherNick === null || $nextMail === null || $otherMail === null) {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), null, null, 0);
        }

        $message = $request->get('MESSAGE');
        $theme = $this->presenter->config->theme;

        if (strlen($nextNick) > $theme->nickLength || strlen($otherNick) > $theme->nickLength) {
            return $this->presenter->errorBox($t->get('ERRORNICKLONG'), null, null, 0);
        }
        if (str_contains($nextNick, ':') || str_contains($otherNick, ':')) {
            return $this->presenter->errorBox($t->get('ERRORNODP'), null, null, 0);
        }
        if (strlen($nextMail) > $theme->mailLength || strlen($otherMail) > $theme->mailLength) {
            return $this->presenter->errorBox($t->get('ERRORMAILLONG'), null, null, 0);
        }

        $now = getdate();
        $id = GameId::fromString(str_replace(' ', '0', sprintf('%4d%2d%2d%d', $now['year'], $now['mon'], $now['mday'], getmypid())));

        $enemyMail = $enemyIsOther ? $otherMail : $nextMail;
        if ($enemyMail === '') {
            if ($joinId !== null) {
                return $this->presenter->errorBox($t->get('ERRORMISMAIL'), null, null, 0);
            }
            $id = $id->withOpenMarker();
        }

        if ($joinId !== null && !$this->games->rename($joinId, $id)) {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), null, null, 0);
        }

        if ($nextMail !== '' && MailAddressValidator::validate($nextMail) < 0) {
            return $this->presenter->errorBox($t->get('ERRORMAILVALID'), null, null, 0);
        }
        if ($otherMail !== '' && MailAddressValidator::validate($otherMail) < 0) {
            return $this->presenter->errorBox($t->get('ERRORMAILVALID'), null, null, 0);
        }
        if ($enemyIsOther && $nextMail === '') {
            return $this->presenter->errorBox($t->get('ERRORMISMAIL'), null, null, 0);
        }
        if (!$enemyIsOther && $otherMail === '') {
            return $this->presenter->errorBox($t->get('ERRORMISMAIL'), null, null, 0);
        }
        if ($otherMail !== '' && $otherNick === '') {
            return $this->presenter->errorBox($t->get('ERRORMISNICK'), null, null, 0);
        }
        if ($nextMail !== '' && $nextNick === '') {
            return $this->presenter->errorBox($t->get('ERRORMISNICK'), null, null, 0);
        }

        $pass = $this->passwords->generate();
        $board = Board::start();

        $game = new Game(
            id: $id,
            nextNick: $nextNick,
            nextMail: $nextMail,
            waitingNick: $otherNick,
            waitingMail: $otherMail,
            pass: $pass,
            waitingFixedPass: '',
            nextHasFixedPass: false,
            noMailToNext: false,
            noMailToWaiting: false,
            board: $board,
            moves: null,
            moveCount: 0,
            message: $message === null ? null : '@'.$message,
        );

        try {
            $this->games->save($game);
        } catch (\RuntimeException) {
            return $this->presenter->errorBox($t->get('ERRORCREATE'), null, null, 0);
        }

        if ($enemyMail === '') {
            return $this->presenter->backToBoard($t->get('STARTWAIT'), null, null, 0);
        }

        $this->mailer->send($id, $nextMail, $otherMail, $nextNick, $otherNick, $pass, null, $board, $message, 0, 0, $otherNick, 1, $nextNick, $otherNick);
        $this->mailer->send($id, $otherMail, $otherMail, $otherNick, $nextNick, '', null, $board, $message, 0, 0, $nextNick, -1, $nextNick, $otherNick);

        return $this->presenter->backToBoard($t->get('STARTFINISH'), null, $id, 0);
    }
}
