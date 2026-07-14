<?php

namespace App\Support\Client;

/**
 * Route prefixes that must not be treated as client preview slugs (MC-5A).
 */
final class ReservedClientPreviewSlugs
{
    /**
     * @var list<string>
     */
    public const ALL = [
        'admin',
        'staff',
        'agent',
        'dev',
        'devcp',
        'dev-cp',
        'login',
        'register',
        'booking',
        'bookings',
        'groups',
        'api',
        'storage',
        'css',
        'js',
        'images',
        'assets',
        'build',
        'vendor',
        'client-assets',
        'themes',
        'v1',
        'v2',
        'ui',
    ];

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), self::ALL, true);
    }

    public static function routeParameterConstraint(): string
    {
        $alternation = implode('|', array_map(
            static fn (string $slug): string => preg_quote($slug, '/'),
            self::ALL,
        ));

        return '(?!^(?:'.$alternation.')$)[a-z0-9][a-z0-9\-]*';
    }
}
