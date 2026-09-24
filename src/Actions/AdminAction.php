<?php

declare(strict_types=1);

namespace W3Chess\Actions;

use W3Chess\Admin\AdminAuth;
use W3Chess\Game\GameId;
use W3Chess\Game\GameRepository;
use W3Chess\Http\Request;
use W3Chess\Routing\ActionInterface;
use W3Chess\View\Presenter;

/** Ports admin() + saveAdminPass(): first-run password setup, login, password change, and the games table (with delete). */
final readonly class AdminAction implements ActionInterface
{
    public function __construct(
        private Presenter $presenter,
        private AdminAuth $auth,
        private GameRepository $games,
        private ListGamesAction $listGames,
    ) {
    }

    public function handle(Request $request): string
    {
        $t = $this->presenter->translator;
        $theme = $this->presenter->config->theme;

        $pass1 = substr($request->get('PASS1') ?? '', 0, $theme->adminPassLength);
        $pass2 = substr($request->get('PASS2') ?? '', 0, $theme->adminPassLength);
        $pass3 = substr($request->get('PASS3') ?? '', 0, $theme->adminPassLength);

        if (!$this->auth->isConfigured()) {
            if ($pass1 !== '' && $pass1 === $pass2) {
                $this->auth->save($pass1);

                return $this->presenter->render('pages/admin-login', ['wrongPassword' => false]);
            }

            return $this->presenter->render('pages/admin-setup', ['passwordsDiffer' => $pass1 !== '' && $pass1 !== $pass2]);
        }

        if ($pass3 === '') {
            return $this->presenter->render('pages/admin-login', ['wrongPassword' => false]);
        }

        if (!$this->auth->verify($pass3)) {
            return $this->presenter->render('pages/admin-login', ['wrongPassword' => true]);
        }

        $passwordsDiffer = false;
        if ($pass1 !== '') {
            if ($pass1 === $pass2) {
                $this->auth->save($pass1);
            } else {
                $passwordsDiffer = true;
            }
        }

        $delete = $request->get('DELETE');
        if ($delete !== null && $this->presenter->config->allowRemove) {
            $id = GameId::tryFromString($delete);
            if ($id !== null) {
                $this->games->delete($id);
            }
        }

        return $this->presenter->render('pages/admin-panel', [
            'passwordsDiffer' => $passwordsDiffer,
            'gamesListHtml' => $this->listGames->render(onlyList: false),
        ]);
    }
}
