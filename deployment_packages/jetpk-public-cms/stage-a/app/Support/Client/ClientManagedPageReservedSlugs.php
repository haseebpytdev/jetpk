<?php

namespace App\Support\Client;

/**
 * Reserved public slugs that custom CMS pages must not collide with.
 */
final class ClientManagedPageReservedSlugs
{
    /** @var list<string> */
    public const RESERVED = [
        'admin',
        'login',
        'register',
        'logout',
        'flights',
        'booking',
        'checkout',
        'payment',
        'support',
        'api',
        'storage',
        'assets',
        'agent',
        'dashboard',
        'about-us',
        'about',
        'faq',
        'terms',
        'privacy',
        'lookup-booking',
        'groups',
        'pages',
        'contact',
        'guest',
        'payment',
        'profile',
        'airports',
        'umrah-groups',
        'request-demo',
        'devcp',
        'ui',
    ];

    public static function isReserved(string $slug): bool
    {
        $normalized = self::normalize($slug);

        return $normalized === '' || in_array($normalized, self::RESERVED, true);
    }

    public static function normalize(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug;
    }

    public static function isValidFormat(string $slug): bool
    {
        $normalized = self::normalize($slug);

        if ($normalized === '' || $normalized !== $slug) {
            return false;
        }

        if (str_contains($slug, '..') || str_contains($slug, '%')) {
            return false;
        }

        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }
}
