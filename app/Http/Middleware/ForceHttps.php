<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->secure()) {
            // Next.js rewrites /laravel/* to http://127.0.0.1:8088 after the public
            // edge already terminated TLS. Treat that local hop as HTTPS.
            $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
            $remote = (string) $request->server('REMOTE_ADDR', '');
            if ($forwardedProto === 'https' || in_array($remote, ['127.0.0.1', '::1'], true)) {
                // Keep generated absolute URLs on the public canonical host.
                $canonical = rtrim((string) config('app.url'), '/');
                if ($canonical !== '') {
                    \Illuminate\Support\Facades\URL::forceRootUrl($canonical);
                    \Illuminate\Support\Facades\URL::forceScheme('https');
                }

                return $next($request);
            }

            $canonical = rtrim((string) config('app.url'), '/');
            if ($canonical !== '') {
                return redirect()->to($canonical.$request->getRequestUri(), 308);
            }

            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
