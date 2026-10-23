<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Student management is for partner users of an active recruiting-agent organisation. */
class EnsureAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAgent(), 403);

        return $next($request);
    }
}
