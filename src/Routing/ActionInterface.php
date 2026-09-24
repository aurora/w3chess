<?php

declare(strict_types=1);

namespace W3Chess\Routing;

use W3Chess\Http\Request;

interface ActionInterface
{
    /** Returns the page body HTML (the front controller wraps it in the page header/footer). */
    public function handle(Request $request): string;
}
