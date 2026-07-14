<?php

namespace App\Services\Suppliers\Sabre;

use App\Models\SupplierConnection;

final class SabreInspectGate
{
    private const PRODUCTION_LIVE_SABRE_HOST = 'api.platform.sabre.com';

    /** @var list<string> */
    private const PRODUCTION_CERT_SABRE_HOSTS = [
        'api.cert.platform.sabre.com',
        'api-crt.cert.havail.sabre.com',
    ];

    /**
     * Local-only / testing-only Sabre inspect tooling (never enable in production web; Artisan guard).
     *
     * @param  non-empty-string|null  $environment  Explicit env name for tests; uses config when null.
     */
    public static function allowed(?string $environment = null): bool
    {
        $env = $environment ?? (string) config('app.env', 'production');

        return in_array($env, ['local', 'testing'], true);
    }

    /**
     * Production read-only PNR retrieve inspect (`sabre:inspect-pnr-retrieve --send` only).
     */
    public static function isPnrRetrieveInspectEnabled(): bool
    {
        return (bool) config('suppliers.sabre.pnr_retrieve_inspect_enabled', false);
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    public static function pnrRetrieveInspectAllowed(bool $send, ?string $environment = null): bool
    {
        if (self::allowed($environment)) {
            return true;
        }

        $env = $environment ?? (string) config('app.env', 'production');

        return $env === 'production'
            && $send
            && self::isPnrRetrieveInspectEnabled();
    }

    /**
     * Machine-readable block reason for {@see pnrRetrieveInspectAllowed()}.
     *
     * @param  non-empty-string|null  $environment
     */
    public static function pnrRetrieveInspectBlockReason(bool $send, ?string $environment = null): ?string
    {
        if (self::pnrRetrieveInspectAllowed($send, $environment)) {
            return null;
        }

        $env = $environment ?? (string) config('app.env', 'production');

        if ($env === 'production') {
            if (! self::isPnrRetrieveInspectEnabled()) {
                return 'sabre_pnr_retrieve_inspect_disabled';
            }

            if (! $send) {
                return 'sabre_pnr_retrieve_inspect_requires_send';
            }
        }

        return 'sabre_pnr_retrieve_inspect_env_blocked';
    }

    /**
     * Direct {@code --pnr} live retrieve: production {@see pnrRetrieveInspectAllowed()} or CERT host +
     * {@see certEntitlementMatrixAllowed()} when {@code SABRE_CERT_ENTITLEMENT_MATRIX_ENABLED=true}.
     *
     * @param  non-empty-string|null  $environment
     */
    public static function pnrRetrieveDirectInspectAllowed(
        bool $send,
        ?SupplierConnection $connection,
        ?string $environment = null,
    ): bool {
        if (! $send) {
            return false;
        }

        if (self::pnrRetrieveInspectAllowed($send, $environment)) {
            return true;
        }

        $env = $environment ?? (string) config('app.env', 'production');
        if ($env !== 'production' || $connection === null) {
            return false;
        }

        if (! self::isCertEntitlementMatrixEnabled()) {
            return false;
        }

        $resolved = self::resolveSabreBaseUrlForGate($connection);

        return $resolved !== '' && self::isCertSabreHost($resolved);
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    public static function pnrRetrieveDirectInspectBlockReason(
        bool $send,
        ?SupplierConnection $connection,
        ?string $environment = null,
    ): ?string {
        if (self::pnrRetrieveDirectInspectAllowed($send, $connection, $environment)) {
            return null;
        }

        if (! $send) {
            return 'pnr_retrieve_requires_send';
        }

        if (self::allowed($environment)) {
            return 'pnr_retrieve_direct_inspect_blocked';
        }

        $env = $environment ?? (string) config('app.env', 'production');

        if ($env !== 'production') {
            return 'sabre_pnr_retrieve_inspect_env_blocked';
        }

        if (self::isPnrRetrieveInspectEnabled()) {
            return 'pnr_retrieve_direct_inspect_blocked';
        }

        if (! self::isCertEntitlementMatrixEnabled()) {
            return 'pnr_retrieve_direct_inspect_requires_prod_or_cert_flag';
        }

        if ($connection === null) {
            return 'pnr_retrieve_requires_connection';
        }

        $resolved = self::resolveSabreBaseUrlForGate($connection);
        if ($resolved === '' || ! self::isCertSabreHost($resolved)) {
            return 'pnr_retrieve_direct_cert_requires_cert_host';
        }

        return 'pnr_retrieve_direct_inspect_disabled';
    }

    /**
     * Production SSH-only CERT shop send ({@code sabre:inspect-shop-payload --send --confirm=READONLY-CERT-SHOP-SEND}).
     * Requires {@code SABRE_BRANDED_FARES_PROBE_ENABLED=true} and a CERT Sabre host (never live production).
     *
     * @param  non-empty-string|null  $environment
     */
    public static function shopPayloadCertSendAllowed(?SupplierConnection $connection, ?string $environment = null): bool
    {
        if (self::allowed($environment)) {
            return true;
        }

        $env = $environment ?? (string) config('app.env', 'production');
        if ($env !== 'production' || $connection === null) {
            return false;
        }

        if (! (bool) config('suppliers.sabre.branded_fares_probe_enabled', false)) {
            return false;
        }

        $resolved = self::resolveSabreBaseUrlForGate($connection);
        if ($resolved === '' || self::isProductionLiveSabreHost($resolved)) {
            return false;
        }

        return self::isCertSabreHost($resolved);
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    public static function shopPayloadCertSendBlockReason(?SupplierConnection $connection, ?string $environment = null): ?string
    {
        if (self::shopPayloadCertSendAllowed($connection, $environment)) {
            return null;
        }

        if (self::allowed($environment)) {
            return 'shop_payload_cert_send_blocked';
        }

        $env = $environment ?? (string) config('app.env', 'production');

        if ($env !== 'production') {
            return 'shop_payload_cert_send_env_blocked';
        }

        if ($connection === null) {
            return 'shop_payload_cert_send_requires_connection';
        }

        if (! (bool) config('suppliers.sabre.branded_fares_probe_enabled', false)) {
            return 'shop_payload_cert_send_requires_probe_flag';
        }

        $resolved = self::resolveSabreBaseUrlForGate($connection);
        if ($resolved === '' || self::isProductionLiveSabreHost($resolved)) {
            return 'shop_payload_cert_send_live_host_blocked';
        }

        return 'shop_payload_cert_send_non_cert_host';
    }

    /**
     * Production SSH-only CERT entitlement matrix ({@code sabre:cert-entitlement-matrix}).
     */
    public static function isCertEntitlementMatrixEnabled(): bool
    {
        return (bool) config('suppliers.sabre.cert_entitlement_matrix_enabled', false);
    }

    /**
     * Inspect-only matrix output (no {@code --send}) — local/testing always; production when flag + CERT host.
     *
     * @param  non-empty-string|null  $environment
     */
    public static function certEntitlementMatrixAllowed(?SupplierConnection $connection = null, ?string $environment = null): bool
    {
        if (self::allowed($environment)) {
            return true;
        }

        return self::certEntitlementMatrixProductionContextAllowed($connection, $environment);
    }

    /**
     * Live HTTP probes ({@code --send}) — local/testing always; production only when flag + CERT host (never live).
     *
     * @param  non-empty-string|null  $environment
     */
    public static function certEntitlementMatrixSendAllowed(?SupplierConnection $connection = null, ?string $environment = null): bool
    {
        if (self::allowed($environment)) {
            return true;
        }

        return self::certEntitlementMatrixProductionContextAllowed($connection, $environment);
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    public static function certEntitlementMatrixBlockReason(?SupplierConnection $connection = null, ?string $environment = null): ?string
    {
        if (self::certEntitlementMatrixAllowed($connection, $environment)) {
            return null;
        }

        if (self::allowed($environment)) {
            return null;
        }

        return self::certEntitlementMatrixProductionBlockReason($connection, $environment);
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    public static function certEntitlementMatrixSendBlockReason(?SupplierConnection $connection = null, ?string $environment = null): ?string
    {
        if (self::certEntitlementMatrixSendAllowed($connection, $environment)) {
            return null;
        }

        if (self::allowed($environment)) {
            return null;
        }

        return self::certEntitlementMatrixProductionBlockReason($connection, $environment);
    }

    /**
     * Safe Sabre base URL resolution for diagnostics (hosts only; no credentials).
     *
     * @return array{
     *     connection_base_url: string|null,
     *     config_base_url: string,
     *     resolved_base_url: string,
     *     resolved_base_host: string,
     *     resolved_source: 'connection_base_url'|'config_base_url',
     * }
     */
    public static function resolveSabreBaseUrlContext(?SupplierConnection $connection = null): array
    {
        $connectionUrl = '';
        if ($connection !== null) {
            $connectionUrl = trim((string) ($connection->base_url ?? ''));
        }

        $configUrl = rtrim((string) config('suppliers.sabre.default_base_url', ''), '/');
        $resolvedUrl = $connectionUrl !== ''
            ? rtrim($connectionUrl, '/')
            : $configUrl;
        $resolvedSource = $connectionUrl !== '' ? 'connection_base_url' : 'config_base_url';

        $connectionHost = $connectionUrl !== '' ? self::sabreBaseUrlHost($connectionUrl) : null;
        $configHost = self::sabreBaseUrlHost($configUrl);
        $resolvedHost = self::sabreBaseUrlHost($resolvedUrl);

        return [
            'connection_base_url' => $connectionHost !== '' ? $connectionHost : null,
            'config_base_url' => $configHost !== '' ? $configHost : 'unknown',
            'resolved_base_url' => $resolvedHost !== '' ? $resolvedHost : 'unknown',
            'resolved_base_host' => $resolvedHost !== '' ? $resolvedHost : 'unknown',
            'resolved_source' => $resolvedSource,
        ];
    }

    public static function resolveSabreBaseUrlForGate(?SupplierConnection $connection = null): string
    {
        $context = self::resolveSabreBaseUrlContext($connection);
        $host = (string) ($context['resolved_base_host'] ?? '');
        if ($host === '' || $host === 'unknown') {
            return '';
        }

        return 'https://'.$host;
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    protected static function certEntitlementMatrixProductionContextAllowed(
        ?SupplierConnection $connection,
        ?string $environment,
    ): bool {
        $env = $environment ?? (string) config('app.env', 'production');
        if ($env !== 'production') {
            return false;
        }

        if (! self::isCertEntitlementMatrixEnabled()) {
            return false;
        }

        $context = self::resolveSabreBaseUrlContext($connection);
        $resolvedHost = (string) ($context['resolved_base_host'] ?? '');
        if ($resolvedHost === self::PRODUCTION_LIVE_SABRE_HOST) {
            return false;
        }

        return self::isCertSabreHost('https://'.$resolvedHost);
    }

    /**
     * @param  non-empty-string|null  $environment
     */
    protected static function certEntitlementMatrixProductionBlockReason(
        ?SupplierConnection $connection,
        ?string $environment,
    ): string {
        $env = $environment ?? (string) config('app.env', 'production');

        if ($env !== 'production') {
            return 'sabre_cert_entitlement_matrix_env_blocked';
        }

        if (! self::isCertEntitlementMatrixEnabled()) {
            return 'sabre_cert_entitlement_matrix_disabled';
        }

        $context = self::resolveSabreBaseUrlContext($connection);
        $resolvedHost = (string) ($context['resolved_base_host'] ?? '');
        if ($resolvedHost === self::PRODUCTION_LIVE_SABRE_HOST) {
            return 'sabre_cert_entitlement_matrix_live_host_blocked';
        }

        return 'sabre_cert_entitlement_matrix_non_cert_host';
    }

    public static function sabreBaseUrlHost(string $baseUrl): string
    {
        $normalized = str_contains($baseUrl, '://') ? $baseUrl : 'https://'.$baseUrl;
        $host = parse_url($normalized, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : '';
    }

    public static function isProductionLiveSabreHost(string $baseUrl): bool
    {
        return self::sabreBaseUrlHost($baseUrl) === self::PRODUCTION_LIVE_SABRE_HOST;
    }

    public static function isCertSabreHost(string $baseUrl): bool
    {
        $host = self::sabreBaseUrlHost($baseUrl);
        if ($host === '' || self::isProductionLiveSabreHost($baseUrl)) {
            return false;
        }

        if (in_array($host, self::PRODUCTION_CERT_SABRE_HOSTS, true)) {
            return true;
        }

        return str_contains($host, 'cert.platform.sabre.com')
            || str_contains($host, 'cert.havail.sabre.com');
    }
}
