<?php

namespace App\Support\Sabre;

use App\Enums\SupplierEnvironment;
use App\Models\SupplierConnection;
use App\Support\Suppliers\SabreSupplierConnectionNormalizer;

/**
 * Hard stop for sandbox QA lifecycle when a production Sabre host/connection is selected.
 * Never prints credentials. Safe for logs and artisan JSON.
 */
final class SabreSandboxQaLifecycleGuard
{
    public const LIVE_HOST = 'api.platform.sabre.com';

    /** @var list<string> */
    public const CERT_HOSTS = [
        'api.cert.platform.sabre.com',
        'api-crt.cert.havail.sabre.com',
        'stl.platform.sabre.com',
    ];

    /**
     * @return array{
     *     allowed: bool,
     *     block_reason: string|null,
     *     resolved_environment: string,
     *     resolved_host: string,
     *     host_classification: string,
     *     production_sabre_host_selected: bool,
     *     connection_id: int|null,
     *     connection_alias_safe: string|null
     * }
     */
    public static function assertSandboxQaAllowed(
        ?SupplierConnection $connection,
        ?int $forbiddenLiveConnectionId = null,
    ): array {
        if ($connection === null) {
            return self::blocked('supplier_connection_missing', null);
        }

        $alias = trim((string) ($connection->name ?? ''));
        $env = $connection->environment?->value ?? 'unknown';
        $host = self::resolvedHost($connection);
        $classification = self::classifyHost($host);
        $productionHost = $classification === 'production';

        $base = [
            'allowed' => false,
            'block_reason' => null,
            'resolved_environment' => $env,
            'resolved_host' => $host !== '' ? $host : 'unknown',
            'host_classification' => $classification,
            'production_sabre_host_selected' => $productionHost,
            'connection_id' => $connection->id,
            'connection_alias_safe' => $alias !== '' ? $alias : null,
        ];

        if ($forbiddenLiveConnectionId !== null && (int) $connection->id === (int) $forbiddenLiveConnectionId) {
            return array_merge($base, ['block_reason' => 'live_production_connection_selected_for_sandbox_qa']);
        }

        if ($connection->environment === SupplierEnvironment::Live) {
            return array_merge($base, ['block_reason' => 'connection_environment_production']);
        }

        if ($productionHost) {
            return array_merge($base, ['block_reason' => 'resolved_host_is_production_sabre']);
        }

        if ($classification !== 'non_production') {
            return array_merge($base, ['block_reason' => 'resolved_host_not_classified_non_production']);
        }

        return array_merge($base, [
            'allowed' => true,
            'block_reason' => null,
        ]);
    }

    public static function resolvedHost(SupplierConnection $connection): string
    {
        $base = trim((string) ($connection->base_url ?: SabreSupplierConnectionNormalizer::CERT_BASE_URL));
        $host = parse_url($base, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    public static function classifyHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'unknown') {
            return 'unknown';
        }
        if ($host === self::LIVE_HOST) {
            return 'production';
        }
        if (in_array($host, self::CERT_HOSTS, true)) {
            return 'non_production';
        }

        return 'unknown';
    }

    /**
     * @return array{
     *     allowed: bool,
     *     block_reason: string|null,
     *     resolved_environment: string,
     *     resolved_host: string,
     *     host_classification: string,
     *     production_sabre_host_selected: bool,
     *     connection_id: int|null,
     *     connection_alias_safe: string|null
     * }
     */
    private static function blocked(string $reason, ?SupplierConnection $connection): array
    {
        return [
            'allowed' => false,
            'block_reason' => $reason,
            'resolved_environment' => $connection?->environment?->value ?? 'unknown',
            'resolved_host' => $connection !== null ? self::resolvedHost($connection) : 'unknown',
            'host_classification' => $connection !== null
                ? self::classifyHost(self::resolvedHost($connection))
                : 'unknown',
            'production_sabre_host_selected' => false,
            'connection_id' => $connection?->id,
            'connection_alias_safe' => $connection !== null ? trim((string) $connection->name) : null,
        ];
    }
}
