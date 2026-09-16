<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        $remote = (string) $request->server('REMOTE_ADDR', '');
        $host = strtolower((string) $request->getHost());
        $isLocalHop = $forwardedProto === 'https'
            || in_array($remote, ['127.0.0.1', '::1'], true)
            || in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || str_starts_with($host, '127.0.0.1');

        // Next.js rewrites /laravel/* to http://127.0.0.1:8088 after the public
        // edge already terminated TLS. Always pin absolute URLs to APP_URL so
        // redirects never leak the loopback hop (even when TrustedProxies marks
        // the request secure via X-Forwarded-Proto).
        if ($isLocalHop) {
            $canonical = rtrim((string) config('app.url'), '/');
            if ($canonical !== '') {
                URL::forceRootUrl($canonical);
                URL::forceScheme('https');
            }

            return $next($request);
        }

        if (! $request->secure()) {
            $canonical = rtrim((string) config('app.url'), '/');
            if ($canonical !== '') {
                return redirect()->to($canonical.$request->getRequestUri(), 308);
            }

            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
