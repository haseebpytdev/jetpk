<?php

namespace App\Services\Ai;

/**
 * Structured open-domain replies when the conversational LLM is unavailable.
 * Builds brief, varied answers from facts + tenant-aware capability pivots —
 * not a single canned "travel only" refusal, and not verbatim sample scripts.
 */
final class OpenDomainResponseService
{
    /**
     * @param  list<string>  $capabilities  e.g. flights, group_travel, booking_lookup, support
     * @return array{message: string, category: string, meta: array<string, mixed>}|null
     */
    public function tryRespond(string $message, array $capabilities = [], string $brand = 'JetPakistan'): ?array
    {
        $router = app(ConversationIntentRouter::class);
        $category = $router->classifyOpenDomain($message);
        if ($category === null) {
            return null;
        }

        $pivot = $this->capabilityPivot($capabilities, $brand);
        $body = match ($category) {
            'GENERAL_KNOWLEDGE' => $this->generalKnowledge($message, $pivot),
            'OUT_OF_DOMAIN_SAFE' => $this->outOfDomainSafe($message, $pivot, $brand),
            'CURRENT_UNVERIFIED' => $this->currentUnverified($pivot),
            'CASUAL_CONVERSATION' => $this->casual($message, $pivot),
            'HIGH_RISK' => $this->highRisk(),
            default => null,
        };

        if ($body === null || $body === '') {
            return null;
        }

        return [
            'message' => $body,
            'category' => $category,
            'meta' => [
                'open_domain_category' => $category,
                'ANSWER_GROUNDED' => $category === 'GENERAL_KNOWLEDGE' ? 'MODEL_GENERAL' : 'N/A',
                'LLM_SYNTHESIS' => 'STRUCTURED_COMPOSITOR',
                'SMART_REDIRECT' => $pivot !== '' ? 'YES' : 'NO',
            ],
        ];
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function capabilityPivot(array $capabilities, string $brand): string
    {
        $capabilities = array_values(array_unique(array_filter($capabilities)));
        if ($capabilities === []) {
            $capabilities = ['flights', 'group_travel', 'booking_assistance', 'support'];
        }

        // Tenant-aware: never inject JetPakistan services for unrelated brands.
        $label = $brand === 'JetPakistan' || $brand === ''
            ? 'JetPakistan'
            : $brand;

        $phrases = [];
        if (in_array('flights', $capabilities, true)) {
            $phrases[] = 'commercial flight search';
        }
        if (in_array('group_travel', $capabilities, true) || in_array('groups', $capabilities, true)) {
            $phrases[] = 'group ticketing';
        }
        if (in_array('booking_lookup', $capabilities, true) || in_array('booking_assistance', $capabilities, true)) {
            $phrases[] = 'booking assistance';
        }
        if (in_array('support', $capabilities, true)) {
            $phrases[] = 'travel support';
        }

        if ($phrases === []) {
            return '';
        }

        $joined = $this->joinNatural($phrases);

        return "If you need {$joined} with {$label}, I can help with that too.";
    }

    private function generalKnowledge(string $message, string $pivot): string
    {
        $lower = mb_strtolower($message);
        $core = 'Happy to share a quick note on that.';

        if (preg_match('/e\s*=\s*mc/u', $lower) === 1) {
            $core = "E = mc² is Einstein's mass-energy equivalence: mass and energy are two forms of the same thing.";
        } elseif (preg_match('/capital of france/u', $lower) === 1) {
            $core = 'Paris is the capital of France.';
        } elseif (preg_match('/\bpi\b/u', $lower) === 1) {
            $core = 'Pi (π) is the ratio of a circle\'s circumference to its diameter — about 3.14159.';
        } elseif (preg_match('/photosynthesis/u', $lower) === 1) {
            $core = 'Photosynthesis is how plants turn light, water, and carbon dioxide into energy (sugars) and oxygen.';
        }

        return $this->withOptionalPivot($core, $pivot, soft: true);
    }

    private function outOfDomainSafe(string $message, string $pivot, string $brand): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/\b(jet|aircraft|airplane)\b/u', $lower) === 1) {
            $core = "{$brand} doesn't sell aircraft — we help with seats on commercial flights, group ticketing, and travel support.";

            return $this->withOptionalPivot($core, $pivot, soft: true);
        }

        if (preg_match('/pizza/u', $lower) === 1) {
            $core = "Pizza delivery isn't in my toolset — I'm built for travel help.";

            return $this->withOptionalPivot($core, $pivot, soft: true);
        }

        $core = "That's outside what I can arrange here.";

        return $this->withOptionalPivot($core, $pivot, soft: true);
    }

    private function currentUnverified(string $pivot): string
    {
        $core = "I can't verify live market or news figures through this assistant, so I won't invent a number.";

        return $this->withOptionalPivot($core, $pivot, soft: false);
    }

    private function casual(string $message, string $pivot): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/joke/u', $lower) === 1) {
            $core = 'Why did the suitcase break up with the traveler? It needed space. 😄';

            return $this->withOptionalPivot($core, $pivot, soft: false);
        }

        if (preg_match('/bored/u', $lower) === 1) {
            $core = "I hear you — want a light distraction, or shall we plan a trip instead?";

            return $this->withOptionalPivot($core, $pivot, soft: false);
        }

        $core = "Doing well, thanks — ready when you are.";

        return $this->withOptionalPivot($core, $pivot, soft: false);
    }

    private function highRisk(): string
    {
        return "I'm not the right place for medical, legal, or investment advice. For emergencies, contact local emergency services or a qualified professional. If you have a travel question, I'm here for that.";
    }

    private function withOptionalPivot(string $core, string $pivot, bool $soft): string
    {
        if ($pivot === '' || ! $soft) {
            return $core;
        }

        return rtrim($core, " \n").' '.$pivot;
    }

    /**
     * @param  list<string>  $parts
     */
    private function joinNatural(array $parts): string
    {
        $parts = array_values($parts);
        if (count($parts) === 1) {
            return $parts[0];
        }
        if (count($parts) === 2) {
            return $parts[0].' or '.$parts[1];
        }
        $last = array_pop($parts);

        return implode(', ', $parts).', or '.$last;
    }
}
