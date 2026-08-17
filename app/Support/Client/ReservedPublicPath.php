<?php

namespace App\Support\Client;

/**
 * First-segment public paths that must never be captured by CMS custom-page catch-alls.
 */
final class ReservedPublicPath
{
    /**
     * Reserved single path segments (lowercase). Applies to /{slug} custom pages only.
     *
     * @var list<string>
     */
    public const FIRST_SEGMENT = [
        'admin',
        'agent',
        'staff',
        'customer',
        'account',
        'dashboard',
        'booking',
        'bookings',
        'flights',
        'login',
        'logout',
        'register',
        'password',
        'email',
        'verification',
        'support',
        'contact',
        'api',
        'storage',
        'health',
        'up',
        'dev',
        'devcp',
        'oauth',
        'auth',
        'payment',
        'payments',
        'webhook',
        'webhooks',
        'callbacks',
        'sitemap.xml',
        'robots.txt',
        'lookup-booking',
        'guest',
        'profile',
        'airports',
        'groups',
        'pages',
        'about-us',
        'about',
        'faq',
        'terms',
        'privacy',
        'checkout',
        'assets',
        'build',
        'vendor',
        'client-assets',
        'themes',
        'ui',
        'v1',
        'v2',
        'umrah-groups',
        'request-demo',
        'agent-network',
        'mobile-view',
        'mobile-app-preview',
        'desktop-view',
        'forgot-password',
        'reset-password',
        'dev-cp',
        // Default JetPakistan deployment slug — must not be captured by CMS custom-page show.
        'jetpk',
    ];

    public static function isReservedFirstSegment(string $segment): bool
    {
        $normalized = self::normalizeSegment($segment);

        if ($normalized === '' || in_array($normalized, self::FIRST_SEGMENT, true)) {
            return true;
        }

        $defaultSlug = self::normalizeSegment((string) config(
            'client.canonical_client.slug',
            config('ota_client.slug', 'jetpk'),
        ));

        return $defaultSlug !== '' && $normalized === $defaultSlug;
    }

    public static function normalizeSegment(string $segment): string
    {
        $segment = strtolower(trim($segment));
        $segment = preg_replace('/[^a-z0-9.\-]+/', '-', $segment) ?? '';
        $segment = trim($segment, '-');

        return $segment;
    }

    /**
     * Laravel `where` constraint for custom CMS slugs (production client.custom-page.show).
     */
    public static function customPageSlugConstraint(): string
    {
        $segments = self::FIRST_SEGMENT;
        $defaultSlug = self::normalizeSegment((string) config(
            'client.canonical_client.slug',
            config('ota_client.slug', 'jetpk'),
        ));
        if ($defaultSlug !== '' && ! in_array($defaultSlug, $segments, true)) {
            $segments[] = $defaultSlug;
        }

        $alternation = implode('|', array_map(
            static fn (string $slug): string => preg_quote($slug, '/'),
            $segments,
        ));

        return '(?!(?:'.$alternation.')$)[a-z0-9]+(?:-[a-z0-9]+)*';
    }

    /**
     * @return list<string>
     */
    public static function allFirstSegments(): array
    {
        return self::FIRST_SEGMENT;
    }
}
