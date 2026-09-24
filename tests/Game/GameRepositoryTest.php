<?php

declare(strict_types=1);

namespace W3Chess\Tests\Game;

use PHPUnit\Framework\TestCase;
use W3Chess\Chess\Board;
use W3Chess\Config\AppConfig;
use W3Chess\Config\Theme;
use W3Chess\Game\Game;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;

final class GameRepositoryTest extends TestCase
{
    private string $dataDir;
    private GameRepository $repository;

    protected function setUp(): void
    {
        $this->dataDir = sys_get_temp_dir().'/w3chess-test-'.bin2hex(random_bytes(8));
        mkdir($this->dataDir);

        $fixture = dirname(__DIR__, 2).'/games/202609167001';
        if (is_file($fixture)) {
            copy($fixture, $this->dataDir.'/202609167001');
        }

        $config = new AppConfig(
            dataPath: $this->dataDir,
            adminPassFile: $this->dataDir.'/adm.pss',
            imgUrlPrefix: '/figures',
            sendmail: '/usr/sbin/sendmail',
            subject: '[W3Chess]',
            noReplyAddress: null,
            reverseMoveList: true,
            allowRemove: true,
            deleteAfterDays: 30,
            adminEnabled: true,
            enableList: false,
            defaultLocale: 'en',
            availableLocales: ['en'],
            htmlHeaderFile: null,
            htmlFooterFile: null,
            htmlDefpageFile: null,
            theme: Theme::fromArray([
                'max_string' => 256, 'mail_length' => 40, 'nick_length' => 20, 'admin_pass_length' => 15,
                'piece_images' => [], 'empty_image' => 'empty.gif',
                'button_yes' => 'yes.gif', 'button_no' => 'no.gif',
                'arrow_left' => 'l.gif', 'arrow_left_end' => 'll.gif', 'arrow_right' => 'r.gif', 'arrow_right_end' => 'rr.gif',
                'swap_icon_white_top' => 'w.gif', 'swap_icon_black_top' => 'b.gif', 'redframe_icon' => 'rf.gif',
                'color_board_black' => '#000', 'color_board_white' => '#fff', 'color_selected' => '#0f0', 'color_warn' => '#f00',
                'color_board_margin_bg' => '#000', 'color_board_margin_fg' => '#fff', 'color_board_grid' => '#aaa', 'color_message' => '#090',
                'field_size' => 50, 'piece_size' => 40, 'message_box_length' => 70, 'wait_time' => 3,
                'title' => 'Test', 'url' => 'http://example.test/',
            ]),
        );

        $this->repository = new GameRepository($config);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dataDir.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dataDir);
    }

    public function testLoadsTheSampleGameFileFields(): void
    {
        $game = $this->repository->load(GameId::fromString('202609167001'));

        self::assertNotNull($game);
        self::assertSame('Harald', $game->nextNick);
        self::assertSame('harald@test.de', $game->nextMail);
        self::assertSame('Tobi', $game->waitingNick);
        self::assertSame('tobi@test.de', $game->waitingMail);
        self::assertSame('abcdef1234', $game->pass);
        self::assertSame(2, $game->moveCount);
        self::assertSame('Ph2h3peph7h6pe', $game->moves);
        self::assertFalse($game->nextHasFixedPass);
        self::assertSame('', $game->waitingFixedPass);
    }

    public function testSaveThenLoadRoundTripsAllFields(): void
    {
        $id = GameId::fromString('209912319999');
        $game = new Game(
            id: $id,
            nextNick: 'Alice',
            nextMail: 'alice@example.com',
            waitingNick: 'Bob',
            waitingMail: 'bob@example.com',
            pass: 'roundtrip1',
            waitingFixedPass: 'bobsfixed',
            nextHasFixedPass: false,
            noMailToNext: false,
            noMailToWaiting: true,
            board: Board::start(),
            moves: 'Pe2e4pe',
            moveCount: 1,
            message: '@hello there',
        );

        $this->repository->save($game);
        $reloaded = $this->repository->load($id);

        self::assertNotNull($reloaded);
        self::assertSame('Alice', $reloaded->nextNick);
        self::assertSame('alice@example.com', $reloaded->nextMail);
        self::assertSame('Bob', $reloaded->waitingNick);
        self::assertSame('bob@example.com', $reloaded->waitingMail);
        self::assertSame('roundtrip1', $reloaded->pass);
        self::assertSame('bobsfixed', $reloaded->waitingFixedPass);
        self::assertTrue($reloaded->noMailToWaiting);
        self::assertFalse($reloaded->noMailToNext);
        self::assertSame(1, $reloaded->moveCount);
        self::assertSame('Pe2e4pe', $reloaded->moves);
        self::assertSame(Board::start()->asString(), $reloaded->board->asString());
        self::assertSame('hello there', $reloaded->messageText());
    }

    public function testDeleteRemovesTheFile(): void
    {
        $id = GameId::fromString('202609167001');
        self::assertTrue($this->repository->exists($id));

        $this->repository->delete($id);

        self::assertFalse($this->repository->exists($id));
    }
}
