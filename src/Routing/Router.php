<?php

declare(strict_types=1);

namespace W3Chess\Routing;

use W3Chess\Http\Request;

/**
 * Maps the ACTION parameter to an Action, replacing the original's six-deep
 * nested if/else chain of strpos($query, "ACTION=NEW") substring checks.
 * Unlike the original, this matches ACTION by exact value (a substring match
 * could be fooled by e.g. a MESSAGE containing the text "ACTION=NEW") and
 * only offers ADMIN/LIST when config enables them, same as the original
 * gating those branches behind defined('ADMIN')/defined('ENABLELIST').
 */
final readonly class Router
{
    /** @param array<string, ActionInterface> $actions */
    public function __construct(
        private array $actions,
        private ActionInterface $default,
    ) {
    }

    public function dispatch(Request $request): string
    {
        $action = $request->get('ACTION');
        $handler = $action !== null ? ($this->actions[$action] ?? null) : null;

        return ($handler ?? $this->default)->handle($request);
    }
}
