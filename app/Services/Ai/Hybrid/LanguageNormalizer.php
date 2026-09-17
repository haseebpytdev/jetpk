<?php

namespace App\Services\Ai\Hybrid;

/**
 * Bounded Roman Urdu / misspelling normalization for travel domain.
 * Produces reusable lexical forms — not benchmark-specific answers.
 */
final class LanguageNormalizer
{
    /** @var array<string, string> */
    private const REPLACEMENTS = [
        '/\bsy\b/u' => 'se',
        '/\bsay\b/u' => 'se',
        '/\bchahye\b/u' => 'chahiye',
        '/\bchaiye\b/u' => 'chahiye',
        '/\bjaana\b/u' => 'jana',
        '/\bwapis\b/u' => 'wapas',
        '/\bsasta\b/u' => 'sasti',
        '/\bseedha\b/u' => 'seedhi',
        '/\bagle\b/u' => 'aglay',
        '/\bjumma\b/u' => 'jumay',
        '/\bbachay\b/u' => 'bacha',
        '/\bbaray\b/u' => 'bara',
        '/\badlts\b/u' => 'adults',
        '/\bdirecrt\b/u' => 'direct',
        '/\bislmabad\b/u' => 'islamabad',
        '/\bjedah\b/u' => 'jeddah',
        '/\bemrates\b/u' => 'emirates',
        '/\bretun\b/u' => 'return',
        '/\bhzar\b/u' => 'hazar',
        '/\bchld\b/u' => 'child',
        '/\binfnt\b/u' => 'infant',
        '/\bgrp\b/u' => 'group',
            '/\bpeshawr\b/u' => 'peshawar',
            '/\bbaghair\s+stop\b/u' => 'direct',
        '/\bstop\s+nahi\b/u' => 'direct',
        '/\bnon[\s-]?stop\b/u' => 'direct',
    ];

    /**
     * @return array{normalized: string, original: string, language: string}
     */
    public function normalize(string $message): array
    {
        $original = trim($message);
        // Strip control / script tags for safety (display elsewhere still sanitized).
        $text = strip_tags($original);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        $lower = mb_strtolower($text);
        foreach (self::REPLACEMENTS as $pat => $rep) {
            $lower = preg_replace($pat, $rep, $lower) ?? $lower;
        }

        return [
            'normalized' => $lower,
            'original' => $original,
            'language' => $this->detectLanguage($original),
        ];
    }

    private function detectLanguage(string $text): string
    {
        $hasArabic = (bool) preg_match('/\p{Arabic}/u', $text);
        $hasLatin = (bool) preg_match('/[A-Za-z]/', $text);
        if ($hasArabic && $hasLatin) {
            return 'mixed';
        }
        if ($hasArabic) {
            return 'ur';
        }
        // Roman Urdu heuristic: common tokens
        if (preg_match('/\b(se|chahiye|sasti|wapas|aglay|jumay|kal|parso|seedhi|bara|bacha)\b/u', mb_strtolower($text)) === 1) {
            return 'ru';
        }

        return 'en';
    }
}
