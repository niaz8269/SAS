<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Is this request from a search-engine or other automated client? Used only to save work: a crawler gets no session row and does not count as a
 * visitor in the analytics. It is a plain look at the User-Agent text, so it is never used to decide what a page says or who may see it.
 */
class Crawler
{
    private const PATTERN = '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|pinterest|headless|lighthouse|pagespeed|gtmetrix|uptime|monitor|curl\/|wget\/|python-|go-http-client|okhttp|libwww|httpclient|axios|node-fetch|undici|postmanruntime|ahrefs|semrush|mj12|dotbot|petalbot|yandex|baidu|duckduck/i';

    public static function is(?string $userAgent): bool
    {
        return $userAgent !== null && $userAgent !== '' && preg_match(self::PATTERN, $userAgent) === 1;
    }

    /** A page view by a person: a normal GET, not a HEAD check and not an automated client. */
    public static function isHumanView(Request $request): bool
    {
        return $request->isMethod('GET') && ! self::is($request->userAgent());
    }
}
