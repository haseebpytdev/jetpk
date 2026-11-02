<?php

namespace App\Support\Emails;

/**
 * Converts JetPK HTML email bodies into readable text/plain without CSS/script leak.
 */
final class JetpkEmailPlainTextRenderer
{
    public static function fromHtml(string $html, string $fallback = ''): string
    {
        $working = $html;

        // Drop non-visible blocks before strip_tags (style/script contents otherwise leak).
        $working = preg_replace('/<!--\[if\s[\s\S]*?<!\[endif\]-->/i', '', $working) ?? $working;
        $working = preg_replace('/<!--[\s\S]*?-->/', '', $working) ?? $working;
        $working = preg_replace('/<head\b[^>]*>[\s\S]*?<\/head>/i', '', $working) ?? $working;
        $working = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i', '', $working) ?? $working;
        $working = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $working) ?? $working;
        $working = preg_replace('/<noscript\b[^>]*>[\s\S]*?<\/noscript>/i', '', $working) ?? $working;

        // Structural line breaks before stripping tags.
        $working = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $working) ?? $working;
        $working = preg_replace('/<\/\s*(p|div|h[1-6]|tr|li|table|section|article|header|footer)\s*>/i', "\n", $working) ?? $working;
        $working = preg_replace('/<\s*(p|div|h[1-6]|tr|li|table|section|article|header|footer)\b[^>]*>/i', "\n", $working) ?? $working;
        $working = preg_replace('/<\/\s*td\s*>/i', "\t", $working) ?? $working;
        $working = preg_replace('/<\/\s*th\s*>/i', "\t", $working) ?? $working;

        $text = strip_tags($working);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = preg_replace('/[ \t]{2,}/', ' ', $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : $fallback;
    }
}
