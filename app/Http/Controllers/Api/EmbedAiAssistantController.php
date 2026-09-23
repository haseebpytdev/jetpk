<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiEmbedTenant;
use App\Services\Ai\AiAssistantEligibility;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\AiEmbedSessionService;
use App\Services\Ai\Embed\EmbedAuditLogger;
use App\Services\Ai\Embed\EmbedProviderFactory;
use App\Services\Ai\Embed\EmbedRuntimeContext;
use App\Support\Ai\Embed\EmbedTenantCapability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cross-origin iframe AI transport. Reuses AiChatOrchestrator without jp_ai_vid cookies.
 */
class EmbedAiAssistantController extends Controller
{
    public function __construct(
        private readonly AiChatOrchestrator $orchestrator,
        private readonly AiAssistantEligibility $eligibility,
        private readonly AiEmbedSessionService $embedSessions,
        private readonly EmbedProviderFactory $providerFactory,
        private readonly EmbedRuntimeContext $runtimeContext,
        private readonly EmbedAuditLogger $auditLogger,
    ) {}

    public function session(Request $request, string $embedKey): JsonResponse
    {
        $tenant = $this->resolveOperationalTenant($embedKey);
        if ($tenant === null) {
            return $this->notFound('Unknown embed entry.');
        }

        $parentOriginHeader = (string) config('ai_embed.parent_origin_header', 'X-JP-AI-Embed-Parent-Origin');
        $parentOrigin = trim((string) $request->header($parentOriginHeader, ''));
        if ($parentOrigin === '') {
            $referer = (string) $request->headers->get('Referer', '');
            if ($referer !== '') {
                $parsed = parse_url($referer);
                if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                    $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
                    $parentOrigin = $parsed['scheme'].'://'.$parsed['host'].$port;
                }
            }
        }

