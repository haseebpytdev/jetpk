<?php

namespace App\Services\Ai;

/**
 * Distinguishes commercial travel intent from general informational questions.
 */
final class AiCommercialIntentClassifier
{
    public function isSupportAssistanceIntent(string $message): bool
    {
        $lower = mb_strtolower(trim($message));
        if ($lower === '' || $this->isSimpleGreeting($lower)) {
            return false;
        }

        if ($this->isInformationalOnly($lower)) {
            return false;
        }

        $patterns = [
            '/\b(i need help|i\'d like some help|can you help me|could you help me|need assistance|i need assistance)\b/u',
            '/\b(help me|please help)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower) === 1) {
                return true;
            }
        }

        return false;
    }

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

    public function isBookingHelpIntent(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        return preg_match(
            '/\b(existing\s+)?(booking|reservation)\b|\blook\s*up\s+my\s+booking\b|\bmy\s+booking\s+reference\b|\bhelp with an existing booking\b/u',
            $lower,
        ) === 1;
    }

    public function isSimpleGreeting(string $message): bool
    {
        $lower = mb_strtolower(trim($message));

        return preg_match('/^(hi|hello|hey|salam|assalam|assalamu alaikum|good morning|good evening|good afternoon)[\s!.?]*$/u', $lower) === 1;
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
            'how does a refund work',
            'how do refunds work',
            'do you offer umrah',
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
