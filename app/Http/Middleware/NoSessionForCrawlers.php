<?php

namespace App\Http\Middleware;

use App\Support\Crawler;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sessions live in the database, which on the shared host is one SQLite file. A search-engine crawler sends no cookie, so every page it fetches
 * would add a session row (a write that locks the file for everyone else; that is what produced "database is locked" server errors).
 * For a crawler reading a page the session is kept in memory only. People are not affected, and nothing that needs a signed-in user is touched
 * (a crawler cannot sign in; only GET and HEAD are handled here).
 */
class NoSessionForCrawlers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($request->isMethod('GET') || $request->isMethod('HEAD')) && Crawler::is($request->userAgent())) {
            config(['session.driver' => 'array']);
        }

        return $next($request);
    }
}
