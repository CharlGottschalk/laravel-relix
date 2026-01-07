<?php

namespace CharlGottschalk\LaravelRelix\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRelixUiEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('relix.ui.enabled', true)) {
            abort(404);
        }

        if (config('relix.ui.require_local', true) && ! app()->isLocal()) {
            abort(404);
        }

        return $next($request);
    }
}
