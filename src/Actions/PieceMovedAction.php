<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Chess\CheckDetector;
use W3Chess\Chess\Move;
use W3Chess\Chess\MoveList;
use W3Chess\Chess\MoveValidator;
use W3Chess\Game\Game;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Game\PasswordGenerator;
use W3Chess\Http\Request;
use W3Chess\Mail\GameMailBuilder;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/**
 * Ports piecemoved(): validates and applies one move, rotates the move
 * password, swaps next/waiting, and mails both players. Unlike
 * ResumeGameAction's special actions, this writes without reloading
 * afterwards — matching the original, which mutates its next/waiting
 * variables in memory (not via a readgamedata() reload) before mailing, so
 * the "no mail" gating below intentionally uses $game's *original*
 * (pre-move) flags, not the post-move ones.
 */
final readonly class PieceMovedAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private GameRepository $games,
        private GameMailBuilder $mailer,
        private PasswordGenerator $passwords,
        private ResumeGameAction $resumeGameAction,
    ) {
    }

    public function handle(Request $request): string
    {
        $t = $this->presenter->translator;

        // Special actions (resend/remis/giveup/settings/logout) share this form; hand off to ResumeGameAction.
        if ($request->get('SPECIAL') !== null) {
            return $this->resumeGameAction->handleWithChange($request, false);
        }

        $id = GameId::tryFromString($request->get('ID'));
        if ($id === null) {
            return $this->presenter->errorBox($t->get('ERRORMOV'), null, null, 0);
        }

        $game = $this->games->load($id);
        if ($game === null) {
            return $this->presenter->errorBox($t->get('ERRORID'), null, null, 0);
        }

        $rightSwap = ($game->moveCount % 2) === 0 ? 1 : -1;
        $swap = $request->int('SWAP');

        $moveParam = $request->get('MOVE');
        $move4 = $moveParam !== null ? substr($moveParam, 0, 4) : '';
        if (strlen($move4) < 4) {
            return $this->presenter->errorBox($t->get('ERRORMOV'), $game->pass, $id, $swap);
        }

        $pass = $request->get('PASS');
        if ($pass === null) {
            return $this->presenter->errorBox($t->get('ERRORPASS'), null, $id, $swap);
        }
        if ($pass !== $game->pass) {
            if ($move4 === 'CANC') {
                return $this->presenter->backToBoard($t->get('MOVECANC'), null, $id, 0);
            }

            return $this->presenter->errorBox($t->get('ERRORPASS'), null, $id, $swap);
        }
        if ($move4 === 'CANC') {
            return $this->presenter->backToBoard($t->get('MOVECANC'), $game->pass, $id, 0);
        }

        $oldMoveCount = $request->int('GNUMMOVES');
        if ($oldMoveCount > 0 && $oldMoveCount !== $game->moveCount) {
            return $this->presenter->errorBox($t->get('ERROROLDBOARD'), null, $id, $swap);
        }

        [$fromFile, $fromRank, $toFile, $toRank] = str_split($move4);

        $validator = new MoveValidator($game->board, $game->moveCount, $game->moves);
        if (!$validator->canMove(ord($fromFile), ord($fromRank) - 48, ord($toFile) - ord('A'), ord('8') - ord($toRank))) {
            return $this->presenter->errorBox($t->get('ERRORILLEGAL'), null, $id, $swap);
        }

        $changeParam = $request->get('CHANGE');
        $changedPiece = ($changeParam !== null && $changeParam !== '') ? $changeParam[0] : null;

        $fromIndex = (ord('8') - ord($fromRank)) * 8 + (ord($fromFile) - ord('A'));
        $toIndex = (ord('8') - ord($toRank)) * 8 + (ord($toFile) - ord('A'));
        $movingPiece = $game->board->pieceAt($fromIndex % 8, intdiv($fromIndex, 8));

        // A pawn reaching the last rank needs a promotion choice first.
        if ($changedPiece === null && strtoupper($movingPiece) === 'P' && ($toRank === '8' || $toRank === '1')) {
            return $this->resumeGameAction->handleWithChange($request, true);
        }

        $capturedPiece = $game->board->pieceAt($toIndex % 8, intdiv($toIndex, 8));
        $isEnPassant = strtoupper($movingPiece) === 'P' && $capturedPiece === 'e' && $fromFile !== $toFile;
        $resultPiece = $isEnPassant ? 'O' : ($changedPiece ?? $movingPiece);

        $move = new Move($movingPiece, $fromFile, $fromRank, $toFile, $toRank, $resultPiece, $capturedPiece);
        $newBoard = MoveList::applyToLiveBoard($game->board, $move);
        $newMoves = ($game->moves ?? '').$move->encode();
        $newMoveCount = $game->moveCount + 1;

        $newPass = $this->passwords->generate();
        $effectivePass = $game->waitingFixedPass !== '' ? $game->waitingFixedPass : $newPass;

        $messageParam = $request->get('MESSAGE');

        $updated = new Game(
            id: $id,
            nextNick: $game->waitingNick,
            nextMail: $game->waitingMail,
            waitingNick: $game->nextNick,
            waitingMail: $game->nextMail,
            // pass doubles as the fixed-password prefix source when nextHasFixedPass; the
            // rotating line-4 password is written separately via $overridePass below.
            pass: $effectivePass,
            waitingFixedPass: $game->nextHasFixedPass ? substr($game->pass, 0, 10) : '',
            nextHasFixedPass: $game->waitingFixedPass !== '',
            noMailToNext: $game->noMailToWaiting,
            noMailToWaiting: $game->noMailToNext,
            board: $newBoard,
            moves: $newMoves,
            moveCount: $newMoveCount,
            message: $messageParam !== null ? '@'.$messageParam : null,
        );
        $this->games->save($updated, overridePass: $newPass);

        $status = (new CheckDetector($newBoard))->evaluate(new MoveValidator($newBoard, $newMoveCount, $newMoves), $newMoveCount, $effectivePass);

        // Gated by the *original* (pre-move) flags: the original mutates its next/waiting
        // variables in memory without reloading, so gnomail1/gnomail2 stay tied to who they
        // originally described (waiting's own preference now applies to the new "next" mail).
        if (!$game->noMailToWaiting || in_array($status->value, [1, 3, 4], true)) {
            $this->mailer->send($id, $updated->nextMail, $updated->waitingMail, $updated->nextNick, $updated->waitingNick, $effectivePass, $newMoves, $newBoard, $messageParam ?? '', $newMoveCount, $status->value, $updated->waitingNick, -1 * $rightSwap, $updated->nextNick, $updated->waitingNick);
        }
        if (!$game->noMailToNext || in_array($status->value, [1, 3, 4], true)) {
            $this->mailer->send($id, $updated->waitingMail, $updated->waitingMail, $updated->waitingNick, $updated->waitingNick, '', $newMoves, $newBoard, '', $newMoveCount, $status->value, $updated->nextNick, $rightSwap, $updated->nextNick, $updated->waitingNick);
        }

        if ($status->value === 1) {
            // Note: mirrors rewriteData($ID, "[MATE]", null, null, 0) — the original never
            // refreshes $gmessage after writing the move, so the mate-causing move's own
            // MESSAGE is not what ends up persisted here; the game's pre-move message is.
            $mated = new Game(
                id: $id, nextNick: $updated->nextNick, nextMail: $updated->nextMail,
                waitingNick: $updated->waitingNick, waitingMail: $updated->waitingMail,
                pass: '[MATE]', waitingFixedPass: $updated->waitingFixedPass, nextHasFixedPass: $updated->nextHasFixedPass,
                noMailToNext: $updated->noMailToNext, noMailToWaiting: $updated->noMailToWaiting,
                board: $updated->board, moves: $updated->moves, moveCount: $updated->moveCount, message: $game->message,
            );
            $this->games->save($mated);
        }

        return $this->presenter->backToBoard($t->get('MOVEDFINISH'), null, $id, $swap);
    }
}
