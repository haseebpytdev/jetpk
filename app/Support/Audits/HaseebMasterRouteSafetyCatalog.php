<?php

namespace App\Support\Audits;

use App\Support\Client\ReservedClientPreviewSlugs;

/**
 * Curated MC-5C route safety matrix for the default haseeb-master deployment.
 *
 * Read-only registry + collision checks — no supplier calls, no DB writes.
 */
final class HaseebMasterRouteSafetyCatalog
{
    public const DEFAULT_CLIENT_SLUG = 'haseeb-master';

    /**
     * Named production routes that must exist at unprefixed URLs.
     *
     * @return list<array{section: string, route: string, method: string, expected_uri: string, notes: string}>
     */
    public static function requiredProductionRoutes(): array
    {
        return [
            // 1. Public homepage
            ['section' => 'Public homepage', 'route' => 'home', 'method' => 'GET', 'expected_uri' => '/', 'notes' => 'Root OTA homepage'],

            // 2. Public flight search
            ['section' => 'Public flight search', 'route' => 'flights.results', 'method' => 'GET', 'expected_uri' => '/flights/results', 'notes' => 'Results page'],
            ['section' => 'Public flight search', 'route' => 'flights.results.data', 'method' => 'GET', 'expected_uri' => '/flights/results/data', 'notes' => 'Results JSON'],
            ['section' => 'Public flight search', 'route' => 'flights.results.search', 'method' => 'GET', 'expected_uri' => '/flights/results/search', 'notes' => 'Results search JSON'],
            ['section' => 'Public flight search', 'route' => 'airports.search', 'method' => 'GET', 'expected_uri' => '/airports/search', 'notes' => 'Airport autocomplete'],

            // 3. Results / select / checkout
            ['section' => 'Flight checkout flow', 'route' => 'flights.return-options', 'method' => 'GET', 'expected_uri' => '/flights/return-options', 'notes' => 'Return options'],
            ['section' => 'Flight checkout flow', 'route' => 'flights.details', 'method' => 'GET', 'expected_uri' => '/flights/details/{id}', 'notes' => 'Offer details (parametric URI)'],
            ['section' => 'Flight checkout flow', 'route' => 'booking.passengers', 'method' => 'GET|POST', 'expected_uri' => '/booking/passengers', 'notes' => 'Passenger step'],
            ['section' => 'Flight checkout flow', 'route' => 'booking.review', 'method' => 'GET|POST', 'expected_uri' => '/booking/review', 'notes' => 'Review step'],
            ['section' => 'Flight checkout flow', 'route' => 'booking.confirmation', 'method' => 'GET', 'expected_uri' => '/booking/confirmation', 'notes' => 'Confirmation'],

            // 4. Booking lookup / support
            ['section' => 'Booking lookup & support', 'route' => 'booking.lookup', 'method' => 'GET', 'expected_uri' => '/lookup-booking', 'notes' => 'Guest lookup form'],
            ['section' => 'Booking lookup & support', 'route' => 'lookup-booking.submit', 'method' => 'POST', 'expected_uri' => '/lookup-booking', 'notes' => 'Guest lookup POST'],
            ['section' => 'Booking lookup & support', 'route' => 'support', 'method' => 'GET', 'expected_uri' => '/support', 'notes' => 'Support form'],

            // 5. Auth
            ['section' => 'Auth', 'route' => 'login', 'method' => 'GET', 'expected_uri' => '/login', 'notes' => 'Login form'],
            ['section' => 'Auth', 'route' => 'register', 'method' => 'GET', 'expected_uri' => '/register', 'notes' => 'Registration form'],
            ['section' => 'Auth', 'route' => 'password.request', 'method' => 'GET', 'expected_uri' => '/forgot-password', 'notes' => 'Forgot password'],
            ['section' => 'Auth', 'route' => 'password.reset', 'method' => 'GET', 'expected_uri' => '/reset-password/{token}', 'notes' => 'Reset password (parametric URI)'],

            // 6. Admin
            ['section' => 'Admin dashboard', 'route' => 'admin.dashboard', 'method' => 'GET', 'expected_uri' => '/admin', 'notes' => 'Admin home'],
            ['section' => 'Admin dashboard', 'route' => 'admin.bookings', 'method' => 'GET', 'expected_uri' => '/admin/bookings', 'notes' => 'Admin bookings index'],

            // 7. Agent
            ['section' => 'Agent dashboard', 'route' => 'agent.dashboard', 'method' => 'GET', 'expected_uri' => '/agent', 'notes' => 'Agent home'],
            ['section' => 'Agent dashboard', 'route' => 'agent.bookings.index', 'method' => 'GET', 'expected_uri' => '/agent/bookings', 'notes' => 'Agent bookings index'],

            // 8. Staff
            ['section' => 'Staff dashboard', 'route' => 'staff.dashboard', 'method' => 'GET', 'expected_uri' => '/staff', 'notes' => 'Staff home'],
            ['section' => 'Staff dashboard', 'route' => 'staff.bookings.index', 'method' => 'GET', 'expected_uri' => '/staff/bookings', 'notes' => 'Staff bookings index'],

            // 9. Customer
            ['section' => 'Customer dashboard', 'route' => 'dashboard', 'method' => 'GET', 'expected_uri' => '/dashboard', 'notes' => 'Auth redirect hub'],
            ['section' => 'Customer dashboard', 'route' => 'customer.dashboard', 'method' => 'GET', 'expected_uri' => '/customer', 'notes' => 'Customer portal home'],
            ['section' => 'Customer dashboard', 'route' => 'customer.bookings.index', 'method' => 'GET', 'expected_uri' => '/customer/bookings', 'notes' => 'Customer bookings'],

            // 10. Dev CP
            ['section' => 'Dev CP', 'route' => 'dev.cp.login', 'method' => 'GET', 'expected_uri' => '/dev/cp/login', 'notes' => 'Dev CP login'],
            ['section' => 'Dev CP', 'route' => 'dev.cp.index', 'method' => 'GET', 'expected_uri' => '/dev/cp', 'notes' => 'Dev CP overview'],
            ['section' => 'Dev CP', 'route' => 'dev.cp.clients.index', 'method' => 'GET', 'expected_uri' => '/dev/cp/clients', 'notes' => 'Client profiles'],

            // 11. Group ticketing
            ['section' => 'Group ticketing', 'route' => 'group-ticketing.search', 'method' => 'GET', 'expected_uri' => '/groups/search', 'notes' => 'Public group search'],
            ['section' => 'Group ticketing', 'route' => 'group-ticketing.show', 'method' => 'GET', 'expected_uri' => '/groups/package/{inventory}', 'notes' => 'Package detail (parametric URI)'],
        ];
    }

