<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies
|--------------------------------------------------------------------------
|
| Laravel's built-in TrustProxies middleware reads this value. Set it to your
| reverse proxy / load balancer / Cloudflare IPs (comma separated), or "*"
| only when the origin can be reached exclusively through that proxy.
|
| Leave it empty (or "none") to trust no proxy and use the direct connection.
| See https://www.cloudflare.com/ips/
|
*/

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => ($proxies === '' || strtolower($proxies) === 'none') ? null : $proxies,
];
