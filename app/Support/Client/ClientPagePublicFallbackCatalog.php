<?php

namespace App\Support\Client;

/**
 * JetPK public-page fallback content used when no client_page_settings row exists.
 *
 * Values mirror the effective copy rendered on public Blade pages (not master-client defaults).
 */
final class ClientPagePublicFallbackCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function contentFor(string $pageKey): array
    {
        return match ($pageKey) {
            ClientPageKeys::HOME => app(\App\Services\Client\ClientPageContentResolver::class)->defaultHomeContent(),
            ClientPageKeys::FOOTER => app(\App\Services\Client\ClientPageContentResolver::class)->defaultFooterContent(),
            ClientPageKeys::GLOBAL => app(\App\Services\Client\ClientPageContentResolver::class)->defaultGlobalContent(),
            ClientPageKeys::ABOUT => [
                'hero' => [
                    'kicker' => 'About JetPakistan',
                    'title' => 'Cheap flights and secure online booking for Pakistan',
                    'description' => 'JetPakistan helps travellers discover low fares, compare airlines, and complete domestic and international flight bookings online with confidence.',
                ],
                'contact' => [
                    'phone' => '0311 1222427',
                    'email' => 'ota@jetpakistan.pk',
                    'website' => 'https://www.jetpakistan.com',
                    'office' => 'Office No. 220, 2nd Floor, Century Tower, Kalma Chowk, Gulberg III, Lahore',
                ],
            ],
            ClientPageKeys::SUPPORT => [
                'hero' => [
                    'kicker' => 'Support & contact',
                    'title' => 'Flight booking help, 24/7',
                    'description' => 'Get assistance with online ticket booking, fare questions, payments, e-tickets, changes, and online check-in.',
                ],
                'contact' => [
                    'phone' => '0311 1222427',
                    'email' => 'ota@jetpakistan.pk',
                    'whatsapp' => '923111222427',
                    'website' => 'https://www.jetpakistan.com',
                ],
                'form' => [
                    'helper_text' => 'Tell us what you need and our team will respond shortly.',
                ],
            ],
            ClientPageKeys::GROUP_SEARCH => [
                'hero' => [
                    'kicker' => 'Group fares',
                    'title' => 'Search group & series inventory',
                    'description' => 'Browse available group blocks with transparent per-seat pricing.',
                ],
            ],
            ClientPageKeys::BOOKING_LOOKUP => [
                'hero' => [
                    'title' => 'Find your booking',
                    'description' => 'Enter your booking reference and email to view status, payment, and e-ticket.',
                    'help_text' => 'Reference is in your confirmation email (e.g. JPK-2026-004821).',
                ],
            ],
            ClientPageKeys::AGENT_REGISTRATION => [
                'hero' => [
                    'kicker' => 'B2B partners',
                    'title' => 'Register your travel agency',
                    'description' => 'Apply for JetPakistan agency access with markups, reporting, and operational support.',
                    'cta_text' => 'Start application',
                ],
            ],
            ClientPageKeys::TERMS, ClientPageKeys::PRIVACY, ClientPageKeys::FAQ => [
                'content' => [
                    'title' => ClientPageKeys::labels()[$pageKey] ?? $pageKey,
                    'intro' => '',
                    'body' => '',
                ],
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function fieldPathsFor(string $pageKey): array
    {
        $paths = [];
        foreach (ClientPageSectionSchema::sectionsFor($pageKey) as $section) {
            $prefix = $section['key'];
            foreach ($section['fields'] as $field) {
                if ($field === 'items' || $field === 'cards' || $field === 'links') {
                    $paths[] = $prefix.'.'.$field;

                    continue;
                }
                $paths[] = $prefix.'.'.$field;
            }
        }

        $content = self::contentFor($pageKey);
        foreach (self::flattenPaths($content) as $path) {
            if (! in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function flattenPaths(array $data, string $prefix = ''): array
    {
        $paths = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $paths[] = $path;

                    continue;
                }
                $paths = array_merge($paths, self::flattenPaths($value, $path));

                continue;
            }
            $paths[] = $path;
        }

        return $paths;
    }
}