    /**
     * Master preview routes that must remain registered (MC-4/5A, MC-7B).
     *
     * @return list<array{section: string, route: string, method: string, expected_uri: string, notes: string}>
     */
    public static function requiredPreviewRoutes(): array
    {
        $routes = [
            ['section' => 'Client preview', 'route' => 'client.preview.root', 'method' => 'GET', 'expected_uri' => '/{clientSlug}', 'notes' => 'Preview root redirect'],
        ];

        if (! config('client_route_parity.enabled', true)) {
            $routes = array_merge($routes, [
                ['section' => 'Client preview', 'route' => 'client.preview.home', 'method' => 'GET', 'expected_uri' => '/{clientSlug}/home', 'notes' => 'Preview home placeholder'],
                ['section' => 'Client preview', 'route' => 'client.preview.login', 'method' => 'GET', 'expected_uri' => '/{clientSlug}/login', 'notes' => 'Preview login placeholder'],
                ['section' => 'Client preview', 'route' => 'client.preview.admin', 'method' => 'GET', 'expected_uri' => '/{clientSlug}/admin', 'notes' => 'Preview admin placeholder'],
                ['section' => 'Client preview', 'route' => 'client.preview.staff', 'method' => 'GET', 'expected_uri' => '/{clientSlug}/staff', 'notes' => 'Preview staff placeholder'],
                ['section' => 'Client preview', 'route' => 'client.preview.agent', 'method' => 'GET', 'expected_uri' => '/{clientSlug}/agent', 'notes' => 'Preview agent placeholder'],
            ]);
        }

        return $routes;
    }

