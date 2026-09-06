<?php

namespace App\Support\Cms;

/**
 * Normalizes CMS plain text so JSON unicode escapes render as characters.
 */
final class CmsPlainText
{
    public static function decode(mixed $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (! str_contains($text, '\\u') && ! str_contains($text, '\u')) {
            return $text;
        }

        $decoded = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $match): string => self::chr(hexdec($match[1])),
            $text,
        );

        return is_string($decoded) ? $decoded : $text;
    }

    private static function chr(int $codepoint): string
    {
        if ($codepoint <= 0) {
            return '';
        }

        $char = mb_chr($codepoint, 'UTF-8');

        return is_string($char) ? $char : '';
    }
}
