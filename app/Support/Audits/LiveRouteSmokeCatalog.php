<?php

namespace App\Support\Audits;

/**
 * Curated F6/F8 live-safe route smoke matrix (read-only GET + validation-only POST).
 */
final class LiveRouteSmokeCatalog
{
    /**
     * Named routes that must exist for F6 smoke readiness.
     *
     * @return list<string>
     */
    public static function registryRouteNames(): array
    {
        return [
            'home',
            'login',
            'register',
            'password.request',
            'booking.lookup',
            'support',
            'flights.results',
            'flights.results.data',
            'flights.results.search',
            'flights.results.offer',
            'flights.details',
            'flights.return-options',
            'flights.return-options.data',
            'booking.passengers',
            'booking.review',
            'booking.confirmation',
            'guest.bookings.show',
            'admin.bookings.data',
            'dev.cp.login',
            'dev.cp.index',
            'dev.cp.users.index',
            'dev.cp.modules.index',
            'dev.cp.companies.index',
            'dev.cp.sabre',
            'dev.cp.health',
            'dev.cp.deployment',
            'dev.cp.security-events.index',
            'dev.cp.group-ticketing',
            'dev.cp.dashboards',
            'admin.dashboard',
            'admin.bookings',
            'admin.bookings.show',
            'admin.bookings.preview',
            'admin.reports.supplier-diagnostics',
            'admin.support.tickets.index',
            'staff.dashboard',
            'staff.bookings.index',
            'staff.support.tickets.index',
            'agent.dashboard',
            'agent.bookings.index',
            'agent.support.tickets.index',
            'customer.dashboard',
            'customer.bookings.index',
            'customer.support.tickets.index',
        ];
    }

    /**
     * Guest/public GET targets (safe on production with --guest-only).
     *
     * @return list<array{label: string, uri: string, accept: string}>
     */
    public static function guestDispatchTargets(): array
    {
        return [
            ['label' => 'home', 'uri' => '/', 'accept' => 'text/html'],
            ['label' => 'login', 'uri' => '/login', 'accept' => 'text/html'],
            ['label' => 'register', 'uri' => '/register', 'accept' => 'text/html'],
            ['label' => 'forgot-password', 'uri' => '/forgot-password', 'accept' => 'text/html'],
            ['label' => 'password-forgot-alias', 'uri' => '/password/forgot', 'accept' => 'text/html'],
            ['label' => 'lookup-booking', 'uri' => '/lookup-booking', 'accept' => 'text/html'],
            ['label' => 'booking-lookup-alias', 'uri' => '/booking-lookup', 'accept' => 'text/html'],
            ['label' => 'support', 'uri' => '/support', 'accept' => 'text/html'],
            ['label' => 'flights-alias', 'uri' => '/flights', 'accept' => 'text/html'],
            ['label' => 'flights-results-missing-params', 'uri' => '/flights/results', 'accept' => 'text/html'],
            [
                'label' => 'flights-return-options-data-missing-params',
                'uri' => '/flights/return-options/data',
                'accept' => 'application/json',
            ],
            [
                'label' => 'flights-results-data-missing-params',
                'uri' => '/flights/results/data',
                'accept' => 'application/json',
            ],
            [
                'label' => 'flights-results-search-missing-params',
                'uri' => '/flights/results/search',
                'accept' => 'application/json',
            ],
            [
                'label' => 'flights-results-offer-missing-params',
                'uri' => '/flights/results/offer',
                'accept' => 'text/html',
            ],
            [
                'label' => 'flights-details-invalid-offer',
                'uri' => '/flights/details/test-offer',
                'accept' => 'text/html',
            ],
            [
                'label' => 'flights-return-options-missing-params',
                'uri' => '/flights/return-options',
                'accept' => 'text/html',
            ],
            [
                'label' => 'booking-passengers-missing-session',
                'uri' => '/booking/passengers',
                'accept' => 'text/html',
            ],
            [
                'label' => 'booking-review-missing-session',
                'uri' => '/booking/review',
                'accept' => 'text/html',
            ],
            [
                'label' => 'booking-confirmation-missing-session',
                'uri' => '/booking/confirmation',
                'accept' => 'text/html',
            ],
            [
                'label' => 'guest-booking-show-invalid-token',
                'uri' => '/guest/bookings/1/access/invalid-token',
                'accept' => 'text/html',
            ],
        ];
    }

    /**
     * Validation-only POST targets (empty body — no supplier mutation).
     *
     * @return list<array{label: string, uri: string, accept: string}>
     */
    public static function guestValidationPostTargets(): array
    {
        return [
            [
                'label' => 'lookup-booking-empty-post',
                'uri' => '/lookup-booking',
                'accept' => 'text/html',
            ],
            [
                'label' => 'booking-passengers-empty-post',
                'uri' => '/booking/passengers',
                'accept' => 'text/html',
            ],
        ];
    }

