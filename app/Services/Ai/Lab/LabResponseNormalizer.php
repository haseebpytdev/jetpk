<?php

namespace App\Services\Ai\Lab;

use App\Data\Ai\Lab\V1\ConsultantTurnResponse;
use App\Models\AiConversation;
use App\Models\AiMessage;

/**
 * Map lab v1 response to existing public Ask JetPakistan JSON contract.
 */
final class LabResponseNormalizer
{
    /**
     * @param  list<array<string, mixed>>  $recommendations
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function toPublicPayload(
        AiConversation $conversation,
        ConsultantTurnResponse $response,
        AiMessage $assistant,
        array $recommendations = [],
        array $meta = [],
    ): array {
        $status = match ($response->status) {
            'clarify', 'needs_confirmation' => $response->status,
            'refused' => 'refused',
            'degraded' => 'unavailable',
            default => 'ok',
        };

        $baseMeta = array_merge([
            'AI_FLIGHT_SEARCH_READ_CALLS' => 0,
            'AI_GROUP_SEARCH_READ_CALLS' => 0,
            'LOCAL_LLM_REQUIRED_FOR_CORE' => false,
            'lab_adapter' => true,
            'lab_sha' => config('ai_lab.lab_git_sha'),
            'contract_version' => 'v1',
        ], $meta, $response->meta);

        if ($response->actionKind() === 'SHADOW_FLIGHT_SEARCH') {
            $baseMeta['AI_FLIGHT_SEARCH_READ_CALLS'] = 0;
            $baseMeta['shadow_flight_search'] = true;
        }

        $payload = [
            'ok' => $status !== 'unavailable',
            'status' => $status,
            'mode' => $response->mode,
            'conversation_id' => $conversation->public_id,
            'state' => $conversation->state,
            'message' => $response->assistantMessage,
            'recommendations' => $recommendations,
            'actions' => $this->defaultActions(),
            'meta' => $baseMeta,
            'message_id' => $assistant->id,
        ];

        $confirmState = $response->confirmationState();
        $dialogState = strtoupper((string) ($response->labState['dialog_state'] ?? ''));
        if (in_array($confirmState, ['PROPOSED', 'AWAITING_CONFIRMATION'], true)
            || $dialogState === 'AWAITING_CONFIRMATION'
            || $response->status === 'needs_confirmation') {
            $payload['requires_confirmation'] = true;
            $payload['confirmation_snapshot'] = $response->confirmation['snapshot'] ?? null;
        }

        if ($response->rag !== []) {
            $payload['knowledge'] = array_map(static fn (array $hit): array => [
                'title' => $hit['title'] ?? 'Knowledge',
                'source_id' => $hit['source_id'] ?? null,
            ], $response->rag['hits'] ?? $response->rag['evidence'] ?? []);
        }

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultActions(): array
    {
        return [
            ['label' => 'Search Flights', 'href' => '/#flight-search'],
            ['label' => 'Talk to Support', 'action' => 'handoff'],
        ];
    }
}
