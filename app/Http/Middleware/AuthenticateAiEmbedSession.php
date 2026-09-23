<?php

namespace App\Http\Middleware;

use App\Services\Ai\AiEmbedSessionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAiEmbedSession
{
    public function __construct(
        private readonly AiEmbedSessionService $embedSessions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('ai_embed.enabled', false)) {
            return $this->deny('unavailable', 'Embed AI is not enabled.', 404);
        }

        $tenant = (string) $request->route('tenant', '');
        if ($tenant === '' || ! $this->embedSessions->isEnabledForTenant($tenant)) {
            return $this->deny('forbidden', 'Unknown embed tenant.', 404);
        }

        $token = trim((string) $request->header((string) config('ai_embed.session_header', 'X-JP-AI-Embed-Session'), ''));
        if ($token === '') {
            return $this->deny('unauthorized', 'Embed session token required.', 401);
        }

        $parentOriginHeader = (string) config('ai_embed.parent_origin_header', 'X-JP-AI-Embed-Parent-Origin');
        $parentOrigin = trim((string) $request->header($parentOriginHeader, ''));

        $session = $this->embedSessions->validateToken(
            $tenant,
            $token,
            $parentOrigin !== '' ? $parentOrigin : null
        );

        if ($session === null) {
            return $this->deny('forbidden', 'Invalid or expired embed session.', 403);
        }

        $request->attributes->set('ai_embed_session', $session);
        $request->attributes->set('ai_embed_token', $token);
        $request->attributes->set('ai_embed_tenant', $tenant);

        $this->embedSessions->touchSession($token);

        return $next($request);
    }

    private function deny(string $status, string $message, int $code): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'status' => $status,
            'message' => $message,
        ], $code);
    }
}
