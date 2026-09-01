<?php

namespace App\Services\Ai\Hybrid;

use App\Data\Ai\TravelIntent;

/**
 * Applies follow-up patches onto structured conversation state without regenerating unrelated fields.
 */
final class ConversationStatePatcher
{
    /**
     * @param  array<string, mixed>  $prior
     * @return array<string, mixed>
     */
    public function merge(array $prior, TravelIntent $intent): array
    {
        $next = $prior;
        foreach ($intent->toArray() as $key => $value) {
            if ($key === 'mode') {
                continue;
            }
            if ($value === null) {
                continue;
            }
            if ($key === 'adults' && $value === 1 && isset($prior['adults']) && (int) $prior['adults'] > 1
                && $intent->intent === 'unknown') {
                continue;
            }
            $next[$key] = $value;
        }
        $next['intent'] = $intent->intent;

        return $next;
    }
}
