<?php

namespace App\Support\BackOffice;

/**
 * Public browser paths for Laravel admin/staff modules (never loopback origins).
 */
final class BackOfficeLaravelRoutePaths
{
    /** @var array<string, string> */
    private const PATHS = [
        'admin.dashboard' => '/admin/dashboard',
        'admin.bookings' => '/admin/bookings',
        'admin.payments' => '/admin/payments',
        'admin.customers.index' => '/admin/customers',
        'admin.agents' => '/admin/agents',
        'admin.staff' => '/admin/staff',
        'admin.api-settings' => '/admin/api-settings',
        'admin.markups' => '/admin/markups',
        'admin.page-settings.index' => '/admin/page-settings',
        'admin.reports' => '/admin/reports',
        'admin.settings.index' => '/admin/settings',
        'admin.settings.communications.index' => '/admin/settings/communications',
        'admin.support.tickets.index' => '/admin/support/tickets',
        'admin.finance.wallet-audit.index' => '/admin/finance/wallet-audit',
        'admin.agent-deposits.index' => '/admin/agent-deposits',
        'admin.settings.branding.edit' => '/admin/settings/branding',
        'admin.branding' => '/admin/branding',
        'admin.go-live-checklist' => '/admin/go-live-checklist',
        'staff.bookings.index' => '/staff/bookings',
        'staff.support.tickets.index' => '/staff/support/tickets',
        'flights.search' => '/',
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
