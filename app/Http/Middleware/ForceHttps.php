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
            $canonical = rtrim((string) config('app.url'), '/');
            if ($canonical !== '') {
                return redirect()->to($canonical.$request->getRequestUri(), 308);
            }

            return redirect()->secure($request->getRequestUri(), 308);
        }

        return $next($request);
    }
}