    /**
     * Default deployment slug prefixed paths that must render parity routes (MC-7B).
     *
     * @return list<array{section: string, route: string, method: string, uri: string, parity_route: string, notes: string}>
     */
    public static function defaultSlugParityChecks(string $clientSlug = self::DEFAULT_CLIENT_SLUG): array
    {
        return [
            [
                'section' => 'Default slug parity',
                'route' => 'client.preview.root',
                'method' => 'GET',
                'uri' => '/'.$clientSlug,
                'parity_route' => 'client.parity.home.alias',
                'notes' => 'Default slug root → prefixed home',
            ],
            [
                'section' => 'Default slug parity',
                'route' => 'client.parity.home.alias',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/home',
                'parity_route' => 'client.parity.home.alias',
                'notes' => 'Default slug /home renders production home',
            ],
            [
                'section' => 'Default slug parity',
                'route' => 'client.parity.login',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/login',
                'parity_route' => 'client.parity.login',
                'notes' => 'Default slug /login renders production login',
            ],
            [
                'section' => 'Default slug parity',
                'route' => 'client.parity.admin.dashboard',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/admin',
                'parity_route' => 'client.parity.admin.dashboard',
                'notes' => 'Default slug /admin uses admin auth middleware',
            ],
        ];
    }

    /**
     * Default deployment slug prefixed paths that must redirect to production URLs (MC-5B).
     *
     * @return list<array{section: string, route: string, method: string, uri: string, expected_target: string, notes: string}>
     */
    public static function defaultSlugRedirectChecks(string $clientSlug = self::DEFAULT_CLIENT_SLUG): array
    {
        return [
            [
                'section' => 'Default slug redirect',
                'route' => 'client.preview.root',
                'method' => 'GET',
                'uri' => '/'.$clientSlug,
                'expected_target' => '/',
                'notes' => 'Default slug root → production home',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'client.preview.home',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/home',
                'expected_target' => '/',
                'notes' => 'Default slug /home → production home',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'client.preview.admin',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/admin',
                'expected_target' => '/admin',
                'notes' => 'Default slug /admin → production admin',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'client.preview.login',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/login',
                'expected_target' => '/login',
                'notes' => 'Default slug /login → production login',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'register',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/register',
                'expected_target' => '/register',
                'notes' => 'Default slug /register → production register',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'client.preview.staff',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/staff',
                'expected_target' => '/staff',
                'notes' => 'Default slug /staff → production staff',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'client.preview.agent',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/agent',
                'expected_target' => '/agent',
                'notes' => 'Default slug /agent → production agent',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'customer.dashboard',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/customer',
                'expected_target' => '/customer',
                'notes' => 'Default slug /customer → production customer',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'admin.bookings',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/admin/bookings',
                'expected_target' => '/admin/bookings',
                'notes' => 'Default slug /admin/bookings → production admin bookings',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'booking.lookup',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/lookup-booking',
                'expected_target' => '/lookup-booking',
                'notes' => 'Default slug /lookup-booking → production lookup',
            ],
            [
                'section' => 'Default slug redirect',
                'route' => 'group-ticketing.search',
                'method' => 'GET',
                'uri' => '/'.$clientSlug.'/groups/search',
                'expected_target' => '/groups/search',
                'notes' => 'Default slug /groups/search → production group search',
            ],
        ];
    }

    /**
     * Default slug behavior checks — alias-only redirects to canonical root paths.
     *
     * @return list<array{section: string, route: string, method: string, uri: string, expected_target: string, notes: string}>
     */
    public static function defaultSlugChecks(string $clientSlug = self::DEFAULT_CLIENT_SLUG): array
    {
        return self::defaultSlugRedirectChecks($clientSlug);
    }

