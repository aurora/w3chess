<?php

declare(strict_types=1);

namespace W3Chess\Mail;

use W3Chess\Chess\Board;
use W3Chess\Chess\Move;
use W3Chess\Chess\MoveList;
use W3Chess\Chess\PieceCatalog;
use W3Chess\Config\AppConfig;
use W3Chess\Game\GameId;
use W3Chess\Lang\Translator;

/**
 * Composes and sends one game-status mail, porting w3mail() (and the
 * plain-text branch of movesout()) line for line: same headers, same ASCII
 * board art, same move list, same trailing "[MOVES:...]" / "[BOARD:...]"
 * block. $mate uses the original's ad-hoc range (not just MateStatus's 0-4):
 * 0=normal, 1=mate, 2=check, 3=remis, 4=giveup (from CheckDetector::evaluate),
 * plus the literal 5 used only for "your opponent gave up" notifications.
 */
final readonly class GameMailBuilder
{
    public function __construct(
        private AppConfig $config,
        private Translator $translator,
        private Mailer $mailer,
        private PieceCatalog $pieces,
    ) {
    }

    public function send(
        GameId $id,
        string $to,
        string $from,
        string $toNick,
        string $fromNick,
        string $pass,
        ?string $moves,
        Board $board,
        ?string $message,
        int $moveCount,
        int $mate,
        string $against,
        int $swap,
        /** Current nick1/nick2 (whichever slots they occupy right now), for the move list's "(by whom)" label. */
        string $nick1,
        string $nick2,
    ): void {
        if ($to === '') {
            return;
        }

        $pipe = $this->mailer->open($to);
        if ($pipe === false) {
            return; // sendmail unavailable — skip this mail rather than fail the whole request
        }

        try {
            $this->mailer->header($pipe, 'Mime-Version', '1.0');
            $this->mailer->header($pipe, 'Content-Type', 'text/plain; charset='.$this->translator->get('CHARSET'));
            $this->mailer->header($pipe, 'Content-Transfer-Encoding', '8bit');
            $this->mailer->header($pipe, 'From', $from);
            $this->mailer->header($pipe, 'To', $to);
            $this->mailer->write($pipe, 'Subject: '.$this->config->subject.' '.$this->translator->get('MAILAGAINST')." $against ($id)\n\n");

            $this->mailer->write($pipe, $this->translator->get('MAILDEAR')." $toNick\n\n");

            if ($moveCount === 0) {
                $this->mailer->write($pipe, $this->translator->get('MAILINVITE')."\n");
            }

            $this->mailer->write($pipe, $this->translator->get('MAILID').": $id\n");
            $this->mailer->write($pipe, $this->translator->get('MAILBOARD').': http://'.($_SERVER['SERVER_NAME'] ?? '').($_SERVER['SCRIPT_NAME'] ?? '')."\n\n");

            $scriptUrl = 'http://'.($_SERVER['SERVER_NAME'] ?? '').($_SERVER['SCRIPT_NAME'] ?? '');

            if ($pass !== '') {
                match ($mate) {
                    1 => $this->mailer->write($pipe, $this->translator->get('MATE').", $toNick!\n"),
                    2 => $this->mailer->write($pipe, $this->translator->get('CHECK').", $toNick!\n"),
                    default => null,
                };
                if ($mate === 3) {
                    $this->mailer->write($pipe, $this->translator->get('MAILREMIS')."\n");
                }
                if ($mate !== 1) {
                    $this->mailer->write($pipe, $this->translator->get('MAILMOVE').": $pass\n\n");
                    $this->mailer->write($pipe, $this->translator->get('MAILURLRESUME')."\n");
                    $this->mailer->write($pipe, "$scriptUrl?ACTION=RESUME&PASS=$pass&ID=$id\n");
                } else {
                    $this->mailer->write($pipe, $this->translator->get('MAILURLVIEW')."\n");
                    $this->mailer->write($pipe, "$scriptUrl?ACTION=RESUME&ID=$id\n");
                }
                if ($message !== null && $message !== '') {
                    $this->mailer->write($pipe, "\n".$this->translator->get('RESMESSAGE').":\n$message\n");
                }
            } else {
                if ($moveCount > 0) {
                    $this->mailer->write($pipe, $this->translator->get('MAILDONE')."\n");
                }
                match ($mate) {
                    1 => $this->mailer->write($pipe, $this->translator->get('MAILWINNER')."\n"),
                    2 => $this->mailer->write($pipe, $this->translator->get('MAILOPCHECK')."\n"),
                    3 => $this->mailer->write($pipe, $this->translator->get('MAILREMIS')."\n"),
                    4 => $this->mailer->write($pipe, $this->translator->get('MAILISREMIS')."\n"),
                    5 => $this->mailer->write($pipe, $this->translator->get('MAILGIVESUP', $fromNick)."\n"),
                    default => null,
                };
                $this->mailer->write($pipe, $this->translator->get('MAILURLVIEW')."\n");
                $this->mailer->write($pipe, "$scriptUrl?ACTION=RESUME&ID=$id\n");
            }

            $this->writeBoardArt($pipe, $board, $swap);

            $this->mailer->write($pipe, $swap === -1
                ? '('.$this->translator->get('MAILYOUBLACK').")\n\n"
                : '('.$this->translator->get('MAILYOUWHITE').")\n\n");

            $this->writeMoveList($pipe, $moves, $moveCount, $nick1, $nick2);

            if ($moveCount > 0) {
                $this->mailer->write($pipe, "\n\n[MOVES:$moves]\n[BOARD:{$board->asString()}]\n");
            }
        } finally {
            $this->mailer->close($pipe);
        }
    }

    /** @param resource $pipe */
    private function writeBoardArt($pipe, Board $board, int $swap): void
    {
        $swap = ($swap === 1 || $swap === -1) ? $swap : 1;

        $this->mailer->write($pipe, $swap === 1
            ? "\n   # A | B | C | D | E | F | G | H #\n"
            : "\n   # H | G | F | E | D | C | B | A #\n");
        $this->mailer->write($pipe, "###+###+###+###+###+###+###+###+###+###\n");

        for ($i1 = $swap === 1 ? 0 : 7; $swap === 1 ? $i1 <= 7 : $i1 >= 0; $i1 += $swap) {
            if (($i1 > 0 && $swap === 1) || ($swap === -1 && $i1 < 7)) {
                $this->mailer->write($pipe, "---#---+---+---+---+---+---+---+---#---\n");
            }
            $this->mailer->write($pipe, sprintf(' %d #', 8 - $i1));
            for ($i2 = $swap === 1 ? 0 : 7; $swap === 1 ? $i2 <= 7 : $i2 >= 0; $i2 += $swap) {
                $piece = $board->pieceAt($i2, $i1);
                $this->mailer->write($pipe, $piece === 'e' ? '   ' : " $piece ");
                $this->mailer->write($pipe, (($swap === -1 && $i2 > 0) || ($swap === 1 && $i2 < 7)) ? '|' : '#');
            }
            $this->mailer->write($pipe, sprintf(' %d'."\n", 8 - $i1));
        }
        $this->mailer->write($pipe, "###+###+###+###+###+###+###+###+###+###\n");
        $this->mailer->write($pipe, $swap === 1
            ? "   # A | B | C | D | E | F | G | H #\n"
            : "   # H | G | F | E | D | C | B | A #\n");
    }

    /** @param resource $pipe */
    private function writeMoveList($pipe, ?string $moves, int $moveCount, string $nick1, string $nick2): void
    {
        if ($moveCount === 0 || $moves === null) {
            return;
        }

        $this->mailer->write($pipe, $this->translator->get('RESMOVES')."\n");

        $list = MoveList::parse($moves);
        $order = $this->config->reverseMoveList ? range($moveCount, 1, -1) : range(1, $moveCount);

        foreach ($order as $number) {
            $move = $list[$number - 1];
            // $moveCount is the *current* total move count (as when movesout() reads the
            // ambient $gnummoves), matching the original's "who moved" heuristic exactly.
            $movedBy = (($moveCount + $number) % 2) === 0 ? $nick2 : $nick1;

            $movingIcon = $this->pieces->describe($move->movingPiece);
            $this->mailer->write($pipe, sprintf('%d) %s: %s%s -> %s%s', $number, $movingIcon?->longName ?? '', $move->fromFile, $move->fromRank, $move->toFile, $move->toRank));

            if ($move->capturedPiece !== 'e' || $move->isEnPassant()) {
                $capturedChar = match (true) {
                    $move->isEnPassant() && $move->movingPiece === 'P' => 'p',
                    $move->isEnPassant() && $move->movingPiece === 'p' => 'P',
                    default => $move->capturedPiece,
                };
                $capturedIcon = $this->pieces->describe($capturedChar);
                $this->mailer->write($pipe, ' ['.$this->translator->get('MAILHIT').' '.($capturedIcon?->longName ?? '').']');
            }

            if ($move->movingPiece !== $move->resultPiece && !$move->isEnPassant()) {
                $resultIcon = $this->pieces->describe($move->resultPiece);
                $this->mailer->write($pipe, ' -> '.($resultIcon?->longName ?? ''));
            }

            // Note: the original compares against "H5H7"/"A5A7"/"H5H3"/"A5A3" here, not
            // the real castle squares (E8G8 etc, as the HTML move list uses) — a
            // pre-existing mismatch kept as-is, so the mail move list never actually
            // labels a castling move as such.
            $this->mailer->write($pipe, match ($move->squares()) {
                'H5H7', 'A5A7' => ' ('.$this->translator->get('SMCKS').')',
                'H5H3', 'A5A3' => ' ('.$this->translator->get('SMCQS').')',
                default => '',
            });
            if ($move->isEnPassant()) {
                $this->mailer->write($pipe, ' ('.$this->translator->get('SMEP').')');
            }

            $this->mailer->write($pipe, ' ('.$movedBy.")\n");
        }
    }
}
