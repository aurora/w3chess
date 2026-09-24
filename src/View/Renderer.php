<?php

declare(strict_types=1);

namespace W3Chess\View;

/**
 * Renders a .phtml template with output buffering, the same lightweight
 * approach the original used for its optional HTML_HEADER/HTML_FOOTER
 * override files (readfile()), just applied consistently to every page
 * instead of printf()-ing HTML inline. Templates get an $e() helper for
 * htmlspecialchars() escaping — the original never escaped output at all.
 */
final readonly class Renderer
{
    public function __construct(private string $templateDir)
    {
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = []): string
    {
        $path = $this->templateDir.'/'.$template.'.phtml';

        $render = function (string $__path, array $__vars) {
            extract($__vars, EXTR_SKIP);
            $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            ob_start();
            require $__path;

            return ob_get_clean();
        };

        return $render($path, $vars);
    }
}
