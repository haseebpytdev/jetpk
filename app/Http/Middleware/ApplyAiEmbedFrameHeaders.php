<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiEmbedSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyAiEmbedFrameHeaders
{
    private const TENANT = 'jetpakistan';

    public function __construct(
        private readonly AiEmbedSessionService $embedSessions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $pathToken = (string) $request->route('pathToken', '');
        if (! $this->embedSessions->matchesEntryPath(self::TENANT, $pathToken)) {
            abort(404);
        }

        $request->attributes->set('ai_embed_framing', true);

        $response = $next($request);

        $allowed = config('ai_embed.tenants.'.self::TENANT.'.allowed_origins', []);
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
