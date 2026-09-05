<?php

namespace App\Support\Emails;

/**
 * Deterministic content, branding, and URL gates for QA-rendered emails.
 */
final class JetpkEmailQaContentAuditor
{
    /** @var list<string> */
    private const FORBIDDEN_FRAGMENTS = [
        'Asif Travels',
        'Parwaaz',
        'YD Travel',
        'YoursDomain',
        'haseeb-master',
        'placeholder 123',
        'Travel Platform',
    ];

    /**
     * @return array{pass: bool, failures: list<string>, urls: list<string>}
     */
    public function audit(string $subject, string $html, string $plainBody = ''): array
    {
        $failures = [];
        $combined = $subject."\n".$html."\n".$plainBody;

        if (trim($subject) === '') {
            $failures[] = 'empty_subject';
        }
        if (trim(strip_tags($html)) === '') {
            $failures[] = 'empty_body';
        }
        if (! str_contains($combined, 'JetPakistan')) {
            $failures[] = 'jetpakistan_brand_missing';
        }
        if (preg_match('/\{\{\s*[\w.]+\s*\}\}/', $combined) === 1) {
            $failures[] = 'unresolved_placeholder';
        }
        if (preg_match('/<style\b|font-family\s*:|@media\b/i', $plainBody) === 1) {
            $failures[] = 'plain_text_contains_css';
        }
        if (preg_match('/<(html|body|table|div|style)\b/i', $plainBody) === 1) {
            $failures[] = 'plain_text_contains_html';
        }
        if (preg_match('/<script\b|stack\s*trace|sqlstate\[|viewexception|fatal\s+error/i', $html) === 1) {
            $failures[] = 'unsafe_or_internal_error_content';
        }

        foreach (self::FORBIDDEN_FRAGMENTS as $fragment) {
            if (stripos($combined, $fragment) !== false) {
                $failures[] = 'forbidden_branding:'.$fragment;
            }
        }

        $urls = $this->extractUrls($html);

        return [
            'pass' => $failures === [],
            'failures' => array_values(array_unique($failures)),
            'urls' => $urls,
        ];
    }

    /**
     * @return list<string>
     */
    public function extractUrls(string $html): array
    {
        preg_match_all('/\b(?:href|src)\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i', $html, $matches);

        return array_values(array_unique(array_map(
            static fn (string $url): string => html_entity_decode(trim($url)),
            $matches[2] ?? [],
        )));
    }
}
