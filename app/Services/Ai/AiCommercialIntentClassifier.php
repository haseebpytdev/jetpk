<?php

namespace App\Services\Ai;

/**
 * Distinguishes commercial travel intent from general informational questions.
 */
final class AiCommercialIntentClassifier
{
    public function isCommercialTravelIntent(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if ($this->isInformationalOnly($lower)) {
            return false;
        }

        $patterns = [
            '/\b(find|search|need|looking|book|ticket|fare|flight|flights|travel|umrah|package)\b/u',
            '/\b(se|sy|say|to|from)\b/u',
            '/\b([a-z]{3})\s*(?:to|se|→)\s*([a-z]{3})\b/u',
            '/\b(lahore|karachi|islamabad|dubai|dubay|jeddah|london|doha)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isInformationalOnly(string $lower): bool
    {
        $infoPhrases = [
            'what is jetpakistan',
            'what services do you offer',
            'how does guest booking work',
            'how can i contact',
            'support contact',
            'baggage allowance',
            'payment help',
        ];

        foreach ($infoPhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return preg_match('/^(how|what|where|when)\b/u', $lower) === 1
            && ! preg_match('/\b(flight|ticket|fare|travel|book)\b/u', $lower);
    }
}
