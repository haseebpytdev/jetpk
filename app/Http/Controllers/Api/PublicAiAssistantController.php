<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Services\Ai\AiAssistantEligibility;
use App\Services\Ai\AiChatOrchestrator;
use App\Services\Ai\CustomerQueryLeadService;
use App\Services\Ai\Lab\AiLabFaultInjectionContext;
use App\Services\Ai\Lab\AiLabGatewayUrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public AI chat API. Soft-fails when disabled or ineligible — never 500 for model down.
 */
class PublicAiAssistantController extends Controller
{
    public function __construct(
        private readonly AiChatOrchestrator $orchestrator,
        private readonly AiAssistantEligibility $eligibility,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $max = max(100, (int) config('ota.ai_assistant.max_message_chars', 2000));
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.$max],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $resolved = $this->orchestrator->resolveConversation(
            $request,
            $data['conversation_id'] ?? null
        );
        /** @var AiConversation $conversation */
        $conversation = $resolved['conversation'];

        if ($rate = $this->orchestrator->assertRateLimit($resolved['visitor_raw'])) {
            $retryAfter = max(1, (int) ($rate['retry_after'] ?? 60));

            return $this->withVisitorCookie(
                response()->json($rate, 429)->header('Retry-After', (string) $retryAfter),
                $resolved
            );
        }

        $sanitized = $this->orchestrator->sanitizeUserMessage($data['message']);
        if (! ($sanitized['ok'] ?? false)) {
            $payload = $sanitized['payload'] ?? [
                'ok' => false,
                'status' => 'invalid',
                'message' => 'Invalid message.',
            ];
            $status = ($payload['status'] ?? '') === 'refused' ? 200 : 422;

            return $this->withVisitorCookie(response()->json($payload, $status), $resolved);
        }

        try {
            $payload = $this->orchestrator->handleChat($conversation, $sanitized['message']);
        } catch (\Throwable) {
            return $this->withVisitorCookie($this->unavailable(), $resolved);
        }

