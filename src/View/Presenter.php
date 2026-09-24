<?php

declare(strict_types=1);

namespace W3Chess\View;

use W3Chess\Chess\PieceCatalog;
use W3Chess\Config\AppConfig;
use W3Chess\Game\GameId;
use W3Chess\Lang\Translator;

/**
 * Shared page chrome and small recurring widgets (the framed message boxes
 * used by errorwin()/back2board() throughout the original), so every Action
 * doesn't have to rebuild them.
 */
final readonly class Presenter
{
    public function __construct(
        public AppConfig $config,
        public Translator $translator,
        private Renderer $renderer,
        public PieceCatalog $pieces,
        public string $scriptName,
    ) {
    }

    public function pageHeader(): string
    {
        $html = ($this->config->htmlHeaderFile !== null && is_file($this->config->htmlHeaderFile))
            ? (string) file_get_contents($this->config->htmlHeaderFile)
            : $this->renderer->render('layout/default-header', ['config' => $this->config]);

        return $html."<!-- ".htmlspecialchars($this->config->theme->title, ENT_QUOTES, 'UTF-8')." -->\n";
    }

    public function pageFooter(): string
    {
        $html = "<!-- ".htmlspecialchars($this->config->theme->title, ENT_QUOTES, 'UTF-8')." -->\n";

        $html .= ($this->config->htmlFooterFile !== null && is_file($this->config->htmlFooterFile))
            ? (string) file_get_contents($this->config->htmlFooterFile)
            : $this->renderer->render('layout/default-footer', ['config' => $this->config, 'scriptName' => $this->scriptName]);

        return $html;
    }

    public function errorBox(string $translatedMessage, ?string $pass, GameId|string|null $id, int $swap = 0): string
    {
        return $this->renderer->render('partials/error-box', [
            'config' => $this->config,
            'message' => $translatedMessage,
            'scriptName' => $this->scriptName,
            'pass' => $pass,
            'id' => $id === null ? null : (string) $id,
            'swap' => $swap,
            'msgOk' => $this->translator->get('MSGOK'),
        ]);
    }

    public function backToBoard(string $translatedMessage, ?string $pass, GameId|string|null $id, int $swap = 0): string
    {
        return $this->renderer->render('partials/back-to-board', [
            'config' => $this->config,
            'message' => $translatedMessage,
            'scriptName' => $this->scriptName,
            'pass' => $pass,
            'id' => $id === null ? null : (string) $id,
            'swap' => $swap,
            'waitTime' => $this->config->theme->waitTime,
            'msgOk' => $this->translator->get('MSGOK'),
        ]);
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = []): string
    {
        return $this->renderer->render($template, $vars + [
            'config' => $this->config,
            't' => $this->translator,
            'pieces' => $this->pieces,
            'scriptName' => $this->scriptName,
        ]);
    }
}
