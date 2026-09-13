<?php

namespace App\Services\Ai\Lab;

use App\Data\Ai\Lab\V1\ConsultantTurnResponse;

/**
 * 04A confirmation enforcement at Laravel boundary — never trust gateway alone.
 */
final class ConfirmationPolicyGate
{
    private const ALLOWED_ACTIONS = [
        'SHADOW_FLIGHT_SEARCH',
        'MOCK_HANDOFF',
        'RAG_ANSWER',
    ];

    /**
     * @param  array<string, mixed>  $storedLabState
     * @return array{allowed: bool, reason: ?string}
     */
    public function evaluate(ConsultantTurnResponse $response, array $storedLabState): array
    {
        $kind = $response->actionKind();
        if ($kind === 'NONE') {
            return ['allowed' => true, 'reason' => null];
        }

        if (! in_array($kind, self::ALLOWED_ACTIONS, true)) {
            return ['allowed' => false, 'reason' => 'action_not_allowlisted'];
        }

        if ($kind === 'RAG_ANSWER' || $kind === 'MOCK_HANDOFF') {
            return ['allowed' => true, 'reason' => null];
        }

        // SHADOW_FLIGHT_SEARCH requires explicit confirmation + matching snapshot in response state.
        $newState = $response->labState;
        if (($newState['tool_executed'] ?? false) !== true) {
            return ['allowed' => false, 'reason' => 'confirmation_required'];
        }

        if (($newState['user_confirmed'] ?? false) !== true || ($newState['confirmation_valid'] ?? false) !== true) {
            return ['allowed' => false, 'reason' => 'user_not_confirmed'];
        }

        $hash = $response->snapshotHash();
        $priorToken = is_string($storedLabState['confirmation_token'] ?? null)
            ? $storedLabState['confirmation_token']
            : null;

        if ($hash === null) {
            return ['allowed' => false, 'reason' => 'snapshot_mismatch'];
        }

        if ($priorToken !== null && $hash !== $priorToken) {
            return ['allowed' => false, 'reason' => 'snapshot_mismatch'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function stripDisallowedAction(ConsultantTurnResponse $response, string $reason): ConsultantTurnResponse
    {
        $message = $response->assistantMessage;
        if ($message === '') {
            $message = match ($reason) {
                'confirmation_required' => 'Please confirm the route details before I search.',
                'snapshot_mismatch' => 'Your details changed — please review and confirm again.',
                default => 'I need your confirmation before proceeding.',
            };
        }

        return new ConsultantTurnResponse(
            assistantMessage: $message,
            status: 'needs_confirmation',
            mode: $response->mode,
            parser: $response->parser,
            confirmation: $response->confirmation,
            action: ['kind' => 'NONE'],
            rag: $response->rag,
            recommendations: [],
            meta: array_merge($response->meta, ['action_blocked' => $reason]),
            learningEvent: $response->learningEvent,
            labState: $response->labState,
            failureEvent: 'ACTION_WITHOUT_CONFIRMATION',
        );
    }
}
