<?php

namespace App\Support\Client;

/**
 * Strict allowlist HTML sanitizer for CMS legal and rich-text content.
 */
final class ClientSafeHtmlSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'h2', 'h3', 'h4', 'blockquote', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $stripped = preg_replace('/<(script|style|iframe|object|embed|form|input|button)[^>]*>.*?<\/\1>/is', '', $decoded) ?? $decoded;
        $stripped = preg_replace('/<(script|style|iframe|object|embed|form|input|button)[^>]*\/?>/i', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+on[a-z]+\s*=\s*(["\']).*?\1/i', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+on[a-z]+\s*=\s*[^\s>]+/i', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\s+(href|src)\s*=\s*(["\'])\s*(javascript:|data:)[^"\']*\2/i', '', $stripped) ?? $stripped;

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';

        return trim(strip_tags($stripped, $allowed));
    }

    public static function isSafe(?string $html): bool
    {
        if ($html === null || trim($html) === '') {
            return true;
        }

        $unsafe = [
            '<script', '<style', '<iframe', '<object', '<embed', '<form', '<input', '<button',
            'javascript:', 'data:', ' on',
        ];
        $lower = strtolower($html);
        foreach ($unsafe as $token) {
            if (str_contains($lower, $token)) {
                return false;
            }
        }

        return self::sanitize($html) === trim($html) || self::sanitize($html) !== '';
    }
}
