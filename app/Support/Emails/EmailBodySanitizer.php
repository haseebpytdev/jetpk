<?php

namespace App\Support\Emails;

/**
 * Strips unsafe HTML from admin-entered email bodies (I8 manual communication).
 */
class EmailBodySanitizer
{
    public static function stripUnsafeTags(string $html): string
    {
        $patterns = [
            '/<(script|iframe|object|embed|form|input|button|link|meta|style)\b[^>]*>.*?<\/\1>/is',
            '/<(script|iframe|object|embed|form|input|button|link|meta|style)\b[^>]*\/?>/is',
        ];

        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        return $html;
    }

    public static function toSafePlainBody(string $body): string
    {
        return trim(strip_tags(self::stripUnsafeTags($body)));
    }

    public static function toSafeHtmlBody(string $body): string
    {
        $plain = self::toSafePlainBody($body);

        return nl2br(e($plain), false);
    }
}
