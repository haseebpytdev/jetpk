<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->attributes->get('ai_embed_framing', false)) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        // Preserve stricter guest-access no-referrer when controllers already set it (OPS-DEF-0011).
        if ($response->headers->get('Referrer-Policy') !== 'no-referrer') {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        return $response;
    }
}
