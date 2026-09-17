<?php

namespace App\Services\Ai\Lab;

use App\Data\Ai\Lab\V1\ConsultantTurnResponse;

/**
 * Block RAG from answering live commercial data queries.
 */
final class RagLiveDataBlocker
{
    private const LIVE_MARKERS = [
        'live fare', 'current price', 'pnr', 'ticket status', 'refund amount',
        'seat availability', 'my booking status', 'availability today',
    ];

    public function sanitize(ConsultantTurnResponse $response, string $userMessage): ConsultantTurnResponse
    {
        if ($response->actionKind() !== 'RAG_ANSWER') {
            return $response;
        }

        if (! (bool) config('ai_lab.rag_block_live_data', true)) {
            return $response;
        }

        $blocked = ($response->rag['blocked_live_data'] ?? false) === true
            || $this->looksLikeLiveDataQuery($userMessage);

        if (! $blocked) {
            return $response;
        }

        return new ConsultantTurnResponse(
            assistantMessage: 'Live fares, availability, and booking status require verified booking tools — I cannot answer that from documentation alone.',
            status: 'refused',
            mode: $response->mode,
            parser: $response->parser,
            confirmation: $response->confirmation,
            action: ['kind' => 'NONE'],
            rag: array_merge($response->rag, ['blocked_live_data' => true]),
            recommendations: [],
            meta: array_merge($response->meta, ['rag_live_data_blocked' => true]),
            learningEvent: array_merge($response->learningEvent ?? [], [
                'failure_category' => 'RAG_NO_SOURCE',
            ]),
            labState: $response->labState,
            failureEvent: 'LIVE_DATA_FROM_RAG',
        );
    }

    private function looksLikeLiveDataQuery(string $message): bool
    {
        $lower = strtolower($message);
        foreach (self::LIVE_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }

        return false;
    }
}
