<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\NoCache;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionSecurity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
            'admin' => AdminMiddleware::class,
            'nocache' => NoCache::class,
        ]);
        $middleware->trustHosts(
            at: static fn (): array => (array) config('trustedhosts.hosts', []),
            subdomains: false,
        );

        $middleware->web(append: [
            LocaleMiddleware::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(SessionSecurity::class);
        $middleware->validateCsrfTokens(except: [
            'callback/*',
            'webhook/*',
            'postback/*',
            // Legacy in-game pages (IE7) POST these three without a CSRF
            'in-game/battlepass/claim',
            'in-game/battlepass/purchase-premium',
            'in-game/battlepass/purchase-points',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
