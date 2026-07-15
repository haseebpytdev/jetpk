<?php

namespace App\Support\Audits;

/**
 * Curated critical GET page matrix for ota:route-page-health-audit (read-only dispatch).
 */
final class RoutePageHealthAuditCatalog
{
    /**
     * Regex patterns for invalid Blade/PHP null-display syntax (not valid ternaries).
     *
     * @return list<string>
     */
    public static function sourceSyntaxHazardRegexes(): array
    {
        return [
            '/\{\{(?![^}]*\?)[^}]*\$\w+[^}]*:\s*display_unknown\(\)/',
            '/\{\{(?![^}]*\?)[^}]*->\w+[^}]*:\s*display_unknown\(\)/',
            '/\{\{(?![^}]*\?)[^}]*\[[^\]]+\][^}]*:\s*display_unknown\(\)/',
            '/(?<!\?)\(\s*\$[^)]+\s*:\s*display_unknown\(\)/',
        ];
    }

    /**
     * Dashboard-wide mojibake scan paths (FIX-4C).
     *
     * @return list<string>
     */
    public static function mojibakeScanPaths(): array
    {
        return [
            base_path('resources/views/dashboard'),
            base_path('app/Support'),
            base_path('app/Http/Controllers/Admin'),
            base_path('app/Http/Controllers/Staff'),
            base_path('app/Http/Controllers/Agent'),
            base_path('app/Http/Controllers/Customer'),
        ];
    }

    /**
     * Broken null-display fallback literals (in addition to {@see mojibakeForbiddenSubstrings()}).
     *
     * @return list<string>
     */
    public static function mojibakeForbiddenFallbackPatterns(): array
    {
        return [
            "'\u{FFFD}'",
            '"\u{FFFD}"',
            'Â·',
            'â€',
        ];
    }

    /**
     * @return list<string>
     */
    public static function mojibakeForbiddenSubstrings(): array
    {
        return [
            "\u{FFFD}",
            'Ã',
            'Â·',
            'â€',
        ];
    }

    /**
     * @return list<string>
     */
    public static function sourceScanSkipRelativePaths(): array
    {
        return [
            'app/Support/Audits/RoutePageHealthAuditCatalog.php',
            'app/Console/Commands/OtaRoutePageHealthAuditCommand.php',
            'app/Support/Ui/display_helpers.php',
        ];
    }

    /**
     * Guest/public GET targets (safe on production).
     *
     * @return list<array{label: string, route: ?string, uri: ?string, classification: string, auth: string, accept: string, params?: array<string, mixed>}>
     */
    public static function guestTargets(): array
    {
        $targets = [];
        foreach (LiveRouteSmokeCatalog::guestDispatchTargets() as $target) {
            $targets[] = [
                'label' => $target['label'],
                'route' => null,
                'uri' => $target['uri'],
                'classification' => 'public',
                'auth' => 'guest',
                'accept' => $target['accept'],
            ];
        }

        return $targets;
    }

