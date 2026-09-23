<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiEmbedSessionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiEmbedPageController extends Controller
{
    public function __construct(
        private readonly AiEmbedSessionService $embedSessions,
    ) {}

    public function show(Request $request, string $tenant): Response
    {
        if ($tenant !== 'jetpakistan') {
            abort(404);
        }

        if (! $this->embedSessions->isEnabledForTenant($tenant)) {
            abort(404);
        }

        $tenantConfig = config("ai_embed.tenants.{$tenant}", []);

        return response()->view('ai.embed', [
            'tenant' => $tenant,
            'displayName' => (string) ($tenantConfig['display_name'] ?? 'JetPakistan'),
            'assistantName' => (string) ($tenantConfig['assistant_name'] ?? 'Ask JetPakistan'),
            'sessionEndpoint' => url("/api/embed/ai/{$tenant}/session"),
            'chatEndpoint' => url("/api/embed/ai/{$tenant}/chat"),
            'messagesEndpoint' => url("/api/embed/ai/{$tenant}/messages"),
            'clearEndpoint' => url("/api/embed/ai/{$tenant}/clear"),
            'handoffEndpoint' => url("/api/embed/ai/{$tenant}/handoff"),
            'sessionHeader' => (string) config('ai_embed.session_header', 'X-JP-AI-Embed-Session'),
            'parentOriginHeader' => (string) config('ai_embed.parent_origin_header', 'X-JP-AI-Embed-Parent-Origin'),
        ]);
    }
}
