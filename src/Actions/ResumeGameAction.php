<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Chess\CheckDetector;
use W3Chess\Chess\MateStatus;
use W3Chess\Chess\MoveValidator;
use W3Chess\Game\Game;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Game\PasswordGenerator;
use W3Chess\Http\Request;
use W3Chess\Lang\Translator;
use W3Chess\Mail\GameMailBuilder;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/**
 * Ports resumegame($ischange): the interactive board page. Handles the
 * REQREMIS accept/decline reply and the SPECIAL actions (resend password,
 * offer remis, give up, open the mail-settings form, logout) inline, same as
 * the original, then renders the two-click move-selection board — or, when
 * $isChange is true, the pawn-promotion piece picker (reached from
 * PieceMovedAction when a pawn lands on the last rank).
 */
final readonly class ResumeGameAction implements ActionInterface
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
        return $this->handleWithChange($request, false);
    }

    public function handleWithChange(Request $request, bool $isChange): string
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
        $redFrames = $request->int('RF') === 1 ? 1 : 0;

        $mate = match (true) {
            $game->pass === '[REMIS]' => 3,
            $game->pass === '[GIVEUP]' => 4,
            default => 0,
        };

        $rightSwap = ($game->moveCount % 2) === 0 ? 1 : -1;
        if ($mate !== 0 && $swap === 0) {
            $swap = $rightSwap;
        }

        $reqRemis = false;
        $pass = '';
        if ($mate === 0) {
            $reqRemis = $game->isRemisRequested();
            if ($reqRemis) {
                $rightSwap *= -1;
            }
            if ($swap !== 1 && $swap !== -1) {
                $swap = $rightSwap;
            }
            $pass = substr($request->get('PASS') ?? '', 0, 10);
        }

        $oldMoveCount = $request->int('GNUMMOVES');
        if ($oldMoveCount > 0 && $oldMoveCount !== $game->moveCount) {
            return $this->presenter->errorBox($t->get('ERROROLDBOARD'), $pass, $id, $swap);
        }

        $message = $request->get('MESSAGE') ?? '';

        $fromX = 0;
        $fromY = 0;
        $toX = 0;
        $toY = 0;
        $moveTo = false;

        if ($mate === 0) {
            $special = $this->handleSpecialActions($request, $game, $t, $pass, $swap, $rightSwap, $message, $reqRemis);
            if ($special !== null) {
                return $special;
            }

            $moveParam = $request->get('MOVE');
            if ($moveParam !== null) {
                $len = strlen($moveParam);
                if ($len > 0) {
                    $fromX = ord($moveParam[0]);
                }
                if ($len > 1) {
                    $fromY = ord($moveParam[1]) - 48;
                    $moveTo = true;
                }
                if ($len > 2) {
                    $toX = ord($moveParam[2]);
                }
                if ($len > 3) {
                    $toY = ord($moveParam[3]) - 48;
                }
            }
        }

        $validator = new MoveValidator($game->board, $game->moveCount, $game->moves);
        $blacksTurn = ($game->moveCount % 2) === 1;

        if ($isChange === false) {
            $mateStatus = (new CheckDetector($game->board))->evaluate($validator, $game->moveCount, $game->pass);
            if ($mateStatus === MateStatus::Mate) {
                $mate = 1;
            }
        }

        return $this->presenter->render('pages/resume', [
            'game' => $game,
            'id' => $id,
            'swap' => $swap,
            'redFrames' => $redFrames,
            'mate' => $mate,
            'reqRemis' => $reqRemis,
            'pass' => $pass,
            'message' => $message,
            'isChange' => $isChange,
            'fromX' => $fromX,
            'fromY' => $fromY,
            'toX' => $toX,
            'toY' => $toY,
            'moveTo' => $moveTo,
            'blacksTurn' => $blacksTurn,
            'validator' => $validator,
            'mateStatus' => $mate === 0 ? null : $mate,
        ]);
    }

    /** Handles REQREMIS and the SPECIAL=... actions; returns the finished page HTML, or null to fall through to the board. */
    private function handleSpecialActions(Request $request, Game $game, Translator $t, string $pass, int $swap, int $rightSwap, string $message, bool $reqRemis): ?string
    {
        $reqRemisParam = $request->get('REQREMIS');
        if ($reqRemisParam !== null) {
            if ($pass !== $game->pass) {
                return $this->presenter->errorBox($t->get('ERRORPASS'), null, $game->id, $swap);
            }

            $truncatedMessage = substr('@'.$message, 0, strlen($message)); // original bug, kept 1:1: drops the message's last character

            if (str_starts_with($reqRemisParam, 'YES')) {
                $updated = $this->withPassAndMessage($game, '[REMIS]', $truncatedMessage);
                $this->games->save($updated, swap: true);
                $updated = $this->games->load($game->id) ?? $updated;
                if (!$updated->noMailToWaiting) {
                    $this->mailer->send($game->id, $updated->waitingMail, $updated->waitingMail, $updated->waitingNick, $updated->waitingNick, '', $updated->moves, $updated->board, '', $updated->moveCount, 4, $updated->nextNick, $rightSwap, $updated->nextNick, $updated->waitingNick);
                }
                if (!$updated->noMailToNext) {
                    $this->mailer->send($game->id, $updated->nextMail, $updated->waitingMail, $updated->nextNick, $updated->waitingNick, '', $updated->moves, $updated->board, $message, $updated->moveCount, 4, $updated->waitingNick, -1 * $rightSwap, $updated->nextNick, $updated->waitingNick);
                }
            } else {
                $newPass = $this->passwords->generate();
                $updated = $this->withPassAndMessage($game, $newPass, $truncatedMessage);
                $this->games->save($updated, swap: true);
                $updated = $this->games->load($game->id) ?? $updated;
                if (!$updated->noMailToWaiting) {
                    $this->mailer->send($game->id, $updated->waitingMail, $updated->waitingMail, $updated->waitingNick, $updated->waitingNick, '', $updated->moves, $updated->board, '', $updated->moveCount, 0, $updated->nextNick, $rightSwap, $updated->nextNick, $updated->waitingNick);
                }
                if (!$updated->noMailToNext) {
                    $this->mailer->send($game->id, $updated->nextMail, $updated->waitingMail, $updated->nextNick, $updated->waitingNick, $updated->pass, $updated->moves, $updated->board, $message, $updated->moveCount, 0, $updated->waitingNick, -1 * $rightSwap, $updated->nextNick, $updated->waitingNick);
                }
            }

            return $this->presenter->backToBoard($t->get('RESREQUESTSENT'), null, $game->id, $swap);
        }

        $special = $request->get('SPECIAL');
        if ($special === null) {
            return null;
        }

        switch ($special) {
            case 'PASS':
                $status = (new CheckDetector($game->board))->evaluate(new MoveValidator($game->board, $game->moveCount, $game->moves), $game->moveCount, $game->pass);
                $this->mailer->send($game->id, $game->nextMail, $game->waitingMail, $game->nextNick, $game->waitingNick, $game->pass, $game->moves, $game->board, '', $game->moveCount, $status->value, $game->waitingNick, $rightSwap, $game->nextNick, $game->waitingNick);

                return $this->presenter->backToBoard($t->get('MAILPASS'), $pass, $game->id, $swap);

            case 'REMIS':
                if ($pass !== $game->pass) {
                    return $this->presenter->errorBox($t->get('ERRORPASS'), null, $game->id, $swap);
                }
                $newPass = $this->passwords->generate();
                $updated = $this->withPassAndMessage($game, $newPass, '[REMIS?]@'.$message);
                $this->games->save($updated, swap: true);
                $updated = $this->games->load($game->id) ?? $updated;
                if (!$updated->noMailToWaiting) {
                    $this->mailer->send($game->id, $updated->waitingMail, $updated->waitingMail, $updated->waitingNick, $updated->waitingNick, '', $updated->moves, $updated->board, '', $updated->moveCount, 3, $updated->nextNick, $rightSwap, $updated->nextNick, $updated->waitingNick);
                }
                if (!$updated->noMailToNext) {
                    $this->mailer->send($game->id, $updated->nextMail, $updated->waitingMail, $updated->nextNick, $updated->waitingNick, $updated->pass, $updated->moves, $updated->board, $message, $updated->moveCount, 3, $updated->waitingNick, -1 * $rightSwap, $updated->nextNick, $updated->waitingNick);
                }

                return $this->presenter->backToBoard($t->get('RESREQUESTSENT'), null, $game->id, $swap);

            case 'GIVEUP':
                if ($pass !== $game->pass) {
                    return $this->presenter->errorBox($t->get('ERRORPASS'), null, $game->id, $swap);
                }
                $finished = new Game(
                    id: $game->id, nextNick: $game->nextNick, nextMail: $game->nextMail,
                    waitingNick: $game->waitingNick, waitingMail: $game->waitingMail,
                    pass: '[GIVEUP]', waitingFixedPass: $game->waitingFixedPass, nextHasFixedPass: $game->nextHasFixedPass,
                    noMailToNext: $game->noMailToNext, noMailToWaiting: $game->noMailToWaiting,
                    board: $game->board, moves: $game->moves, moveCount: $game->moveCount,
                    message: '[GIVEUP]@'.$message,
                );
                $this->games->save($finished);
                if (!$game->noMailToNext) {
                    $this->mailer->send($game->id, $game->nextMail, $game->nextMail, $game->nextNick, $game->nextNick, '', $game->moves, $game->board, '', $game->moveCount, 5, $game->waitingNick, $rightSwap, $game->nextNick, $game->waitingNick);
                }
                if (!$game->noMailToWaiting) {
                    $this->mailer->send($game->id, $game->waitingMail, $game->nextMail, $game->waitingNick, $game->nextNick, '', $game->moves, $game->board, $message, $game->moveCount, 5, $game->nextNick, -1 * $rightSwap, $game->nextNick, $game->waitingNick);
                }

                return $this->presenter->backToBoard($t->get('RESREQUESTSENT'), null, $game->id, 0);

            case 'EMAIL':
                if ($pass !== $game->pass) {
                    return $this->presenter->errorBox($t->get('ERRORPASS'), null, $game->id, $swap);
                }

                return $this->presenter->render('pages/change-mail-form', [
                    'game' => $game,
                    'swap' => $swap,
                ]);

            case 'LOGOUT':
                return $this->presenter->backToBoard($t->get('SPECIALLOGOUT'), null, null, 0);
        }

        return null;
    }

    /**
     * Same next/waiting positions as $game, just a new pass/message — the
     * caller then saves this with save(..., swap: true), which writes the
     * *file* slots in reversed order (mirroring rewriteGameAndSwap()) without
     * this object's own next/waiting meaning changing. Reload afterwards to
     * get the actual post-swap Game (nextNick becomes the old waitingNick,
     * etc.), matching the original's readgamedata() re-read at the end of
     * rewriteData().
     */
    private function withPassAndMessage(Game $game, string $newPass, string $message): Game
    {
        return new Game(
            id: $game->id,
            nextNick: $game->nextNick,
            nextMail: $game->nextMail,
            waitingNick: $game->waitingNick,
            waitingMail: $game->waitingMail,
            pass: $newPass,
            waitingFixedPass: $game->waitingFixedPass,
            nextHasFixedPass: $game->nextHasFixedPass,
            noMailToNext: $game->noMailToNext,
            noMailToWaiting: $game->noMailToWaiting,
            board: $game->board,
            moves: $game->moves,
            moveCount: $game->moveCount,
            message: $message,
        );
    }
}
