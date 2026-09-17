<?php

namespace App\Services\Ai\Lab;

use App\Data\Ai\Lab\V1\ConsultantTurnResponse;

/**
 * Mock handoff — require explicit user consent before recording handoff.
 */
final class MockHandoffConsentGate
{
    public function sanitize(ConsultantTurnResponse $response): ConsultantTurnResponse
    {
        if ($response->actionKind() !== 'MOCK_HANDOFF') {
            return $response;
        }

        $consent = $response->labState['handoff_consent'] ?? null;

        if ($consent !== true) {
            return new ConsultantTurnResponse(
                assistantMessage: $response->assistantMessage !== ''
                    ? $response->assistantMessage
                    : 'Would you like me to send your request to our support team?',
                status: 'clarify',
                mode: $response->mode,
                parser: $response->parser,
                confirmation: $response->confirmation,
                action: ['kind' => 'NONE'],
                rag: $response->rag,
                recommendations: [],
                meta: array_merge($response->meta, ['handoff_blocked' => 'consent_required']),
                learningEvent: $response->learningEvent,
                labState: $response->labState,
                failureEvent: 'HANDOFF_WITHOUT_CONSENT',
            );
        }

        return $response;
    }
}
