<?php

namespace App\Support\BackOffice;

/**
 * Public browser paths for Laravel admin/staff modules (never loopback origins).
 *
 * Used by the Next back-office shell to deep-link into Blade mutation surfaces
 * without rewriting those modules into the read-only dashboard API.
 */
final class BackOfficeLaravelRoutePaths
{
    /** @var array<string, string> */
    private const PATHS = [
        'admin.settings.index' => '/admin/settings',
        'admin.settings.branding.edit' => '/admin/settings/branding',
        'admin.settings.homepage.edit' => '/admin/settings/homepage',
        'admin.settings.homepage-featured-fares.index' => '/admin/settings/homepage-featured-fares',
        'admin.settings.media.index' => '/admin/settings/media',
        'admin.settings.login-otp.edit' => '/admin/settings/login-otp',
        'admin.settings.ai-assistant.show' => '/admin/settings/ai-assistant',
        'admin.settings.communications.index' => '/admin/settings/communications',
        'admin.api-settings' => '/admin/api-settings',
        'admin.cms-pages.index' => '/admin/cms-pages',
        'admin.page-settings.index' => '/admin/page-settings',
        'admin.seo.overview' => '/admin/seo',
        'admin.customer-queries.index' => '/admin/customer-queries',
        'admin.staff' => '/admin/staff',
        'admin.markups' => '/admin/markups',
        'admin.group-ticketing.index' => '/admin/group-ticketing',
        'admin.go-live-checklist' => '/admin/go-live-checklist',
        'admin.bookings' => '/admin/bookings',
        'admin.users.index' => '/admin/users',
        'admin.support.tickets.index' => '/admin/support/tickets',
    ];

    /**
     * @param  array<string, string|null>  $params
     */
    public static function pathFor(string $routeName, array $params = []): ?string
    {
        $base = self::PATHS[$routeName] ?? null;
        if ($base === null) {
            return null;
        }

        $filtered = array_filter(
            $params,
            static fn ($value): bool => $value !== null && $value !== '',
        );

        if ($filtered === []) {
            return $base;
        }

        return $base.'?'.http_build_query($filtered);
    }

    /**
     * @param  array<string, string|null>  $params
     */
    public static function publicPathFromRoute(string $routeName, array $params = []): ?string
    {
        $path = self::pathFor($routeName, $params);
        if ($path !== null) {
            return $path;
        }

        try {
            $filtered = array_filter(
                $params,
                static fn ($value): bool => $value !== null && $value !== '',
            );

            return self::stripToPublicPath(route($routeName, $filtered));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function stripToPublicPath(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}
