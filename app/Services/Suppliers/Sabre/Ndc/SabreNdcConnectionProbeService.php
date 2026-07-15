<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Diagnostics\SabreActiveAuthDiagnostic;
use App\Support\Suppliers\SabreChannelGateResolver;

/**
 * Read-only Sabre NDC connection probe — OAuth token only; no shop/order/ticketing/cancel.
 */
final class SabreNdcConnectionProbeService
{
    public const CONFIRM_PHRASE = 'READONLY-SABRE-NDC-CONNECTION-PROBE';

    public function __construct(
        private readonly SabreActiveAuthDiagnostic $authDiagnostic,
        private readonly SabreChannelGateResolver $channelGateResolver,
        private readonly SabreNdcCapabilityReportService $capabilityReport,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function probe(?SupplierConnection $connection, bool $sendLiveAuth = false): array
    {
        $lane = $this->channelGateResolver->diagnostics($connection);
        $blockers = $this->channelGateResolver->ndcLaneBlockers($connection);

        $auth = $connection !== null
            ? $this->authDiagnostic->diagnoseDbConnection($connection, $sendLiveAuth)
            : ['error' => 'connection_missing'];

        $capability = $this->capabilityReport->report($connection);

        return [
            'classification' => 'READ-ONLY',
            'lane' => 'sabre_ndc',
            'endpoint_family' => 'ndc_rest',
            'auth_probe_only' => true,
            'mutations_attempted' => false,
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => $sendLiveAuth,
            'blockers' => $blockers,
            'lane_gate' => $lane,
            'auth' => $this->sanitizeAuthResult($auth),
            'ndc_endpoints_configured' => is_array($capability['endpoints'] ?? null) ? count($capability['endpoints']) : 0,
            'safe_error_family' => $this->safeErrorFamily($auth, $blockers),
        ];
    }

    /**
     * @param  array<string, mixed>  $auth
     * @return array<string, mixed>
     */
    private function sanitizeAuthResult(array $auth): array
    {
        if (isset($auth['error'])) {
            return [
                'status' => 'error',
                'error' => (string) $auth['error'],
            ];
        }

        return [
            'status' => 'ok',
            'auth_endpoint_host' => (string) ($auth['auth_endpoint_host'] ?? 'unknown'),
            'auth_endpoint_path' => (string) ($auth['auth_endpoint_path'] ?? ''),
            'auth_strategy_planned' => (string) ($auth['auth_strategy_planned'] ?? 'unknown'),
            'credential_source' => (string) ($auth['credential_source'] ?? 'unknown'),
            'pcc_present' => (bool) ($auth['pcc_present'] ?? false),
            'pcc_len' => (int) ($auth['pcc_len'] ?? 0),
            'sign_in_present' => (bool) ($auth['sign_in_present'] ?? false),
            'sign_in_len' => (int) ($auth['sign_in_len'] ?? 0),
            'client_id_present' => (bool) ($auth['client_id_present'] ?? false),
            'client_id_len' => (int) ($auth['client_id_len'] ?? 0),
            'http_status' => $auth['http_status'] ?? null,
            'oauth_error' => $auth['oauth_error'] ?? null,
            'token_obtained' => $auth['token_obtained'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $auth
     * @param  list<string>  $blockers
     */
    private function safeErrorFamily(array $auth, array $blockers): string
    {
        if ($blockers !== []) {
            return 'configuration_blocked';
        }
        if (isset($auth['error'])) {
            return 'connection_missing';
        }
        if (($auth['token_obtained'] ?? null) === true) {
            return 'auth_ok';
        }
        if (($auth['token_obtained'] ?? null) === false) {
            return 'auth_failed';
        }

        return 'auth_not_probed';
    }
}
