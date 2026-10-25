<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\LocaleMiddleware;
use App\Http\Middleware\NoCache;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
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
        $middleware->web(append: [
            LocaleMiddleware::class,
            TrustProxies::class,
        ]);
        $middleware->append(SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: [
            '/callback/*',
            '/webhook/*',
            '/postback/*',
            '/game/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
