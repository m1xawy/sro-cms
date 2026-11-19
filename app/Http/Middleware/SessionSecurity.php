<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the session cookie Secure only when the current request is HTTPS.
 *
 * This runs after TrustProxies (so reverse-proxy / Cloudflare headers are
 * already trusted) and before StartSession, which keeps http://localhost,
 * plain http://IP and https://domain all working from the same install.
 */
class SessionSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('session.secure') === null) {
            config(['session.secure' => $request->isSecure()]);
        }

        return $next($request);
    }
}
