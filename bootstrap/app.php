<?php

use App\Http\Middleware\EnsureAgent;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveStudentContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active' => EnsureUserIsActive::class, 'student.context' => ResolveStudentContext::class, 'agent' => EnsureAgent::class]);
        $middleware->preventRequestForgery(except: ['webhooks/*']); // authenticated by HMAC signature instead
        $middleware->web(append: [SecurityHeaders::class, EnsureUserIsActive::class]);
        // Links in emails (password reset, verification) are built from the request host, so only the site's own host names are answered.
        // Local development and the automated tests are left alone by the framework.
        $middleware->trustHosts(at: fn () => array_merge(
            array_filter([parse_url((string) config('app.url'), PHP_URL_HOST)]),
            (array) config('sas.trusted_hosts'),
        ), subdomains: false);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
