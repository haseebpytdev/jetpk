<?php

namespace App\Services\Ai\Lab;

use App\Contracts\Ai\Lab\AiLabConsultantGateway;
use App\Data\Ai\Lab\V1\ConsultantTurnRequest;
use App\Data\Ai\Lab\V1\ConsultantTurnResponse;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiAssistantSettingsService;

/**
 * Laravel AI adapter — controlled shadow integration to certified lab stack.
 */
final class AiLabAdapter
{
    public function __construct(
        private readonly AiLabConsultantGateway $gateway,
        private readonly ConfirmationPolicyGate $confirmationGate,
        private readonly ShadowFlightSearchRecorder $shadowRecorder,
        private readonly FlightSearchReadOnlyExecutor $readOnlyFlightSearch,
        private readonly AiAssistantSettingsService $settingsService,
        private readonly RagLiveDataBlocker $ragBlocker,
        private readonly MockHandoffConsentGate $handoffGate,
        private readonly LearningQueueWriter $learningQueue,
        private readonly LabResponseNormalizer $normalizer,
        private readonly AiLabObservability $observability,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handleTurn(AiConversation $conversation, string $cleanMessage): array
    {
        $shopping = is_array($conversation->shopping_state) ? $conversation->shopping_state : [];
        $labState = is_array($shopping['lab'] ?? null) ? $shopping['lab'] : [];

        $effective = $this->settingsService->effective();

        $request = new ConsultantTurnRequest(
            conversationId: $conversation->public_id,
            message: $cleanMessage,
            locale: 'en',
            channel: 'ask_jetpakistan',
            state: ['lab' => $labState],
            capabilities: [
                'shadow_flight_search' => (bool) config('ai_lab.shadow_flight_search', true)
                    && ! ($effective['flight_search_read_only_enabled'] ?? false),
                'flight_search_read_only' => (bool) ($effective['flight_search_read_only_enabled'] ?? false),
                'rag' => (bool) ($effective['rag_enabled'] ?? false),
                'live_supplier' => false,
                'mock_handoff' => (bool) ($effective['human_handoff_enabled'] ?? false),
            ],
            confirmation: [
                'state' => $labState['dialog_state'] ?? 'NONE',
                'snapshot_hash' => $labState['confirmation_token'] ?? null,
            ],
            authenticatedUserId: $conversation->user_id ? (string) $conversation->user_id : null,
        );

        $started = hrtime(true);
        try {
            $response = $this->gateway->turn($request);
        } catch (\Throwable) {
            $this->observability->record('gateway_error', ['status' => 'unavailable']);
            throw new \RuntimeException('AI lab gateway unavailable');
        }
        $gatewayMs = (int) ((hrtime(true) - $started) / 1_000_000);

        $allowedKinds = ['NONE', 'SHADOW_FLIGHT_SEARCH', 'FLIGHT_SEARCH_READ_ONLY', 'MOCK_HANDOFF', 'RAG_ANSWER'];
        if ($response->actionKind() !== 'NONE' && ! in_array($response->actionKind(), $allowedKinds, true)) {
            $response = new ConsultantTurnResponse(
                assistantMessage: 'I need a moment — please try again.',
                status: 'refused',
                mode: $response->mode,
                parser: $response->parser,
                confirmation: $response->confirmation,
                action: ['kind' => 'NONE'],
                rag: $response->rag,
                recommendations: [],
                meta: array_merge($response->meta, ['malformed_action' => $response->actionKind()]),
                learningEvent: $response->learningEvent,
                labState: $response->labState,
                failureEvent: 'MALFORMED_RESPONSE_ACTION',
            );
        }

        if ($response->assistantMessage === '' && $response->status !== 'degraded') {
            $response = new ConsultantTurnResponse(
                assistantMessage: 'I am here to help with JetPakistan flights and travel questions.',
                status: 'ok',
                mode: $response->mode,
                parser: $response->parser,
                confirmation: $response->confirmation,
                action: $response->action,
                rag: $response->rag,
                recommendations: $response->recommendations,
                meta: $response->meta,
                learningEvent: $response->learningEvent,
                labState: $response->labState,
                failureEvent: 'SILENT_RESPONSE',
            );
        }

        $response = $this->ragBlocker->sanitize($response, $cleanMessage);
        $response = $this->handoffGate->sanitize($response);

        $gate = $this->confirmationGate->evaluate($response, $labState);
        if (! $gate['allowed']) {
            $response = $this->confirmationGate->stripDisallowedAction($response, (string) $gate['reason']);
        }

        $recommendations = $response->recommendations;
        $meta = [
            'intent' => $response->parser,
            'dialog_state' => $response->labState['dialog_state'] ?? null,
        ];

        $flightAction = $response->actionKind();
        if (in_array($flightAction, ['SHADOW_FLIGHT_SEARCH', 'FLIGHT_SEARCH_READ_ONLY'], true) && $gate['allowed']) {
            $payload = $response->action['payload'] ?? [];
            $useReadOnly = ($effective['flight_search_read_only_enabled'] ?? false)
                && in_array($flightAction, ['FLIGHT_SEARCH_READ_ONLY', 'SHADOW_FLIGHT_SEARCH'], true);

            if ($useReadOnly) {
                $search = $this->readOnlyFlightSearch->execute($payload);
                $response = new ConsultantTurnResponse(
                    assistantMessage: $search['message'],
                    status: 'ok',
                    mode: $response->mode,
                    parser: $response->parser,
                    confirmation: $response->confirmation,
                    action: ['kind' => 'FLIGHT_SEARCH_READ_ONLY', 'payload' => $payload],
                    rag: $response->rag,
                    recommendations: $search['recommendations'],
                    meta: array_merge($response->meta, ['search_record' => $search['search_record']]),
                    learningEvent: $response->learningEvent,
                    labState: $response->labState,
                );
                $recommendations = $search['recommendations'];
                $meta['search_record'] = $search['search_record'];
            } else {
                $shadow = $this->shadowRecorder->record($payload);
                $response = new ConsultantTurnResponse(
                    assistantMessage: $shadow['message'],
                    status: 'ok',
                    mode: $response->mode,
                    parser: $response->parser,
                    confirmation: $response->confirmation,
                    action: $response->action,
                    rag: $response->rag,
                    recommendations: $shadow['recommendations'],
                    meta: array_merge($response->meta, ['shadow_record' => $shadow['shadow_record']]),
                    learningEvent: $response->learningEvent,
                    labState: $response->labState,
                );
                $recommendations = $shadow['recommendations'];
                $meta['shadow_record'] = $shadow['shadow_record'];
            }
        }

        $shopping['lab'] = $response->labState;
        $conversation->shopping_state = $shopping;
        $conversation->save();

        if (is_array($response->learningEvent) && ($effective['learning_queue_enabled'] ?? false)) {
            $this->learningQueue->enqueue($response->learningEvent);
        }

        $this->observability->record('turn_complete', [
            'status' => $response->status,
            'mode' => $response->mode,
            'action_kind' => $response->actionKind(),
            'gateway_ms' => $gatewayMs,
            'dialog_state' => $response->labState['dialog_state'] ?? null,
        ]);

        $assistant = $this->storeMessage($conversation, $response, $recommendations, $meta);

        return $this->normalizer->toPublicPayload($conversation, $response, $assistant, $recommendations, $meta);
    }

    /**
     * @param  list<array<string, mixed>>  $recommendations
     * @param  array<string, mixed>  $meta
     */
    private function storeMessage(
        AiConversation $conversation,
        ConsultantTurnResponse $response,
        array $recommendations,
        array $meta,
    ): AiMessage {
        return AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'body' => $response->assistantMessage,
            'meta' => [
                'mode' => $response->mode,
                'status' => $response->status,
                'action' => $response->action,
                'confirmation' => $response->confirmation,
                'recommendations' => $recommendations,
                'lab' => $meta,
            ],
        ]);
    }
}
