<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreCapabilityMatrixService;
use App\Support\Suppliers\SabreChannelGateResolver;

/**
 * Read-only Sabre NDC capability / readiness report (no HTTP, no secrets).
 *
 * Distinguishes Sabre NDC (Offer/Order lifecycle) from Sabre GDS (PNR/AirTicketRQ).
 * One SupplierConnection supplies shared auth for both lanes.
 */
final class SabreNdcCapabilityReportService
{
    public function __construct(
        private readonly SabreChannelGateResolver $channelGateResolver,
        private readonly SabreNdcStatusService $statusService,
        private readonly SabreCapabilityMatrixService $capabilityMatrix,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(?SupplierConnection $connection = null): array
    {
        $ndcConfig = config('suppliers.sabre.ndc', []);
        if (! is_array($ndcConfig)) {
            $ndcConfig = [];
        }

        $lane = $this->channelGateResolver->diagnostics($connection);
        $status = $this->statusService->status($connection);
        $credentials = $this->maskedCredentialSummary($connection, $lane);

        return [
            'lane' => 'sabre_ndc',
            'gds_lane_separated' => true,
            'gds_lane_note' => 'Sabre GDS uses PNR/Trip Orders/AirTicketRQ. Sabre NDC uses Offer/Order APIs only. Shared SupplierConnection auth.',
            'lane_gate' => $lane,
            'connection' => $connection === null ? null : [
                'connection_id' => $connection->id,
                'connection_name' => (string) $connection->name,
                'connection_gds_channel_enabled' => $lane['connection_gds_channel_enabled'],
                'connection_ndc_channel_enabled' => $lane['connection_ndc_channel_enabled'],
            ],
            'credentials' => $credentials,
            'capabilities' => $this->runtimeCapabilities($ndcConfig, $lane),
            'endpoints' => $this->endpointFamilies($ndcConfig),
            'mutation_gates' => $this->mutationGates($ndcConfig, $lane),
            'static_matrix' => $this->ndcMatrixRows(),
            'status' => $status,
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $ndcConfig
     * @param  array<string, mixed>  $lane
     * @return array<string, array{enabled: bool, env_flag: string, admin_only: bool, live_http: string, notes: string}>
     */
    private function runtimeCapabilities(array $ndcConfig, array $lane): array
    {
        $effectiveNdc = (bool) ($lane['effective_ndc_enabled'] ?? false);
        $liveSearch = $effectiveNdc && (bool) ($ndcConfig['search_enabled'] ?? false);

        return [
            'ndc_channel' => [
                'enabled' => $effectiveNdc,
                'env_flag' => 'connection_ndc_enabled+module (global_ndc_kill_switch blocks)',
                'admin_only' => false,
                'live_http' => 'no',
                'notes' => 'Admin connection sabre_ndc_enabled + sabre_ndc platform module; not blocked by SABRE_NDC_ENABLED=false.',
            ],
            'search' => [
                'enabled' => $liveSearch,
                'env_flag' => 'SABRE_NDC_SEARCH_ENABLED',
                'admin_only' => true,
                'live_http' => 'env_gated',
                'notes' => 'Lane allowed when effective_ndc_enabled; live shop HTTP additionally requires SABRE_NDC_SEARCH_ENABLED.',
            ],
            'offer_price_revalidation' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['offer_price_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_OFFER_PRICE_ENABLED',
                'admin_only' => true,
                'live_http' => 'env_gated',
                'notes' => 'Offer price / revalidation; separate from GDS BFM revalidate.',
            ],
            'reprice_order' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['reprice_order_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_REPRICE_ORDER_ENABLED',
                'admin_only' => true,
                'live_http' => 'env_gated',
                'notes' => 'POST repriceOrder; admin Artisan only.',
            ],
            'order_create' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['order_create_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_ORDER_CREATE_ENABLED',
                'admin_only' => true,
                'live_http' => 'env_gated',
                'notes' => 'Admin-approved confirm phrase; no public auto order create.',
            ],
            'order_retrieve_sync' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['order_retrieve_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_ORDER_RETRIEVE_ENABLED',
                'admin_only' => true,
                'live_http' => 'env_gated',
                'notes' => 'Order view / ndc orders retrieve; not GDS getBooking.',
            ],
            'order_change' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['order_change_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_ORDER_CHANGE_ENABLED',
                'admin_only' => true,
                'live_http' => 'env_gated',
                'notes' => 'NDC order change scaffold; env-gated.',
            ],
            'payment_ticketing_fulfillment' => [
                'enabled' => false,
                'env_flag' => 'not_configured',
                'admin_only' => true,
                'live_http' => 'no',
                'notes' => 'Not implemented. Must not use GDS AirTicketRQ or LNIATA printer paths.',
            ],
            'cancel_void_refund' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['cancel_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_CANCEL_ENABLED',
                'admin_only' => true,
                'live_http' => 'no',
                'notes' => 'NDC cancel not implemented; flag reserved for NDC-CANCEL-VOID-REFUND-1.',
            ],
            'public_checkout' => [
                'enabled' => $effectiveNdc && (bool) ($ndcConfig['public_order_create_enabled'] ?? false),
                'env_flag' => 'SABRE_NDC_PUBLIC_ORDER_CREATE_ENABLED',
                'admin_only' => false,
                'live_http' => 'no',
                'notes' => 'Public NDC checkout disabled by default; admin-only until certified.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $ndcConfig
     * @return list<array{family: string, path_config_key: string, path: string}>
     */
    private function endpointFamilies(array $ndcConfig): array
    {
        $keys = [
            ['family' => 'ndc_offer_shop', 'path_config_key' => 'offer_shop_path'],
            ['family' => 'ndc_offer_price', 'path_config_key' => 'offer_price_path'],
            ['family' => 'ndc_order_create', 'path_config_key' => 'order_create_path'],
            ['family' => 'ndc_order_retrieve', 'path_config_key' => 'order_retrieve_path'],
            ['family' => 'ndc_order_view', 'path_config_key' => 'order_view_path'],
            ['family' => 'ndc_order_change', 'path_config_key' => 'order_change_path'],
            ['family' => 'ndc_reprice_order', 'path_config_key' => 'reprice_order_path'],
        ];

        $rows = [];
        foreach ($keys as $row) {
            $rows[] = [
                'family' => $row['family'],
                'path_config_key' => $row['path_config_key'],
                'path' => (string) ($ndcConfig[$row['path_config_key']] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $ndcConfig
     * @param  array<string, mixed>  $lane
     * @return array<string, mixed>
     */
    private function mutationGates(array $ndcConfig, array $lane): array
    {
        return [
            'all_mutations_admin_gated' => true,
            'gds_ticketing_blocked_for_ndc_channel' => true,
            'gds_cancel_blocked_for_ndc_channel' => true,
            'order_create_confirm_phrase_required' => true,
            'public_order_create' => (bool) ($ndcConfig['public_order_create_enabled'] ?? false),
            'effective_ndc_enabled' => (bool) ($lane['effective_ndc_enabled'] ?? false),
            'effective_gds_enabled' => (bool) ($lane['effective_gds_enabled'] ?? false),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ndcMatrixRows(): array
    {
        $keys = ['ndc_search', 'ndc_order_create', 'ndc_order_retrieve', 'ndc_reprice', 'ndc_order_change', 'ndc_cancel'];
        $rows = [];
        foreach ($keys as $key) {
            $row = $this->capabilityMatrix->get($key);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $lane
     * @return array<string, mixed>
     */
    private function maskedCredentialSummary(?SupplierConnection $connection, array $lane): array
    {
        if ($connection === null) {
            return [
                'source' => 'supplier_connection_shared_with_gds',
                'shared_credentials_present' => false,
                'credentials_shared' => true,
                'credential_keys_present' => [],
                'pcc_present' => false,
                'pcc_len' => 0,
                'sign_in_present' => false,
                'sign_in_len' => 0,
                'client_id_present' => false,
                'client_id_len' => 0,
                'note' => 'Valid shared-auth model; one Sabre SupplierConnection for GDS and NDC lanes.',
            ];
        }

        $cred = is_array($connection->credentials) ? $connection->credentials : [];
        $signIn = trim((string) ($cred['sign_in'] ?? ''));
        $password = trim((string) ($cred['password'] ?? ''));
        $pcc = trim((string) ($cred['pcc'] ?? ''));
        $clientId = trim((string) ($cred['client_id'] ?? ''));
        $clientSecret = trim((string) ($cred['client_secret'] ?? ''));
        $present = (bool) ($lane['shared_credentials_present'] ?? false);

        return [
            'source' => 'supplier_connection_shared_with_gds',
            'connection_id' => $connection->id,
            'shared_credentials_present' => $present,
            'credentials_shared' => true,
            'present' => $present,
            'credential_keys_present' => array_values(array_filter(
                array_keys($cred),
                static fn (string $k): bool => trim((string) ($cred[$k] ?? '')) !== '',
            )),
            'pcc_present' => $pcc !== '',
            'pcc_len' => strlen($pcc),
            'sign_in_present' => $signIn !== '',
            'sign_in_len' => strlen($signIn),
            'password_present' => $password !== '',
            'password_len' => strlen($password),
            'client_id_present' => $clientId !== '',
            'client_id_len' => strlen($clientId),
            'client_secret_present' => $clientSecret !== '',
            'client_secret_len' => strlen($clientSecret),
            'note' => 'Shared auth valid for NDC and GDS; GDS lane off does not remove credentials.',
        ];
    }
}
