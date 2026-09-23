<?php

namespace App\Http\Middleware;

use App\Models\AiEmbedTenant;
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
        $pathToken = (string) $request->route('pathToken', '');
        $tenant = $this->embedSessions->resolveTenantByEmbedKey($pathToken);
        if ($tenant === null) {
            abort(404);
        }

        $request->attributes->set('ai_embed_framing', true);
        $request->attributes->set('ai_embed_tenant_model', $tenant);

        $response = $next($request);

        $directives = [];
        foreach ($tenant->normalizedAllowedOrigins() as $origin) {
            $normalized = $this->embedSessions->normalizeOrigin($origin);
            if ($normalized !== null) {
                $directives[] = $normalized;
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