    /**
     * Authenticated critical GET page targets.
     *
     * @return list<array{label: string, route: string, uri: ?string, classification: string, auth: string, accept: string, params?: array<string, mixed>}>
     */
    public static function authenticatedTargets(): array
    {
        return [
            ['label' => 'dev-cp-index', 'route' => 'dev.cp.index', 'uri' => null, 'classification' => 'devcp', 'auth' => 'dev_cp', 'accept' => 'text/html'],
            ['label' => 'admin-dashboard', 'route' => 'admin.dashboard', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-bookings', 'route' => 'admin.bookings', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-booking-show', 'route' => 'admin.bookings.show', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html', 'params' => ['booking' => '__booking__']],
            ['label' => 'staff-dashboard', 'route' => 'staff.dashboard', 'uri' => null, 'classification' => 'staff', 'auth' => 'staff', 'accept' => 'text/html'],
            ['label' => 'staff-bookings', 'route' => 'staff.bookings.index', 'uri' => null, 'classification' => 'staff', 'auth' => 'staff', 'accept' => 'text/html'],
            ['label' => 'staff-booking-show', 'route' => 'staff.bookings.show', 'uri' => null, 'classification' => 'staff', 'auth' => 'staff', 'accept' => 'text/html', 'params' => ['booking' => '__booking__']],
            ['label' => 'agent-dashboard', 'route' => 'agent.dashboard', 'uri' => null, 'classification' => 'agent', 'auth' => 'agent', 'accept' => 'text/html'],
            ['label' => 'agent-bookings', 'route' => 'agent.bookings.index', 'uri' => null, 'classification' => 'agent', 'auth' => 'agent', 'accept' => 'text/html'],
            ['label' => 'agent-booking-create', 'route' => 'agent.bookings.create', 'uri' => null, 'classification' => 'agent', 'auth' => 'agent', 'accept' => 'text/html'],
            ['label' => 'agent-booking-show', 'route' => 'agent.bookings.show', 'uri' => null, 'classification' => 'agent', 'auth' => 'agent', 'accept' => 'text/html', 'params' => ['booking' => '__booking__']],
            ['label' => 'customer-dashboard', 'route' => 'customer.dashboard', 'uri' => null, 'classification' => 'customer', 'auth' => 'customer', 'accept' => 'text/html'],
            ['label' => 'customer-bookings', 'route' => 'customer.bookings.index', 'uri' => null, 'classification' => 'customer', 'auth' => 'customer', 'accept' => 'text/html'],
            ['label' => 'customer-booking-show', 'route' => 'customer.bookings.show', 'uri' => null, 'classification' => 'customer', 'auth' => 'customer', 'accept' => 'text/html', 'params' => ['booking' => '__booking__']],
            ['label' => 'admin-reports', 'route' => 'admin.reports', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-supplier-diagnostics', 'route' => 'admin.reports.supplier-diagnostics', 'uri' => null, 'classification' => 'supplier/internal', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-api-settings', 'route' => 'admin.api-settings', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-api-settings-edit', 'route' => 'admin.api-settings.edit', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html', 'params' => ['supplierConnection' => '__supplier_connection__']],
            ['label' => 'admin-support-tickets', 'route' => 'admin.support.tickets.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-agent-applications', 'route' => 'admin.agent-applications.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-agent-application-show', 'route' => 'admin.agent-applications.show', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html', 'params' => ['application' => '__agent_application__']],
            ['label' => 'admin-agents', 'route' => 'admin.agents', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-agencies', 'route' => 'admin.agencies.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-agency-show', 'route' => 'admin.agencies.show', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html', 'params' => ['agency' => '__agency__']],
            ['label' => 'admin-users', 'route' => 'admin.users.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-user-show', 'route' => 'admin.users.show', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html', 'params' => ['user' => '__user__']],
            ['label' => 'admin-settings-index', 'route' => 'admin.settings.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-markups', 'route' => 'admin.markups', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-settings-branding', 'route' => 'admin.settings.branding.edit', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-settings-communications', 'route' => 'admin.settings.communications.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-group-ticketing', 'route' => 'admin.group-ticketing.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-group-ticketing-tiles', 'route' => 'admin.group-ticketing.tiles.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-group-ticketing-categories', 'route' => 'admin.group-ticketing.categories.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-group-ticketing-inventory', 'route' => 'admin.group-ticketing.inventory.index', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html'],
            ['label' => 'admin-support-ticket-show', 'route' => 'admin.support.tickets.show', 'uri' => null, 'classification' => 'admin', 'auth' => 'platform_admin', 'accept' => 'text/html', 'params' => ['ticket' => '__support_ticket__']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function adminClassifications(): array
    {
        return ['admin', 'supplier/internal', 'devcp'];
    }

    /**
     * @return list<string>
     */
    public static function staffClassifications(): array
    {
        return ['staff'];
    }

    /**
     * @return list<int>
     */
    public static function acceptableStatusCodes(): array
    {
        return LiveRouteSmokeCatalog::acceptableStatusCodes();
    }

    /**
     * @return list<string>
     */
    public static function forbiddenResponsePatterns(): array
    {
        return LiveRouteSmokeCatalog::forbiddenResponsePatterns();
    }

    public static function detectForbiddenResponseSecret(string $content): ?string
    {
        return LiveRouteSmokeCatalog::detectForbiddenResponseSecret($content);
    }
}
