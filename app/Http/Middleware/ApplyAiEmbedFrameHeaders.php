<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiEmbedSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyAiEmbedFrameHeaders
{
    public function __construct(
        private readonly AiEmbedSessionService $embedSessions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('ai_embed_framing', true);

        $response = $next($request);

        $tenant = (string) $request->route('tenant', '');
        $allowed = config("ai_embed.tenants.{$tenant}.allowed_origins", []);
        $directives = [];

        if (is_array($allowed)) {
            foreach ($allowed as $origin) {
                $normalized = $this->embedSessions->normalizeOrigin((string) $origin);
                if ($normalized !== null) {
                    $directives[] = $normalized;
                }
            }
        }

        if ($directives === []) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        } else {
            $response->headers->set(
                'Content-Security-Policy',
                'frame-ancestors '.implode(' ', $directives)
            );
        }

        $response->headers->remove('X-Frame-Options');

        return $response;
    }
}
