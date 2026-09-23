<?php

namespace App\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiEmbedTenant;
use App\Services\Ai\AiEmbedSessionService;
use App\Services\Ai\Embed\EmbedProviderFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiEmbedPageController extends Controller
{
    public function __construct(
        private readonly AiEmbedSessionService $embedSessions,
        private readonly EmbedProviderFactory $providerFactory,
    ) {}

    public function show(Request $request, string $pathToken): Response
    {
        $tenant = $this->embedSessions->resolveTenantByEmbedKey($pathToken);
        if ($tenant === null) {
            abort(404);
        }

        if (! $this->embedSessions->isEmbedPageAvailable($tenant)) {
            abort(404);
        }

        $presentation = $this->providerFactory->tenantConfig($tenant)->presentationConfig();
        $embedKey = $pathToken;
        $apiBase = rtrim((string) config('ai_embed.public_base_url', config('app.url')), '/');

        return response()->view('ai.embed', [
            'tenantPublicId' => $tenant->public_id,
            'displayName' => $presentation['display_name'] ?? $tenant->display_name,
            'assistantName' => $presentation['assistant_name'] ?? $tenant->assistant_name,
            'welcomeText' => $presentation['welcome_text'] ?? '',
            'logoUrl' => $presentation['logo_url'] ?? null,
            'themePrimary' => $presentation['theme']['primary'] ?? '#0b5fff',
            'sessionEndpoint' => $apiBase.'/api/embed/ai/'.$embedKey.'/session',
            'chatEndpoint' => $apiBase.'/api/embed/ai/'.$embedKey.'/chat',
            'messagesEndpoint' => $apiBase.'/api/embed/ai/'.$embedKey.'/messages',
            'clearEndpoint' => $apiBase.'/api/embed/ai/'.$embedKey.'/clear',
            'handoffEndpoint' => $apiBase.'/api/embed/ai/'.$embedKey.'/handoff',
            'sessionHeader' => (string) config('ai_embed.session_header', 'X-JP-AI-Embed-Session'),
            'parentOriginHeader' => (string) config('ai_embed.parent_origin_header', 'X-JP-AI-Embed-Parent-Origin'),
        ]);
    }
}
