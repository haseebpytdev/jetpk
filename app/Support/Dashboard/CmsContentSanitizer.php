<?php

namespace App\Support\Dashboard;

/**
 * Strips unsafe HTML/scripts from CMS content for dashboard read-only previews.
 */
final class CmsContentSanitizer
{
    public static function sanitizeText(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $text = strip_tags((string) $html);
        $text = preg_replace('/javascript\s*:/i', '', $text) ?? $text;
        $text = preg_replace('/on\w+\s*=/i', '', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    /**
     * @return array<string, mixed>
     */
    public static function structuredContent(?string $html, ?string $excerpt = null): array
    {
        $body = self::sanitizeText($html);
        if ($body === '' && $excerpt !== null) {
            $body = self::sanitizeText($excerpt);
        }

        return [
            'format' => 'plain_text',
            'body' => $body,
            'previewOnly' => true,
            'containsHtml' => false,
        ];
    }

    public static function isSafe(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return ! preg_match('/<script|javascript:|on\w+\s*=/i', $value);
    }
}
