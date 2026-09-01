<?php

namespace App\Services\Ai\Hybrid;

use App\Data\Ai\TravelIntent;

/**
 * Template responses — no LLM required for common search/knowledge phrasing.
 */
final class ResponseTemplateService
{
    /**
     * @param  array<string, mixed>  $toolMeta
     */
    public function flightFound(TravelIntent $intent, int $count, ?string $language = 'en'): string
    {
        $od = ($intent->origin ?? '?').' to '.($intent->destination ?? '?');
        $date = $intent->departDate ?? 'your dates';
        if ($language === 'ru') {
            return $count > 0
                ? "Maine {$od} ke liye {$date} par {$count} options dhunday."
                : "In dates par {$od} ke liye koi option nahi mila.";
        }
        if ($language === 'ur') {
            return $count > 0
                ? "{$od} کے لیے {$date} پر {$count} اختیارات ملے۔"
                : "ان تاریخوں پر {$od} کے لیے کوئی پرواز نہیں ملی۔";
        }

        return $count > 0
            ? "I found {$count} options from {$od} for {$date}."
            : "I couldn't find options from {$od} for {$date}.";
    }

    public function clarification(string $message): string
    {
        return $message;
    }

    public function handoff(): string
    {
        return 'Connecting you to a JetPakistan support agent. Please share any booking reference if you have one.';
    }

    public function knowledgeEmpty(): string
    {
        return 'I can help with Guest Booking, registration, Groups, Saved Travelers, payments, and general cancellation guidance — or connect you to a human.';
    }
}
