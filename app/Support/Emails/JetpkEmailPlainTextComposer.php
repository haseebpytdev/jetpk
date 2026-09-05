<?php

namespace App\Support\Emails;

/**
 * Structured text/plain from email semantics — not HTML table layout.
 */
final class JetpkEmailPlainTextComposer
{
    /**
     * @param  array{
     *   title?: string,
     *   greeting?: string,
     *   message?: string,
     *   facts?: list<array{label: string, value: string}>,
     *   cta_label?: string|null,
     *   cta_url?: string|null,
     *   support_email?: string|null,
     *   support_phone?: string|null,
     *   footer?: string|null
     * }  $parts
     */
    public static function compose(array $parts): string
    {
        $blocks = [];
        foreach (['title', 'greeting', 'message'] as $key) {
            $value = trim((string) ($parts[$key] ?? ''));
            if ($value !== '') {
                $blocks[] = $value;
            }
        }

        $facts = [];
        foreach ($parts['facts'] ?? [] as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $facts[] = $label.': '.$value;
        }
        if ($facts !== []) {
            $blocks[] = implode("\n", $facts);
        }

        $ctaUrl = trim((string) ($parts['cta_url'] ?? ''));
        $ctaLabel = trim((string) ($parts['cta_label'] ?? ''));
        if ($ctaUrl !== '') {
            $blocks[] = ($ctaLabel !== '' ? $ctaLabel."\n" : '').$ctaUrl;
        }

        $support = [];
        $email = trim((string) ($parts['support_email'] ?? ''));
        $phone = trim((string) ($parts['support_phone'] ?? ''));
        if ($email !== '') {
            $support[] = 'Support: '.$email;
        }
        if ($phone !== '') {
            $support[] = 'Phone: '.$phone;
        }
        if ($support !== []) {
            $blocks[] = implode("\n", $support);
        }

        $footer = trim((string) ($parts['footer'] ?? ''));
        if ($footer !== '') {
            $blocks[] = $footer;
        }

        $text = implode("\n\n", $blocks);
        $text = str_replace(["\u{200C}", "\u{00A0}"], ['', ' '], $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
