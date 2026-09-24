<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Chess\CheckDetector;
use W3Chess\Chess\MoveValidator;
use W3Chess\Game\GameRepository;
use W3Chess\Game\MailAddressValidator;
use W3Chess\Http\Request;
use W3Chess\Mail\Mailer;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/** Ports sendgames(): mails (or, with ONLYWEB, lists) every game a given address is playing. */
final readonly class SendGamesAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private GameRepository $games,
        private Mailer $mailer,
    ) {
    }

    public function handle(Request $request): string
    {
        $t = $this->presenter->translator;
        $onlyWeb = strcasecmp($request->get('ONLYWEB') ?? '', 'on') === 0;

        $mail = $request->get('MAIL1');
        if ($mail === null) {
            return $this->presenter->errorBox($t->get('ERRORDEFAULT'), null, null, 0);
        }
        if ($mail === '') {
            return $this->presenter->errorBox($t->get('ERRORMISMAIL'), null, null, 0);
        }
        if (MailAddressValidator::validate($mail) !== 0) {
            return $this->presenter->errorBox($t->get('ERRORMAILVALID'), null, null, 0);
        }

        $pipe = null;
        $rows = [];

        foreach ($this->games->allIds() as $id) {
            if ($id->isOpen()) {
                continue;
            }
            $mtime = $this->games->lastModified($id);
            if ($mtime === null) {
                continue;
            }
            $game = $this->games->load($id);
            if ($game === null) {
                continue;
            }

            $matchesNext = strcasecmp($mail, $game->nextMail) === 0;
            $matchesWaiting = !$matchesNext && strcasecmp($mail, $game->waitingMail) === 0;
            if (!$matchesNext && !$matchesWaiting) {
                continue;
            }

            if (!$onlyWeb && $pipe === null) {
                $pipe = $this->mailer->open($mail);
                if ($pipe === false) {
                    return $this->presenter->errorBox($t->get('ERRORPIPE'), null, null, 0);
                }
                $this->mailer->header($pipe, 'Mime-Version', '1.0');
                $this->mailer->header($pipe, 'Content-Type', 'text/plain; charset='.$t->get('CHARSET'));
                $this->mailer->header($pipe, 'Content-Transfer-Encoding', '8bit');
                $this->mailer->header($pipe, 'From', $this->presenter->config->noReplyAddress ?? $mail);
                $this->mailer->header($pipe, 'To', $mail);
                $this->mailer->write($pipe, 'Subject: '.$this->presenter->config->subject."\n\n");
                $this->mailer->write($pipe, $t->get('MAILDEAR').' '.($matchesNext ? $game->nextNick : $game->waitingNick)."\n");
                $this->mailer->write($pipe, $t->get('SENDGAMESPLAY').":\n\n");
            }

            $status = (new CheckDetector($game->board))->evaluate(new MoveValidator($game->board, $game->moveCount, $game->moves), $game->moveCount, $game->pass);
            $finished = in_array($status->value, [1, 3, 4], true);

            if ($onlyWeb) {
                $rows[] = [
                    'id' => (string) $id,
                    'moveCount' => $game->moveCount,
                    'opponentNick' => $matchesNext ? $game->waitingNick : $game->nextNick,
                    'lastAccess' => $this->ctime($mtime),
                    'isYourTurn' => $matchesNext,
                    'finished' => $finished,
                ];
            } elseif ($pipe !== null) {
                $this->mailer->write($pipe, "\n".sprintf($t->get('RESTITLE'), $game->nextNick, $game->waitingNick));
                $this->mailer->write($pipe, "\n".$t->get('RESID').": $id\n");
                $this->mailer->write($pipe, $t->get('ADMIN_TABLE_ACC').': '.$this->ctime($mtime)."\n");
                if ($matchesNext && ($status->value === 0 || $status->value === 2)) {
                    $this->mailer->write($pipe, $t->get('MAILMOVE').' '.$game->pass."\n\n");
                }
                if ($finished) {
                    $this->mailer->write($pipe, $t->get('MAILFINISHED')."\n");
                }
            }
        }

        if ($onlyWeb) {
            return $this->presenter->render('pages/sendgames-list', ['mail' => $mail, 'rows' => $rows]);
        }

        if ($pipe === null) {
            return $this->presenter->errorBox($t->get('SENDGAMENOTFOUND'), null, null, 0);
        }

        $this->mailer->write($pipe, "\n\n".$t->get('MAILBOARD').': http://'.($_SERVER['SERVER_NAME'] ?? '').$this->presenter->scriptName."\n\n");
        $this->mailer->write($pipe, $t->get('SENDGAMESBYE')."\n\n");
        $this->mailer->close($pipe);

        return $this->presenter->backToBoard($t->get('RESREQUESTSENT'), null, null, 0);
    }

    private function ctime(int $timestamp): string
    {
        return sprintf('%s %s %2d %02d:%02d:%02d %4d', date('D', $timestamp), date('M', $timestamp), (int) date('j', $timestamp), (int) date('H', $timestamp), (int) date('i', $timestamp), (int) date('s', $timestamp), (int) date('Y', $timestamp));
    }
}
