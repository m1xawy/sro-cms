<?php

/*
|--------------------------------------------------------------------------
| Trusted Hosts (optional)
|--------------------------------------------------------------------------
|
| Comma-separated host names that are allowed in the Host header, e.g.
| "example.com,www.example.com,203.0.113.10". Wildcard subdomains can be
| written as "*.example.com".
|
| Leave empty (the default) to skip Host validation, which is required for
| setups that must answer on localhost, raw IPs and several domains at once.
| When enabled, any other Host header is rejected with HTTP 400, preventing
| host-header poisoning of generated URLs (password reset links, redirects).
|
*/

$hosts = array_filter(array_map(static function (string $host): string {
    $host = strtolower(trim($host));

    // "*.example.com" => ".example.com" (Symfony host pattern for subdomains).
    return preg_replace('/^\*\./', '.', $host);
}, explode(',', (string) env('TRUSTED_HOSTS', ''))));

return [
    'hosts' => array_values(array_unique($hosts)),
];
