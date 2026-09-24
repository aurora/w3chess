<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Config\AppConfig;
use W3Chess\Game\GameMaintenance;
use W3Chess\Http\Request;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/** Ports start(): the default landing page (new game / resume / send-my-games / open games), and the periodic cleanup sweep. */
final readonly class StartAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private GameMaintenance $maintenance,
        private AppConfig $config,
    ) {
    }

    public function handle(Request $request): string
    {
        if ($this->config->htmlDefpageFile !== null && is_file($this->config->htmlDefpageFile)) {
            $this->maintenance->sweepAndListOpenGames(collectList: false);

            return (string) file_get_contents($this->config->htmlDefpageFile);
        }

        $summary = $this->maintenance->sweepAndListOpenGames(collectList: true);

        return $this->presenter->render('pages/start', [
            'totalActive' => $summary['totalActive'],
            'openGames' => $summary['openGames'],
        ]);
    }
}
