<?php

namespace App\Support\Audits;

use App\Support\Client\ReservedClientPreviewSlugs;

/**
 * MC-7A read-only route classifier for multi-client prefix parity audit.
 *
 * Pure heuristics — no HTTP, no DB writes, no supplier calls.
 */
final class ClientRouteParityClassifier
{
    public const CLASSIFICATIONS = [
        'public_page',
        'public_action',
        'auth_page',
        'auth_action',
        'customer_dashboard',
        'agent_dashboard',
        'staff_dashboard',
        'admin_dashboard',
        'dev_cp',
        'group_ticketing',
        'booking_flow',
        'supplier_api_action',
        'internal_api',
        'asset_static',
        'webhook',
        'debug_or_audit',
        'excluded',
    ];

    /**
     * @param  list<string>  $middleware
     * @return array{
     *     classification: string,
     *     should_have_client_prefix: string,
     *     risk_level: string,
     *     notes: string
     * }
     */
    public function classify(
        string $routeName,
        string $method,
        string $uri,
        string $action,
        array $middleware,
    ): array {
        $method = strtoupper($method);
        $name = strtolower($routeName);
        $uriLower = strtolower(ltrim($uri, '/'));
        $actionLower = strtolower($action);
        $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $hasAuth = in_array('auth', $middleware, true);

        $classification = $this->resolveClassification(
            $name,
            $uriLower,
            $actionLower,
            $hasAuth,
            $isMutating,
        );

        $riskLevel = $this->resolveRiskLevel($classification, $method, $name, $uriLower, $actionLower);
        $prefixDecision = $this->resolvePrefixDecision($classification, $method, $isMutating);
        $notes = $this->resolveNotes($classification, $prefixDecision, $uriLower, $routeName);

        return [
            'classification' => $classification,
            'should_have_client_prefix' => $prefixDecision['should_have_client_prefix'],
            'risk_level' => $riskLevel,
            'notes' => $notes,
        ];
    }

    public function suggestedPrefixedUri(string $targetSlug, string $uri): string
    {
        $normalized = '/'.ltrim($uri, '/');
        if ($normalized === '/') {
            return '/'.$targetSlug;
        }

        return '/'.$targetSlug.$normalized;
    }

    private function resolveClassification(
        string $name,
        string $uriLower,
        string $actionLower,
        bool $hasAuth,
        bool $isMutating,
    ): string {
        if ($this->isExcluded($name, $uriLower)) {
            return 'excluded';
        }

        if ($this->isAssetStatic($uriLower)) {
            return 'asset_static';
        }

        if ($this->isDevCp($name, $uriLower)) {
            return 'dev_cp';
        }

        if ($this->isWebhook($name, $uriLower)) {
            return 'webhook';
        }

        if ($this->isDebugOrAudit($name, $uriLower)) {
            return 'debug_or_audit';
        }

        if ($this->isSupplierApiAction($name, $uriLower, $actionLower)) {
            return 'supplier_api_action';
        }

        if ($this->isInternalApi($name, $uriLower)) {
            return 'internal_api';
        }

        if ($this->isBookingFlow($name, $uriLower)) {
            return 'booking_flow';
        }

        if ($this->isGroupTicketing($name, $uriLower)) {
            return 'group_ticketing';
        }

        $dashboard = $this->resolveDashboardClassification($name);
        if ($dashboard !== null) {
            return $dashboard;
        }

        if ($this->isAuthRoute($name, $uriLower)) {
            return $isMutating ? 'auth_action' : 'auth_page';
        }

        if (! $hasAuth) {
            return $isMutating ? 'public_action' : 'public_page';
        }

        return $isMutating ? 'public_action' : 'public_page';
    }

    private function isExcluded(string $name, string $uriLower): bool
    {
        if ($uriLower === 'up' || str_starts_with($uriLower, 'health')) {
            return true;
        }

        if (str_starts_with($name, 'client.preview.')) {
            return true;
        }

        if (str_starts_with($uriLower, '{clientslug}')) {
            return true;
        }

        return false;
    }

