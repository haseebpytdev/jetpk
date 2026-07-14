<?php

namespace App\Services\Agencies;

use App\Models\AgencySetting;

/**
 * About Us CMS payload in agency_settings.meta.about_us — plain editor and HTML override modes.
 */
class AboutUsContentPresenter
{
    public const META_KEY = 'about_us';

    private const PLAIN_ALLOWED_TAGS = '<p><br><strong><b><em><i><ul><ol><li><h2><h3>';

    private const HTML_OVERRIDE_ALLOWED_TAGS = '<p><br><div><span><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6><a><blockquote><hr><table><thead><tbody><tr><th><td>';

    /**
     * @return array{plain: string, html_override: string, html_active: bool, updated_at: string}
     */
    public function storedPayload(?AgencySetting $settings): array
    {
        $meta = is_array($settings?->meta) ? $settings->meta : [];
        $raw = is_array($meta[self::META_KEY] ?? null) ? $meta[self::META_KEY] : [];

        return [
            'plain' => (string) ($raw['plain'] ?? ''),
            'html_override' => (string) ($raw['html_override'] ?? ''),
            'html_active' => (bool) ($raw['html_active'] ?? false),
            'updated_at' => (string) ($raw['updated_at'] ?? ''),
        ];
    }

    /**
     * @return array{plain: string, html_override: string, html_active: bool, updated_at: string}
     */
    public function presentForAdmin(?AgencySetting $settings): array
    {
        return $this->storedPayload($settings);
    }

    /**
     * @return array{has_custom: bool, mode: ?string, body_html: string}
     */
    public function presentForPublic(?AgencySetting $settings): array
    {
        $stored = $this->storedPayload($settings);

        if ($stored['html_active'] && trim($stored['html_override']) !== '') {
            return [
                'has_custom' => true,
                'mode' => 'html',
                'body_html' => $this->formatHtmlOverrideForDisplay($stored['html_override']),
            ];
        }

        if (trim($stored['plain']) !== '') {
            return [
                'has_custom' => true,
                'mode' => 'plain',
                'body_html' => $this->formatPlainForDisplay($stored['plain']),
            ];
        }

        return [
            'has_custom' => false,
            'mode' => null,
            'body_html' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{plain: string, html_override: string, html_active: bool, updated_at: string}
     */
    public function buildPayloadForStorage(array $input): array
    {
        $htmlActive = filter_var($input['html_active'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'plain' => $this->sanitizePlainForStorage($input['plain'] ?? null),
            'html_override' => $this->sanitizeHtmlOverrideForStorage($input['html_override'] ?? null),
            'html_active' => $htmlActive,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function sanitizePlainForStorage(mixed $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $raw = $this->stripDangerousMarkup($raw);

        if ($raw === strip_tags($raw)) {
            return $raw;
        }

        return strip_tags($raw, self::PLAIN_ALLOWED_TAGS);
    }

    public function formatPlainForDisplay(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        $stored = $this->stripDangerousMarkup($stored);

        if ($stored === strip_tags($stored)) {
            return nl2br(e($stored), false);
        }

        return strip_tags($stored, self::PLAIN_ALLOWED_TAGS);
    }

    public function sanitizeHtmlOverrideForStorage(mixed $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        $raw = $this->stripDangerousMarkup($raw);

        return strip_tags($raw, self::HTML_OVERRIDE_ALLOWED_TAGS);
    }

    public function formatHtmlOverrideForDisplay(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }

        $stored = $this->stripDangerousMarkup($stored);

        return strip_tags($stored, self::HTML_OVERRIDE_ALLOWED_TAGS);
    }

    protected function stripDangerousMarkup(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/?(?:iframe|object|embed)\b[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/\b(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2/i', '', $html) ?? $html;
        $html = preg_replace('/\b(href|src)\s*=\s*javascript:[^\s>]+/i', '', $html) ?? $html;

        return $html;
    }
}
