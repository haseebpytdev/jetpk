<?php

namespace App\Services\Ai\Hybrid;

/**
 * Confidence / ambiguity gate for critical travel fields.
 * Never authorizes a search on a mere guess.
 */
final class IntentConfidenceGate
{
    /**
     * @param  array<string, string>  $provenance
     * @return array{ok: bool, message: ?string}
     */
    public function validateSearchable(
        ?string $origin,
        ?string $destination,
        ?string $departDate,
        bool $clarificationAlreadyRequired,
        ?string $clarificationMessage,
        array $provenance,
    ): array {
        if ($clarificationAlreadyRequired) {
            return ['ok' => false, 'message' => $clarificationMessage];
        }
        if ($origin === null || $destination === null) {
            return ['ok' => false, 'message' => 'Please share origin and destination cities (for example Lahore to Dubai).'];
        }
        foreach (['origin', 'destination'] as $field) {
            $p = $provenance[$field] ?? null;
            if ($p === 'GUESSED') {
                return ['ok' => false, 'message' => 'Please confirm the '.$field.' airport.'];
            }
        }

        return ['ok' => true, 'message' => null];
    }
}
