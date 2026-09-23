<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantEligibility;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\AiEmbedSessionService;
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
    ) {}

    public function session(Request $request, string $tenant): JsonResponse
    {
        if ($tenant !== 'jetpakistan') {
            return response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Unknown embed tenant.',
            ], 404);
        }

        if (! $this->embedSessions->isEnabledForTenant($tenant)) {
            return response()->json([
                'ok' => false,
                'status' => 'unavailable',
                'message' => 'Embed AI is not enabled.',
            ], 404);
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
            return response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Origin is not allowed for this embed tenant.',
            ], 403);
        }

        return response()->json([
            'ok' => true,
            'token' => $created['token'],
            'expires_at' => $created['expires_at'],
            'tenant' => $created['tenant'],
        ]);
    }

    public function chat(Request $request, string $tenant): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
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
            'embed'
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
            return $this->unavailable();
        }

        return response()->json($payload);
    }

    public function messages(Request $request, string $tenant): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = $this->requireSession($request);
        $visitorRaw = (string) ($session['visitor_raw'] ?? '');
        $conversation = $this->orchestrator->findOwnedConversationByVisitor($visitorRaw, $data['conversation_id']);

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
    }

    public function clear(Request $request, string $tenant): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
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
            'embed'
        );
        $conversation = $resolved['conversation'];

        if (($data['conversation_id'] ?? null) && $conversation !== null && $conversation->public_id === $data['conversation_id']) {
            $conversation = $this->orchestrator->clearConversation($conversation, $hash, 'embed');
        } else {
            $conversation = AiConversation::query()->create([
                'channel' => 'embed',
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
    }

    public function requestHandoff(Request $request, string $tenant): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:64'],
        ]);

        $session = $this->requireSession($request);
        $visitorRaw = (string) ($session['visitor_raw'] ?? '');
        $conversation = $this->orchestrator->findOwnedConversationByVisitor($visitorRaw, $data['conversation_id']);

        if ($conversation === null) {
            return response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Conversation not found.',
            ], 403);
        }

        $payload = $this->orchestrator->requestHandoff($conversation, $data['reason'] ?? null);

        return response()->json($payload);
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

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'status' => 'unavailable',
            'mode' => 'AI_UNAVAILABLE',
            'message' => 'AI assistance is temporarily unavailable.',
            'actions' => [
                ['label' => 'Search Flights', 'href' => 'https://jetpakistan.pk/#flight-search'],
                ['label' => 'Browse Groups', 'href' => 'https://jetpakistan.pk/groups'],
                ['label' => 'Manage Booking', 'href' => 'https://jetpakistan.pk/lookup-booking'],
                ['label' => 'Contact Support', 'href' => 'https://jetpakistan.pk/support'],
            ],
        ], 503);
    }
}
