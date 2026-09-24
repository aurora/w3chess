<?php

declare(strict_types=1);

use W3Chess\Actions\AdminAction;
use W3Chess\Actions\ChangeMailAction;
use W3Chess\Actions\JoinGameAction;
use W3Chess\Actions\ListGamesAction;
use W3Chess\Actions\NewGameAction;
use W3Chess\Actions\PieceMovedAction;
use W3Chess\Actions\ResumeGameAction;
use W3Chess\Actions\SendGamesAction;
use W3Chess\Actions\StartAction;
use W3Chess\Actions\ViewAction;
use W3Chess\Admin\AdminAuth;
use W3Chess\Chess\PieceCatalog;
use W3Chess\Config\AppConfig;
use W3Chess\Game\GameMaintenance;
use W3Chess\Game\GameRepository;
use W3Chess\Game\PasswordGenerator;
use W3Chess\Http\Request;
use W3Chess\Lang\Translator;
use W3Chess\Mail\GameMailBuilder;
use W3Chess\Mail\Mailer;
use W3Chess\Routing\Router;
use W3Chess\View\Presenter;
use W3Chess\View\Renderer;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);

// Don't let PHP diagnostics corrupt the HTML output.
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
}

header('Content-Type: text/html; charset=UTF-8');

$config = AppConfig::fromFiles($root.'/config/config.php', $root.'/config/defaults.php');

$request = Request::fromGlobals();
$locale = Translator::negotiateLocale($request->acceptLanguage(), $config->defaultLocale, $config->availableLocales);
$translator = Translator::forLocale($root.'/config/lang', $locale);

$renderer = new Renderer($root.'/templates');
$pieces = new PieceCatalog($config, $translator);
$presenter = new Presenter($config, $translator, $renderer, $pieces, $request->scriptName);

$games = new GameRepository($config);
$mailer = new Mailer($config);
$gameMailer = new GameMailBuilder($config, $translator, $mailer, $pieces);
$passwords = new PasswordGenerator();
$maintenance = new GameMaintenance($games, $config);
$adminAuth = new AdminAuth($config);

$resumeGame = new ResumeGameAction($presenter, $games, $gameMailer, $passwords);
$listGames = new ListGamesAction($presenter, $games);

$actions = [
    'NEW' => new NewGameAction($presenter, $games, $gameMailer, $passwords),
    'RESUME' => $resumeGame,
    'MOVED' => new PieceMovedAction($presenter, $games, $gameMailer, $passwords, $resumeGame),
    'CHMAIL' => new ChangeMailAction($presenter, $games),
    'JOIN' => new JoinGameAction($presenter, $games),
    'SENDGAMES' => new SendGamesAction($presenter, $games, $mailer),
    'VIEW' => new ViewAction($presenter, $games),
];
if ($config->adminEnabled) {
    $actions['ADMIN'] = new AdminAction($presenter, $adminAuth, $games, $listGames);
}
if ($config->enableList) {
    $actions['LIST'] = $listGames;
}

$default = new StartAction($presenter, $maintenance, $config);
$router = new Router($actions, $default);

echo $presenter->pageHeader();
try {
    echo $router->dispatch($request);
} catch (\Throwable $e) {
    // Never let an unexpected error produce a blank page (display_errors is off above) —
    // show a translated error box and log the real cause for diagnosis.
    error_log('[W3Chess] '.$e);
    echo $presenter->errorBox($translator->get('ERRORDEFAULT'), null, null, 0);
}
echo $presenter->pageFooter();