    /**
     * Authenticated GET targets (local/testing or live when demo users exist).
     *
     * @return list<array{label: string, route: string, auth: string, accept: string, params?: array<string, mixed>}>
     */
    public static function authenticatedDispatchTargets(): array
    {
        return [
            ['label' => 'dev-cp-login', 'route' => 'dev.cp.login', 'auth' => 'guest', 'accept' => 'text/html'],
            ['label' => 'dev-cp-index', 'route' => 'dev.cp.index', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-users', 'route' => 'dev.cp.users.index', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-modules', 'route' => 'dev.cp.modules.index', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-companies-legacy', 'route' => 'dev.cp.companies.index', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-sabre', 'route' => 'dev.cp.sabre', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-health', 'route' => 'dev.cp.health', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-deployment', 'route' => 'dev.cp.deployment', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-security-events', 'route' => 'dev.cp.security-events.index', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-group-ticketing', 'route' => 'dev.cp.group-ticketing', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'dev-cp-dashboards', 'route' => 'dev.cp.dashboards', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'admin-dashboard', 'route' => 'admin.dashboard', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-bookings', 'route' => 'admin.bookings', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            [
                'label' => 'admin-booking-show',
                'route' => 'admin.bookings.show',
                'auth' => 'platform_admin',
                'accept' => 'text/html',
                'params' => ['booking' => '__booking__'],
            ],
            [
                'label' => 'admin-booking-preview',
                'route' => 'admin.bookings.preview',
                'auth' => 'platform_admin',
                'accept' => 'application/json',
                'params' => ['booking' => '__booking__'],
            ],
            [
                'label' => 'admin-bookings-data',
                'route' => 'admin.bookings.data',
                'auth' => 'platform_admin',
                'accept' => 'application/json',
            ],
            [
                'label' => 'customer-booking-show',
                'route' => 'customer.bookings.show',
                'auth' => 'customer',
                'accept' => 'text/html',
                'params' => ['booking' => '__booking__'],
            ],
            [
                'label' => 'agent-booking-show',
                'route' => 'agent.bookings.show',
                'auth' => 'agent',
                'accept' => 'text/html',
                'params' => ['booking' => '__booking__'],
            ],
            [
                'label' => 'admin-supplier-diagnostics',
                'route' => 'admin.reports.supplier-diagnostics',
                'auth' => 'platform_admin',
                'accept' => 'text/html',
            ],
            [
                'label' => 'admin-support-tickets',
                'route' => 'admin.support.tickets.index',
                'auth' => 'platform_admin',
                'accept' => 'text/html',
            ],
            ['label' => 'staff-dashboard', 'route' => 'staff.dashboard', 'auth' => 'staff', 'accept' => 'text/html'],
            ['label' => 'staff-bookings', 'route' => 'staff.bookings.index', 'auth' => 'staff', 'accept' => 'text/html'],
            [
                'label' => 'staff-support-tickets',
                'route' => 'staff.support.tickets.index',
                'auth' => 'staff',
                'accept' => 'text/html',
            ],
            ['label' => 'agent-dashboard', 'route' => 'agent.dashboard', 'auth' => 'agent', 'accept' => 'text/html'],
            ['label' => 'agent-bookings', 'route' => 'agent.bookings.index', 'auth' => 'agent', 'accept' => 'text/html'],
            ['label' => 'agent-booking-create', 'route' => 'agent.bookings.create', 'auth' => 'agent', 'accept' => 'text/html'],
            [
                'label' => 'agent-support-tickets',
                'route' => 'agent.support.tickets.index',
                'auth' => 'agent',
                'accept' => 'text/html',
            ],
            ['label' => 'customer-dashboard', 'route' => 'customer.dashboard', 'auth' => 'customer', 'accept' => 'text/html'],
            ['label' => 'customer-bookings', 'route' => 'customer.bookings.index', 'auth' => 'customer', 'accept' => 'text/html'],
            [
                'label' => 'customer-support-tickets',
                'route' => 'customer.support.tickets.index',
                'auth' => 'customer',
                'accept' => 'text/html',
            ],
        ];
    }

    /**
     * @return list<int>
     */
    public static function acceptableStatusCodes(): array
    {
        return [200, 302, 403, 404, 405, 410, 419, 422];
    }

    /**
     * Legacy substring list (production output scan / older callers).
     *
     * @return list<string>
     */
    public static function forbiddenResponsePatterns(): array
    {
        return [
            'client_secret',
            'smtp_password',
            'Bearer eyJ',
            '$2y$',
        ];
    }

    /**
     * Named credential-leak checks for rendered HTML (value/context aware).
     *
     * @return array<string, string> label => PCRE pattern
     */
    public static function forbiddenResponseSecretChecks(): array
    {
        return [
            'bcrypt_password_hash' => '/\$2y\$\d{2}\$[A-Za-z0-9.\/]{50,}/',
            'bearer_jwt_token' => '/Bearer\s+eyJ[A-Za-z0-9\-._~+\/]*=*/i',
            'json_client_secret_value' => '/"client_secret"\s*:\s*"[^"]{4,}"/i',
            'json_smtp_password_value' => '/"smtp_password"\s*:\s*"[^"]{4,}"/i',
            'password_input_value' => '/<input[^>]*\btype=["\']password["\'][^>]*\bvalue=["\'][^"\']{8,}/i',
            'smtp_password_input_value' => '/<input[^>]*\bname=["\']smtp_password["\'][^>]*\bvalue=["\'][^"\']{4,}/i',
        ];
    }

    /**
     * Detect a forbidden secret pattern in rendered HTML; returns check label or null.
     */
    public static function detectForbiddenResponseSecret(string $content): ?string
    {
        foreach (self::forbiddenResponseSecretChecks() as $label => $regex) {
            if (@preg_match($regex, $content) === 1) {
                return $label;
            }
        }

        return null;
    }
}
