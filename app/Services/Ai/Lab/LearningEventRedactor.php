<?php

namespace App\Services\Ai\Lab;

/**
 * Redact PII before learning queue persistence.
 */
final class LearningEventRedactor
{
    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public function redact(array $event): array
    {
        $text = (string) ($event['redacted_user_text'] ?? $event['user_text'] ?? '');
        $text = $this->redactText($text);
        $event['redacted_user_text'] = $text;
        unset($event['user_text'], $event['raw_message']);

        foreach (['passport_numbers', 'payment_information', 'full_pnr_data'] as $forbidden) {
            unset($event[$forbidden]);
        }

        return $event;
    }

    public function redactText(string $text): string
    {
        $text = preg_replace('/\b[A-Z0-9]{6}\b/', '[PNR]', $text) ?? $text;
        $text = preg_replace('/\b[\w.+-]+@[\w.-]+\.\w{2,}\b/', '[EMAIL]', $text) ?? $text;
        $text = preg_replace('/\b(\+92|0)?3\d{9}\b/', '[PHONE]', $text) ?? $text;
        $text = preg_replace('/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', '[CARD]', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    public function assertRedacted(array $event): bool
    {
        $text = (string) ($event['redacted_user_text'] ?? '');
        if ($text === '') {
            return true;
        }

        if (preg_match('/\b[\w.+-]+@[\w.-]+\.\w{2,}\b/', $text)) {
            return false;
        }
        if (preg_match('/\b(\+92|0)?3\d{9}\b/', $text)) {
            return false;
        }

        return true;
    }
}
