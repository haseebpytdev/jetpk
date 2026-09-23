<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiEmbedSessionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiEmbedPageController extends Controller
{
    private const TENANT = 'jetpakistan';

    public function __construct(
        private readonly AiEmbedSessionService $embedSessions,
    ) {}

    public function show(Request $request, string $pathToken): Response
    {
        if (! $this->embedSessions->matchesEntryPath(self::TENANT, $pathToken)) {
            abort(404);
        }

        if (! $this->embedSessions->isEmbedPageAvailable(self::TENANT)) {
            abort(404);
        }

        $tenantConfig = config('ai_embed.tenants.'.self::TENANT, []);

        return response()->view('ai.embed', [
            'tenant' => self::TENANT,
            'displayName' => (string) ($tenantConfig['display_name'] ?? 'JetPakistan'),
            'assistantName' => (string) ($tenantConfig['assistant_name'] ?? 'Ask JetPakistan'),
            'sessionEndpoint' => url('/api/embed/ai/'.self::TENANT.'/session'),
            'chatEndpoint' => url('/api/embed/ai/'.self::TENANT.'/chat'),
            'messagesEndpoint' => url('/api/embed/ai/'.self::TENANT.'/messages'),
            'clearEndpoint' => url('/api/embed/ai/'.self::TENANT.'/clear'),
            'handoffEndpoint' => url('/api/embed/ai/'.self::TENANT.'/handoff'),
            'sessionHeader' => (string) config('ai_embed.session_header', 'X-JP-AI-Embed-Session'),
            'parentOriginHeader' => (string) config('ai_embed.parent_origin_header', 'X-JP-AI-Embed-Parent-Origin'),
        ]);
    }
}