        $created = $this->embedSessions->createSession($tenant, $parentOrigin);
        if ($created === null) {
            $this->auditLogger->record($tenant, 'embed.session.create', false, ['reason' => 'origin_or_policy']);

            return response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Origin is not allowed for this embed tenant.',
            ], 403);
        }

        $this->auditLogger->record($tenant, 'embed.session.create', true);

        return response()->json([
            'ok' => true,
            'token' => $created['token'],
            'expires_at' => $created['expires_at'],
            'tenant_public_id' => $created['tenant_public_id'],
        ]);
    }

    public function chat(Request $request, string $embedKey): JsonResponse
    {
        return $this->withTenantContext($request, $embedKey, function (AiEmbedTenant $tenant) use ($request): JsonResponse {
            if (! $this->eligibility->isEligibleRequest($request)) {
                return $this->unavailable($tenant);
            }

            if (! $tenant->hasCapability(EmbedTenantCapability::GENERAL_AI)) {
                return response()->json([
                    'ok' => false,
                    'status' => 'forbidden',
                    'message' => 'General AI is not enabled for this tenant.',
                ], 403);
            }

            $max = max(100, (int) config('ota.ai_assistant.max_message_chars', 2000));
            $data = $request->validate([
                'message' => ['required', 'string', 'max:'.$max],
                'conversation_id' => ['nullable', 'uuid'],
            ]);

            $session = $this->requireSession($request);
            $visitorRaw = (string) ($session['visitor_raw'] ?? '');
            $resolved = $this->orchestrator->resolveConversationForVisitor(
                $visitorRaw,
                $data['conversation_id'] ?? null,
                true,
                'embed',
                null,
                $tenant->id,
            );
            /** @var AiConversation|null $conversation */
            $conversation = $resolved['conversation'];
            if ($conversation === null) {
                return response()->json([
                    'ok' => false,
                    'status' => 'forbidden',
                    'message' => 'Conversation not found.',
                ], 403);
            }

            $this->bindConversationToSession($request, $conversation->public_id);

            if ($rate = $this->orchestrator->assertRateLimit($visitorRaw)) {
                return response()->json($rate, 429);
            }

            $sanitized = $this->orchestrator->sanitizeUserMessage($data['message']);
            if (! ($sanitized['ok'] ?? false)) {
                $payload = $sanitized['payload'] ?? [
                    'ok' => false,
                    'status' => 'invalid',
                    'message' => 'Invalid message.',
                ];
                $status = ($payload['status'] ?? '') === 'refused' ? 200 : 422;

                return response()->json($payload, $status);
            }

            try {
                $payload = $this->orchestrator->handleChat($conversation, $sanitized['message']);
            } catch (\Throwable) {
                return $this->unavailable($tenant);
            }

            $this->auditLogger->record($tenant, 'embed.chat', true);

            return response()->json($payload);
        });
    }

    public function messages(Request $request, string $embedKey): JsonResponse
    {
        return $this->withTenantContext($request, $embedKey, function (AiEmbedTenant $tenant) use ($request): JsonResponse {
            if (! $this->eligibility->isEligibleRequest($request)) {
                return $this->unavailable($tenant);
            }

            $data = $request->validate([
                'conversation_id' => ['required', 'uuid'],
                'since_id' => ['nullable', 'integer', 'min:0'],
            ]);

            $session = $this->requireSession($request);
            $visitorRaw = (string) ($session['visitor_raw'] ?? '');
            $conversation = $this->orchestrator->findOwnedConversationByVisitor(
                $visitorRaw,
                $data['conversation_id'],
                $tenant->id,
            );

            if ($conversation === null) {
                return response()->json([
                    'ok' => false,
                    'status' => 'forbidden',
                    'message' => 'Conversation not found.',
                ], 403);
            }

            $messages = $this->orchestrator->messagesSince(
                $conversation,
                isset($data['since_id']) ? (int) $data['since_id'] : null
            );

            return response()->json([
                'ok' => true,
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'messages' => $messages,
            ]);
        });
    }

    public function clear(Request $request, string $embedKey): JsonResponse
    {
        return $this->withTenantContext($request, $embedKey, function (AiEmbedTenant $tenant) use ($request): JsonResponse {
            if (! $this->eligibility->isEligibleRequest($request)) {
                return $this->unavailable($tenant);
            }

            $data = $request->validate([
                'conversation_id' => ['nullable', 'uuid'],
            ]);

            $session = $this->requireSession($request);
            $visitorRaw = (string) ($session['visitor_raw'] ?? '');
            $hash = $this->orchestrator->hashVisitor($visitorRaw);

            $resolved = $this->orchestrator->resolveConversationForVisitor(
                $visitorRaw,
                $data['conversation_id'] ?? null,
                true,
                'embed',
                null,
                $tenant->id,
            );
            $conversation = $resolved['conversation'];

            if (($data['conversation_id'] ?? null) && $conversation !== null && $conversation->public_id === $data['conversation_id']) {
                $conversation = $this->orchestrator->clearConversation($conversation, $hash, 'embed');
            } else {
                $conversation = AiConversation::query()->create([
                    'channel' => 'embed',
                    'ai_embed_tenant_id' => $tenant->id,
                    'visitor_token_hash' => $hash,
                    'user_id' => null,
                    'state' => AiConversation::STATE_AI_ACTIVE,
                    'shopping_state' => [],
                ]);
            }

            $this->bindConversationToSession($request, $conversation->public_id);

            return response()->json([
                'ok' => true,
                'conversation_id' => $conversation->public_id,
                'state' => $conversation->state,
                'message' => 'Started a new conversation.',
            ]);
        });
    }

    public function requestHandoff(Request $request, string $embedKey): JsonResponse
    {
        return $this->withTenantContext($request, $embedKey, function (AiEmbedTenant $tenant) use ($request): JsonResponse {
            if (! $this->eligibility->isEligibleRequest($request)) {
                return $this->unavailable($tenant);
            }

            if (! $tenant->hasCapability(EmbedTenantCapability::SUPPORT_HANDOFF)) {
                return response()->json([
                    'ok' => false,
                    'status' => 'forbidden',
                    'message' => 'Support handoff is not enabled for this tenant.',
                ], 403);
            }

            $data = $request->validate([
                'conversation_id' => ['required', 'uuid'],
                'reason' => ['nullable', 'string', 'max:64'],
            ]);

            $session = $this->requireSession($request);
            $visitorRaw = (string) ($session['visitor_raw'] ?? '');
            $conversation = $this->orchestrator->findOwnedConversationByVisitor(
                $visitorRaw,
                $data['conversation_id'],
                $tenant->id,
            );

            if ($conversation === null) {
                return response()->json([
                    'ok' => false,
                    'status' => 'forbidden',
                    'message' => 'Conversation not found.',
                ], 403);
            }

            $payload = $this->orchestrator->requestHandoff($conversation, $data['reason'] ?? null);

            return response()->json($payload);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requireSession(Request $request): array
    {
        /** @var array<string, mixed>|null $session */
        $session = $request->attributes->get('ai_embed_session');
        if (! is_array($session)) {
            abort(response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Embed session missing.',
            ], 403));
        }

        return $session;
    }

    private function bindConversationToSession(Request $request, string $conversationPublicId): void
    {
        $token = (string) $request->attributes->get('ai_embed_token', '');
        if ($token !== '') {
            $this->embedSessions->bindConversation($token, $conversationPublicId);
        }
    }

    private function resolveOperationalTenant(string $embedKey): ?AiEmbedTenant
    {
        return $this->embedSessions->resolveTenantByEmbedKey($embedKey);
    }

    private function notFound(string $message): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'status' => 'forbidden',
            'message' => $message,
        ], 404);
    }

    private function unavailable(AiEmbedTenant $tenant): JsonResponse
    {
        $actions = $this->providerFactory->tenantConfig($tenant)->unavailableActions();

        return response()->json([
            'ok' => false,
            'status' => 'unavailable',
            'mode' => 'AI_UNAVAILABLE',
            'message' => 'AI assistance is temporarily unavailable.',
            'actions' => $actions,
        ], 503);
    }

    /**
     * @param  callable(AiEmbedTenant): JsonResponse  $callback
     */
    private function withTenantContext(Request $request, string $embedKey, callable $callback): JsonResponse
    {
        /** @var AiEmbedTenant|null $tenant */
        $tenant = $request->attributes->get('ai_embed_tenant_model');
        if (! $tenant instanceof AiEmbedTenant) {
            $tenant = $this->resolveOperationalTenant($embedKey);
        }
        if ($tenant === null) {
            return $this->notFound('Unknown embed entry.');
        }

        $this->runtimeContext->activate(
            $tenant,
            $this->providerFactory->knowledge($tenant),
            $this->providerFactory->lead($tenant),
            $this->providerFactory->search($tenant),
            $this->providerFactory->bookingLookup($tenant),
            $this->providerFactory->handoff($tenant),
        );

        try {
            return $callback($tenant);
        } finally {
            $this->runtimeContext->deactivate();
        }
    }
}