    private function isAssetStatic(string $uriLower): bool
    {
        $prefixes = [
            'css/',
            'js/',
            'storage/',
            'client-assets/',
            'build/',
            'vendor/',
            'images/',
            'assets/',
            'themes/',
        ];

        foreach ($prefixes as $prefix) {
            if ($uriLower === rtrim($prefix, '/') || str_starts_with($uriLower, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isDevCp(string $name, string $uriLower): bool
    {
        return str_starts_with($uriLower, 'dev/cp') || str_starts_with($name, 'dev.cp.');
    }

    private function isWebhook(string $name, string $uriLower): bool
    {
        return str_contains($name, 'webhook') || str_contains($uriLower, 'webhook');
    }

    private function isDebugOrAudit(string $name, string $uriLower): bool
    {
        $needles = ['_debugbar', 'telescope', 'horizon'];

        foreach ($needles as $needle) {
            if (str_contains($name, $needle) || str_contains($uriLower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isSupplierApiAction(string $name, string $uriLower, string $actionLower): bool
    {
        $needles = [
            'supplier-booking',
            'prepare-supplier-pnr',
            'revalidate-offer',
            'api-settings.test',
            'supplier-diagnostics',
            'supplier_diagnostics',
            'createSupplierBooking',
            'prepareSupplierPnr',
            'revalidateSelectedOffer',
            'sabre',
        ];

        $haystack = $name.' '.$uriLower.' '.$actionLower;

        foreach ($needles as $needle) {
            if (str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function isInternalApi(string $name, string $uriLower): bool
    {
        if (str_starts_with($uriLower, 'api/') || str_starts_with($name, 'api.')) {
            return true;
        }

        if (str_starts_with($name, 'group-ticketing.')
            && (str_contains($name, '.results')
                || str_contains($name, '.facets')
                || str_contains($uriLower, '/results')
                || str_contains($uriLower, '/facets'))) {
            return true;
        }

        $internalPatterns = [
            '.data',
            '.search',
            '.facets',
            'airports/search',
            'results/data',
            'results/search',
            'return-options/data',
            'results/nearby-dates',
        ];

        foreach ($internalPatterns as $pattern) {
            if ($pattern === '.search' && str_starts_with($name, 'group-ticketing.')) {
                continue;
            }

            if (str_contains($name, $pattern) || str_contains($uriLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function isBookingFlow(string $name, string $uriLower): bool
    {
        if (str_starts_with($name, 'booking.')
            || str_starts_with($name, 'flights.')
            || str_starts_with($name, 'lookup-booking.')) {
            return true;
        }

        return str_contains($uriLower, 'booking/')
            || str_starts_with($uriLower, 'booking')
            || str_contains($uriLower, 'flights/')
            || str_starts_with($uriLower, 'flights')
            || str_starts_with($uriLower, 'lookup-booking');
    }

    private function isGroupTicketing(string $name, string $uriLower): bool
    {
        return str_starts_with($name, 'group-ticketing.')
            || str_starts_with($uriLower, 'groups/')
            || str_starts_with($uriLower, 'groups')
            || str_starts_with($uriLower, 'umrah-groups');
    }

    private function resolveDashboardClassification(string $name): ?string
    {
        if ($name === 'dashboard') {
            return 'customer_dashboard';
        }

        if (str_starts_with($name, 'admin.')) {
            return 'admin_dashboard';
        }

        if (str_starts_with($name, 'agent.')) {
            return 'agent_dashboard';
        }

        if (str_starts_with($name, 'staff.')) {
            return 'staff_dashboard';
        }

        if (str_starts_with($name, 'customer.')) {
            return 'customer_dashboard';
        }

        return null;
    }

    private function isAuthRoute(string $name, string $uriLower): bool
    {
        if (str_contains($name, 'login')
            || str_contains($name, 'register')
            || str_contains($name, 'password')
            || str_starts_with($name, 'verification.')
            || str_contains($name, 'social')
            || str_contains($name, 'oauth')
            || str_contains($name, 'confirm-password')) {
            return true;
        }

        $authUriPrefixes = ['login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'confirm-password'];

        foreach ($authUriPrefixes as $prefix) {
            if ($uriLower === $prefix || str_starts_with($uriLower, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{should_have_client_prefix: string, defer_reason: string|null}
     */
    private function resolvePrefixDecision(string $classification, string $method, bool $isMutating): array
    {
        $neverPrefix = [
            'excluded',
            'asset_static',
            'dev_cp',
            'webhook',
            'internal_api',
            'supplier_api_action',
            'debug_or_audit',
        ];

        if (in_array($classification, $neverPrefix, true)) {
            return ['should_have_client_prefix' => 'no', 'defer_reason' => 'never auto-prefix'];
        }

        $prefixableGet = [
            'public_page',
            'auth_page',
            'customer_dashboard',
            'agent_dashboard',
            'staff_dashboard',
            'admin_dashboard',
            'group_ticketing',
            'booking_flow',
        ];

        if (in_array($classification, $prefixableGet, true) && ! $isMutating) {
            return ['should_have_client_prefix' => 'yes', 'defer_reason' => null];
        }

        if ($isMutating || in_array($classification, ['public_action', 'auth_action'], true)) {
            return ['should_have_client_prefix' => 'no', 'defer_reason' => 'defer mutating action until MC-7B review'];
        }

        if ($classification === 'booking_flow' && $method === 'GET') {
            return ['should_have_client_prefix' => 'yes', 'defer_reason' => null];
        }

        return ['should_have_client_prefix' => 'no', 'defer_reason' => 'manual review required'];
    }

    private function resolveRiskLevel(
        string $classification,
        string $method,
        string $name,
        string $uriLower,
        string $actionLower,
    ): string {
        $isMutating = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if (! $isMutating) {
            return 'low';
        }

        if (in_array($classification, ['supplier_api_action', 'booking_flow'], true)) {
            return 'high';
        }

        $highNeedles = ['ticketing', 'cancel', 'pnr', 'supplier'];
        $haystack = $name.' '.$uriLower.' '.$actionLower;
        foreach ($highNeedles as $needle) {
            if (str_contains($haystack, $needle)) {
                return 'high';
            }
        }

        if (in_array($classification, ['auth_action', 'group_ticketing', 'dev_cp'], true)) {
            return 'medium';
        }

        if (in_array($classification, ['admin_dashboard', 'agent_dashboard', 'staff_dashboard', 'customer_dashboard'], true)) {
            return 'medium';
        }

        if ($classification === 'public_action') {
            return 'medium';
        }

        return 'low';
    }

    private function resolveNotes(string $classification, array $prefixDecision, string $uriLower, string $routeName): string
    {
        $notes = [];

        if ($prefixDecision['defer_reason'] !== null) {
            $notes[] = $prefixDecision['defer_reason'];
        }

        if ($classification === 'excluded' && str_starts_with(strtolower($routeName), 'client.preview.')) {
            $notes[] = 'MC-4/5A placeholder — superseded in MC-7B';
        }

        $firstSegment = explode('/', $uriLower)[0] ?? '';
        if ($firstSegment !== '' && ReservedClientPreviewSlugs::isReserved($firstSegment)) {
            $notes[] = 'URI first segment is reserved client slug segment';
        }

        if ($notes === []) {
            return ucfirst(str_replace('_', ' ', $classification));
        }

        return implode('; ', $notes);
    }
}
