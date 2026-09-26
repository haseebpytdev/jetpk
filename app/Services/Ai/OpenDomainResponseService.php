<?php

namespace App\Services\Ai;

/**
 * Deterministic resilience fallback ONLY when the conversational model is
 * unavailable or returns invalid output. Must not intercept healthy-model turns.
 */
final class OpenDomainResponseService
{
    /**
     * Classify then compose a fallback reply. Prefer fallbackForCategory when
     * the router already classified the turn.
     *
     * @param  list<string>  $capabilities
     * @return array{message: string, category: string, meta: array<string, mixed>}|null
     */
    public function tryRespond(string $message, array $capabilities = [], string $brand = 'JetPakistan'): ?array
    {
        $category = app(ConversationIntentRouter::class)->classifyOpenDomain($message);
        if ($category === null) {
            return null;
        }

        return $this->fallbackForCategory($message, $category, $capabilities, $brand);
    }

    /**
     * @param  list<string>  $capabilities
     * @return array{message: string, category: string, meta: array<string, mixed>}|null
     */
    public function fallbackForCategory(
        string $message,
        string $category,
        array $capabilities = [],
        string $brand = 'JetPakistan',
    ): ?array {
        $pivot = $this->capabilityPivot($capabilities, $brand);
        $body = match ($category) {
            'GENERAL_KNOWLEDGE' => $this->generalKnowledge($message, $pivot),
            'OUT_OF_DOMAIN_SAFE' => $this->outOfDomainSafe($message, $pivot, $brand, $capabilities),
            'CURRENT_UNVERIFIED' => $this->currentUnverified($message, $pivot),
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
                'LLM_SYNTHESIS' => 'FALLBACK_STRUCTURED',
                'OPEN_DOMAIN_FALLBACK' => 'YES',
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
            // Only invent default JetPakistan capability set for the JetPakistan brand.
            if (strcasecmp($brand, 'JetPakistan') === 0) {
                $capabilities = ['flights', 'group_travel', 'booking_assistance', 'support'];
            } else {
                return '';
            }
        }

        $label = $brand !== '' ? $brand : 'this assistant';

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
            $phrases[] = 'support';
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
        $core = null;

        if (preg_match('/e\s*=\s*mc/u', $lower) === 1) {
            $core = "E = mc² is Einstein's mass-energy equivalence: mass and energy are two forms of the same thing.";
        } elseif (preg_match('/capital of france/u', $lower) === 1) {
            $core = 'Paris is the capital of France.';
        } elseif (preg_match('/capital of japan/u', $lower) === 1) {
            $core = 'Tokyo is the capital of Japan.';
        } elseif (preg_match('/\bpi\b/u', $lower) === 1) {
            $core = 'Pi (π) is the ratio of a circle\'s circumference to its diameter — about 3.14159.';
        } elseif (preg_match('/photosynthesis/u', $lower) === 1) {
            $core = 'Photosynthesis is how plants turn light, water, and carbon dioxide into energy (sugars) and oxygen.';
        } elseif (preg_match('/\bgravity\b/u', $lower) === 1) {
            $core = 'Gravity is the attractive force between masses — it keeps planets in orbit and gives objects weight on Earth.';
        } elseif (preg_match('/\bwhat is an api\b|\bexplain what an api is\b|\bwhat(\'s| is) an api\b/u', $lower) === 1) {
            $core = 'An API (Application Programming Interface) is a defined way for software systems to talk to each other — requesting data or actions through agreed endpoints and formats.';
        } elseif (preg_match('/\bundefined\b/u', $lower) === 1) {
            $core = 'In C, undefined behavior means the language standard does not define what the program must do for that case — compilers may assume it never happens.';
        } elseif (preg_match('/\bnull hypothesis\b|\bhypothesis in statistics\b/u', $lower) === 1) {
            $core = 'In statistics, the null hypothesis is the default claim of no effect or no difference that a test tries to reject with evidence.';
        }

        // Transparent resilience — do not pretend a canned filler is a complete answer.
        if ($core === null) {
            $core = "I couldn't produce a reliable general answer just now. Ask again, or I can help with JetPakistan flights and bookings.";

            return $core;
        }

        return $this->withOptionalPivot($core, $pivot, true);
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function outOfDomainSafe(string $message, string $pivot, string $brand, array $capabilities): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/\b(jet|aircraft|airplane)\b/u', $lower) === 1) {
            $core = "{$brand} doesn't sell aircraft.";

            return $this->withOptionalPivot($core, $pivot, true);
        }

        if (preg_match('/pizza/u', $lower) === 1) {
            $core = "Pizza delivery isn't in my toolset.";

            return $this->withOptionalPivot($core, $pivot, true);
        }

        $core = "That's outside what I can arrange here.";

        return $this->withOptionalPivot($core, $pivot, true);
    }

    private function currentUnverified(string $message, string $pivot): string
    {
        $topic = app(ConversationIntentRouter::class)->classifyCurrentTopic($message);
        $core = match ($topic) {
            'weather' => "I don't have an approved live weather source available here, so I can't verify the current conditions.",
            'news' => "I don't have an approved live news source available here, so I can't verify today's news.",
            'market' => "I don't have an approved live market-data source available here, so I can't verify the current price.",
            'sports' => "I don't have an approved live sports-data source available here, so I can't verify the current result or score.",
            default => "I don't have an approved live data source for that request, so I can't verify the current information.",
        };

        return $this->withOptionalPivot($core, $pivot, false);
    }

    private function casual(string $message, string $pivot): string
    {
        $lower = mb_strtolower($message);

        if (preg_match('/joke/u', $lower) === 1) {
            $core = 'Why did the suitcase break up with the traveler? It needed space.';

            return $this->withOptionalPivot($core, $pivot, false);
        }

        if (preg_match('/bored/u', $lower) === 1) {
            $core = 'I hear you — want a light distraction, or shall we plan a trip instead?';

            return $this->withOptionalPivot($core, $pivot, false);
        }

        $core = 'Doing well, thanks — ready when you are.';

        return $this->withOptionalPivot($core, $pivot, false);
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
