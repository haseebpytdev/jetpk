<?php

namespace App\Support\Emails;

/**
 * Converts HTML email bodies into human-readable text/plain without CSS/HTML scaffolding.
 */
final class HtmlEmailPlainTextConverter
{
    public static function fromHtml(string $html, string $fallback = ''): string
    {
        $working = $html;
        $working = preg_replace('#<head\b[^>]*>.*?</head>#is', '', $working) ?? $working;
        $working = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $working) ?? $working;
        $working = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $working) ?? $working;
        $working = preg_replace('#<!--\[if[^\]]*\]>.*?<!\[endif\]-->#is', '', $working) ?? $working;
        $working = preg_replace('#<!--.*?-->#s', '', $working) ?? $working;
        $working = preg_replace('#<(br|br/|br /|/p|/div|/tr|/h[1-6])>#i', "\n", $working) ?? $working;
        $working = preg_replace('#</li>#i', "\n", $working) ?? $working;
        $text = html_entity_decode(strip_tags($working), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return trim($fallback);
        }

        if (preg_match('/[{};]|@media\b|font-family\s*:/i', $text) === 1 && strlen($text) > 400) {
            $strippedCss = preg_replace('/[^{}\n]*\{[^}]*\}/s', '', $text) ?? $text;
            $strippedCss = trim($strippedCss);
            if ($strippedCss !== '') {
                $text = $strippedCss;
            }
        }

        return $text;
    }
}
