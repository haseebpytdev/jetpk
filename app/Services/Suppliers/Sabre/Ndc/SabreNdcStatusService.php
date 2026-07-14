<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\SupplierConnection;
use App\Support\Suppliers\SabreChannelGateResolver;
use App\Support\Suppliers\SabreNdcEntitlementDiagnosticAdvisor;
use App\Support\Suppliers\SabreNdcEntitlementEvidenceStore;

/**
 * Sabre NDC module status, credential readiness, and entitlement diagnostic summary.
 */
final class SabreNdcStatusService
{
    public function __construct(
        private readonly SabreChannelGateResolver $channelGateResolver,
        private readonly SabreNdcOfferShopRequestBuilder $requestBuilder,
        private readonly SabreNdcEntitlementEvidenceStore $evidenceStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(?SupplierConnection $connection = null): array
    {
        $ndcConfig = config('suppliers.sabre.ndc', []);
        if (! is_array($ndcConfig)) {
            $ndcConfig = [];
        }

        $lane = $this->channelGateResolver->diagnostics($connection);
        $blockers = $this->channelGateResolver->ndcLaneBlockers($connection);
        $connectionId = (int) ($connection?->id ?? 0);
        $evidence = $connectionId > 0 ? $this->evidenceStore->buildEvidenceSummary($connectionId) : [];
        $messageCodeList = is_array($evidence['last_matrix_message_codes'] ?? null)
            ? $evidence['last_matrix_message_codes']
            : [];

        $entitlement = [
            'shared_credentials_present' => (bool) ($lane['shared_credentials_present'] ?? false),
            'connection_ndc_channel_enabled' => (bool) ($lane['connection_ndc_channel_enabled'] ?? false),
            'ndc_live_search_http_enabled' => (bool) ($ndcConfig['search_enabled'] ?? false),
            'selected_public_variant' => $this->requestBuilder->resolvePublicSearchVariant(null),
            'last_matrix_total_cells' => (int) ($evidence['last_matrix_total_cells'] ?? 0),
            'last_matrix_http_200_count' => (int) ($evidence['last_matrix_http_200_count'] ?? 0),
            'last_matrix_zero_offer_count' => (int) ($evidence['last_matrix_zero_offer_count'] ?? 0),
            'last_matrix_message_codes' => $messageCodeList,
            'last_matrix_recorded_at' => $evidence['last_matrix_recorded_at'] ?? null,
            'ndc_only_raw_offer_count' => (int) ($evidence['ndc_only_raw_offer_count'] ?? 0),
            'ndc_only_normalized_offer_count' => (int) ($evidence['ndc_only_normalized_offer_count'] ?? 0),
            'atpco_diagnostic_raw_offer_count' => (int) ($evidence['atpco_diagnostic_raw_offer_count'] ?? 0),
            'atpco_diagnostic_normalized_offer_count' => (int) ($evidence['atpco_diagnostic_normalized_offer_count'] ?? 0),
            'entitlement_gap_likely' => (bool) ($evidence['entitlement_gap_likely'] ?? false),
            'suggested_next_action' => SabreNdcEntitlementDiagnosticAdvisor::suggest([
                'blockers' => $blockers,
                'ndc_live_search_http_enabled' => (bool) ($ndcConfig['search_enabled'] ?? false),
                'last_matrix_total_cells' => (int) ($evidence['last_matrix_total_cells'] ?? 0),
                'last_matrix_http_200_count' => (int) ($evidence['last_matrix_http_200_count'] ?? 0),
                'last_matrix_zero_offer_count' => (int) ($evidence['last_matrix_zero_offer_count'] ?? 0),
                'last_matrix_message_codes' => array_fill_keys($messageCodeList, 1),
                'entitlement_gap_likely' => (bool) ($evidence['entitlement_gap_likely'] ?? false),
            ]),
        ];

        return [
            'lane' => 'sabre_ndc',
            'lifecycle_mode' => 'sabre_ndc_order',
            'gds_lane_separated' => true,
            'active_connection_id' => $lane['active_connection_id'],
            'selected_sabre_lanes' => $lane['selected_sabre_lanes'],
            'connection_ndc_channel_enabled' => $lane['connection_ndc_channel_enabled'],
            'connection_gds_channel_enabled' => $lane['connection_gds_channel_enabled'],
            'global_ndc_kill_switch' => $lane['global_ndc_kill_switch'],
            'global_gds_kill_switch' => $lane['global_gds_kill_switch'],
            'sabre_ndc_module_enabled' => $lane['sabre_ndc_module_enabled'],
            'sabre_gds_module_enabled' => $lane['sabre_gds_module_enabled'],
            'effective_ndc_enabled' => $lane['effective_ndc_enabled'],
            'effective_gds_enabled' => $lane['effective_gds_enabled'],
            'gds_results_suppressed' => $lane['gds_results_suppressed'],
            'ndc_results_allowed' => $lane['ndc_results_allowed'],
            'gds_suppressed' => $lane['gds_suppressed'],
            'ndc_allowed' => $lane['ndc_allowed'],
            'shared_credentials_present' => $lane['shared_credentials_present'],
            'credentials_shared' => true,
            'credentials_source' => $lane['credentials_source'],
            'credentials_present' => $lane['shared_credentials_present'],
            'connection_id' => $connection?->id,
            'ndc_live_search_http_enabled' => (bool) ($ndcConfig['search_enabled'] ?? false),
            'ndc_live_order_create_enabled' => (bool) ($ndcConfig['order_create_enabled'] ?? false),
            'blockers' => $blockers,
            'ready_for_dry_run' => true,
            'ready_for_live_order_create' => $blockers === []
                && (bool) ($ndcConfig['order_create_enabled'] ?? false),
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => false,
            'entitlement_diagnostic' => $entitlement,
        ];
    }
}