    /**
     * Production URIs that must not resolve to client.preview.* routes.
     *
     * @return list<array{section: string, route: string, method: string, uri: string, expected_route_prefix: string, notes: string}>
     */
    public static function productionRouteMatchChecks(): array
    {
        return [
            ['section' => 'Route collision guard', 'route' => 'home', 'method' => 'GET', 'uri' => '/', 'expected_route_prefix' => '', 'notes' => 'Root must not match preview slug route'],
            ['section' => 'Route collision guard', 'route' => 'admin.dashboard', 'method' => 'GET', 'uri' => '/admin', 'expected_route_prefix' => 'admin.', 'notes' => '/admin is admin portal'],
            ['section' => 'Route collision guard', 'route' => 'login', 'method' => 'GET', 'uri' => '/login', 'expected_route_prefix' => 'login', 'notes' => '/login is auth route'],
            ['section' => 'Route collision guard', 'route' => 'dev.cp.index', 'method' => 'GET', 'uri' => '/dev/cp', 'expected_route_prefix' => 'dev.cp.', 'notes' => '/dev/cp is Dev CP'],
        ];
    }

    /**
     * Reserved first-path segments that must not bind as {clientSlug}.
     *
     * @return list<array{section: string, route: string, method: string, uri: string, notes: string}>
     */
    public static function reservedSlugCollisionChecks(): array
    {
        $checks = [];
        foreach (ReservedClientPreviewSlugs::ALL as $slug) {
            $checks[] = [
                'section' => 'Reserved slug guard',
                'route' => '-',
                'method' => 'GET',
                'uri' => '/'.$slug.'/home',
                'notes' => "Reserved slug `{$slug}` must not match client.preview.*",
            ];
        }

        return $checks;
    }

    /**
     * Static/public asset path prefixes guarded by reserved slug list (MC-5A).
     *
     * @return list<array{section: string, route: string, method: string, uri: string, notes: string}>
     */
    public static function staticAssetPathChecks(): array
    {
        return [
            ['section' => 'Static asset paths', 'route' => '-', 'method' => 'GET', 'uri' => '/css/ota-public.css', 'notes' => 'CSS served from public/; slug `css` reserved'],
            ['section' => 'Static asset paths', 'route' => '-', 'method' => 'GET', 'uri' => '/images/logo.svg', 'notes' => 'Images under public/; slug `images` reserved'],
            ['section' => 'Static asset paths', 'route' => '-', 'method' => 'GET', 'uri' => '/storage/branding/logo.svg', 'notes' => 'Storage symlink; slug `storage` reserved'],
            ['section' => 'Static asset paths', 'route' => '-', 'method' => 'GET', 'uri' => '/client-assets/haseeb-master/logo/logo.svg', 'notes' => 'Client assets; slug `client-assets` reserved'],
            ['section' => 'Static asset paths', 'route' => '-', 'method' => 'GET', 'uri' => '/themes/frontend/v1-classic/app.css', 'notes' => 'Theme assets; slug `themes` reserved'],
        ];
    }

    /**
     * Minimum named route counts for wildcard dashboard areas.
     *
     * @return list<array{section: string, route: string, method: string, uri: string, prefix: string, minimum: int, notes: string}>
     */
    public static function dashboardRouteInventoryChecks(): array
    {
        return [
            ['section' => 'Admin route inventory', 'route' => 'admin.*', 'method' => '-', 'uri' => '/admin/*', 'prefix' => 'admin.', 'minimum' => 20, 'notes' => 'Admin area has registered routes'],
            ['section' => 'Agent route inventory', 'route' => 'agent.*', 'method' => '-', 'uri' => '/agent/*', 'prefix' => 'agent.', 'minimum' => 5, 'notes' => 'Agent area has registered routes'],
            ['section' => 'Staff route inventory', 'route' => 'staff.*', 'method' => '-', 'uri' => '/staff/*', 'prefix' => 'staff.', 'minimum' => 5, 'notes' => 'Staff area has registered routes'],
            ['section' => 'Customer route inventory', 'route' => 'customer.*', 'method' => '-', 'uri' => '/customer/*', 'prefix' => 'customer.', 'minimum' => 3, 'notes' => 'Customer area has registered routes'],
            ['section' => 'Dev CP route inventory', 'route' => 'dev.cp.*', 'method' => '-', 'uri' => '/dev/cp/*', 'prefix' => 'dev.cp.', 'minimum' => 10, 'notes' => 'Dev CP area has registered routes'],
        ];
    }
}