        return $this->withVisitorCookie(response()->json($payload), $resolved);
    }

    public function health(
        Request $request,
        AiLabConsultantGateway $labGateway,
        AiLabGatewayUrlResolver $urlResolver,
    ): JsonResponse {
        try {
            $base = $this->orchestrator->healthPayload();
            $status = $this->eligibility->statusPayload();

            $payload = array_merge($base, [
                'enabled' => $this->eligibility->isRuntimeOn(),
                'assistant_mode' => $status['mode'],
                'status' => $status,
            ]);

            if ($this->eligibility->isEligibleRequest($request)) {
                $resolved = $urlResolver->resolve();
                $host = is_string($resolved) ? (string) parse_url($resolved, PHP_URL_HOST) : null;
                $port = is_string($resolved) ? (string) parse_url($resolved, PHP_URL_PORT) : null;
                $payload['ai_lab_gateway'] = [
                    'healthy' => $labGateway->isHealthy(),
                    'resolved_host' => $host,
                    'resolved_port' => $port,
                    'fault_mode' => AiLabFaultInjectionContext::mode(),
                ];
            }

            return response()->json($payload);
        } catch (\Throwable) {
            return response()->json([
                'ok' => true,
                'enabled' => false,
                'gateway' => 'degraded',
                'mode' => 'STRUCTURED_FALLBACK',
                'assistant_mode' => $this->eligibility->mode(),
            ]);
        }
    }

    public function messages(Request $request): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'since_id' => ['nullable', 'integer', 'min:0'],
        ]);

        [$visitorRaw, $setCookie] = $this->orchestrator->resolveVisitorToken($request);
        $conversation = $this->orchestrator->findOwnedConversation($request, $data['conversation_id']);
        $resolved = [
            'visitor_raw' => $visitorRaw,
            'set_cookie' => $setCookie || true,
        ];

        if ($conversation === null) {
            return $this->withVisitorCookie(response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Conversation not found.',
            ], 403), $resolved);
        }

        $messages = $this->orchestrator->messagesSince(
            $conversation,
            isset($data['since_id']) ? (int) $data['since_id'] : null
        );

        return $this->withVisitorCookie(response()->json([
            'ok' => true,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'messages' => $messages,
        ]), $resolved);
    }

    public function clear(Request $request): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $data = $request->validate([
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $resolved = $this->orchestrator->resolveConversation(
            $request,
            $data['conversation_id'] ?? null
        );
        $conversation = $resolved['conversation'];
        $hash = $this->orchestrator->hashVisitor($resolved['visitor_raw']);

        if (($data['conversation_id'] ?? null) && $conversation->public_id === $data['conversation_id']) {
            $conversation = $this->orchestrator->clearConversation($conversation, $hash);
        } else {
            $conversation = AiConversation::query()->create([
                'channel' => 'web',
                'visitor_token_hash' => $hash,
                'user_id' => $request->user()?->id,
                'state' => AiConversation::STATE_AI_ACTIVE,
                'shopping_state' => [],
            ]);
        }

        return $this->withVisitorCookie(response()->json([
            'ok' => true,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => 'Started a new conversation.',
        ]), array_merge($resolved, ['set_cookie' => true, 'conversation' => $conversation]));
    }

    public function submitLead(Request $request, CustomerQueryLeadService $leadService): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $resolved = $this->orchestrator->resolveConversation($request, $request->input('conversation_id'), false);
        $conversation = $resolved['conversation'] ?? null;
        if ($conversation === null) {
            return $this->withVisitorCookie(response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Conversation not found.',
            ], 403), $resolved);
        }

        $requiredFields = $leadService->requiredLeadFields($request->user());
        $rules = [
            'conversation_id' => ['required', 'uuid'],
            'phone_country' => ['nullable', 'string', 'max:8'],
        ];
        foreach ($requiredFields as $field) {
            if ($field === 'contact_consent') {
                $rules['contact_consent'] = ['required', 'boolean'];
            } elseif ($field === 'name') {
                $rules['name'] = ['required', 'string', 'max:120'];
            } elseif ($field === 'email') {
                $rules['email'] = ['required', 'string', 'email', 'max:191'];
            } elseif ($field === 'phone') {
                $rules['phone'] = ['required', 'string', 'max:40'];
            }
        }

        $data = $request->validate($rules);

        $result = $leadService->createFromPayload(
            $conversation,
            $data,
            $resolved['visitor_hash'] ?? $this->orchestrator->hashVisitor($resolved['visitor_raw']),
            $request->user(),
            $request->header('CF-IPCountry') ?: null,
        );

        if (! ($result['ok'] ?? false)) {
            return $this->withVisitorCookie(response()->json([
                'ok' => false,
                'status' => 'validation_error',
                'errors' => $result['errors'] ?? [],
            ], 422), $resolved);
        }

        $state = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $pendingMessage = (string) ($state['lead_pending_message'] ?? '');
        unset(
            $state['lead_capture_pending'],
            $state['lead_capture_stage'],
            $state['lead_name'],
            $state['lead_email'],
            $state['lead_phone'],
            $state['lead_pending_message'],
            $state['lead_capture_fields'],
        );
        $conversation->shopping_state = $state;
        $conversation->save();

        $followUp = $pendingMessage !== ''
            ? $this->orchestrator->handleChat($conversation, $pendingMessage)
            : [
                'ok' => true,
                'status' => 'lead_saved',
                'conversation_id' => $conversation->public_id,
                'message' => 'Thank you. How can I help with your travel request?',
            ];

        $followUp['query_reference'] = $result['query']->query_reference;
        $followUp['status'] = $followUp['status'] ?? 'lead_saved';

        return $this->withVisitorCookie(response()->json($followUp), $resolved);
    }

    public function requestHandoff(Request $request): JsonResponse
    {
        if (! $this->eligibility->isEligibleRequest($request)) {
            return $this->unavailable();
        }

        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'reason' => ['nullable', 'string', 'max:64'],
        ]);

        [$visitorRaw, $setCookie] = $this->orchestrator->resolveVisitorToken($request);
        $conversation = $this->orchestrator->findOwnedConversation($request, $data['conversation_id']);
        $resolved = [
            'visitor_raw' => $visitorRaw,
            'set_cookie' => $setCookie || true,
        ];

        if ($conversation === null) {
            return $this->withVisitorCookie(response()->json([
                'ok' => false,
                'status' => 'forbidden',
                'message' => 'Conversation not found.',
            ], 403), $resolved);
        }

        $payload = $this->orchestrator->requestHandoff($conversation, $data['reason'] ?? null);

        return $this->withVisitorCookie(response()->json($payload), $resolved);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'status' => 'unavailable',
            'mode' => 'AI_UNAVAILABLE',
            'message' => 'AI assistance is temporarily unavailable.',
            'actions' => [
                ['label' => 'Search Flights', 'href' => '/#flight-search'],
                ['label' => 'Browse Groups', 'href' => '/groups'],
                ['label' => 'Manage Booking', 'href' => '/lookup-booking'],
                ['label' => 'Contact Support', 'href' => '/support'],
            ],
        ], 503);
    }

    /**
     * @param  array{visitor_raw: string, set_cookie: bool}  $resolved
     */
    private function withVisitorCookie(JsonResponse $response, array $resolved): JsonResponse
    {
        return $response->cookie(
            AiChatOrchestrator::COOKIE,
            $resolved['visitor_raw'],
            60 * 24 * 30,
            '/',
            null,
            (bool) config('session.secure', false),
            true,
            false,
            'Lax'
        );
    }
}
