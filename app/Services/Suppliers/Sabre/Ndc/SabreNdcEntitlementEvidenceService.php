<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\SupplierConnection;
use App\Support\Suppliers\SabreChannelGateResolver;
use App\Support\Suppliers\SabreNdcEntitlementDiagnosticAdvisor;
use App\Support\Suppliers\SabreNdcEntitlementEvidenceStore;

/**
 * Safe Sabre NDC entitlement evidence export for client/Sabre escalation (no secrets/payloads).
 */
final class SabreNdcEntitlementEvidenceService
{
    public function __construct(
        private readonly SabreChannelGateResolver $channelGateResolver,
        private readonly SabreNdcOfferShopRequestBuilder $requestBuilder,
        private readonly SabreNdcEntitlementEvidenceStore $evidenceStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function export(?SupplierConnection $connection = null, bool $verboseSafe = false): array
    {
        $connectionId = (int) ($connection?->id ?? 0);
        $lane = $this->channelGateResolver->diagnostics($connection);
        $evidence = $connectionId > 0 ? $this->evidenceStore->buildEvidenceSummary($connectionId) : [];
        $matrix = $connectionId > 0 ? ($this->evidenceStore->lastMatrix($connectionId) ?? []) : [];
        $messageCodes = is_array($evidence['last_matrix_message_codes'] ?? null)
            ? $evidence['last_matrix_message_codes']
            : [];

        $transactionIdCount = 0;
        if ($verboseSafe && $connectionId > 0) {
            $transactionIdCount = (int) ($matrix['zero_offer_count'] ?? 0);
        }

        return [
            'lane' => 'sabre_ndc',
            'connection_id' => $connection?->id,
            'endpoint_path' => (string) config('suppliers.sabre.ndc.offer_shop_path', '/v5/offers/shop'),
            'selected_public_variant' => $this->requestBuilder->resolvePublicSearchVariant(null),
            'shared_credentials_present' => (bool) ($lane['shared_credentials_present'] ?? false),
            'connection_ndc_channel_enabled' => (bool) ($lane['connection_ndc_channel_enabled'] ?? false),
            'ndc_live_search_http_enabled' => (bool) config('suppliers.sabre.ndc.search_enabled', false),
            'gds_called' => false,
            'mutation_attempted' => false,
            'live_supplier_call_attempted' => false,
            'ndc_only_matrix_summary' => [
                'total_cells' => (int) ($evidence['last_matrix_total_cells'] ?? 0),
                'http_200_count' => (int) ($evidence['last_matrix_http_200_count'] ?? 0),
                'zero_offer_count' => (int) ($evidence['last_matrix_zero_offer_count'] ?? 0),
                'message_codes' => $messageCodes,
                'recorded_at' => $evidence['last_matrix_recorded_at'] ?? null,
            ],
            'variant_comparison' => [
                'ndc_only_raw_offer_count' => (int) ($evidence['ndc_only_raw_offer_count'] ?? 0),
                'ndc_only_normalized_offer_count' => (int) ($evidence['ndc_only_normalized_offer_count'] ?? 0),
                'atpco_diagnostic_raw_offer_count' => (int) ($evidence['atpco_diagnostic_raw_offer_count'] ?? 0),
                'atpco_diagnostic_normalized_offer_count' => (int) ($evidence['atpco_diagnostic_normalized_offer_count'] ?? 0),
            ],
            'primary_sabre_message_code' => $evidence['primary_message_code'] ?? null,
            'entitlement_gap_likely' => (bool) ($evidence['entitlement_gap_likely'] ?? false),
            'transaction_id_observation_count' => $transactionIdCount,
            'recommended_client_sabre_question' => SabreNdcEntitlementDiagnosticAdvisor::suggest([
                'blockers' => $this->channelGateResolver->ndcLaneBlockers($connection),
                'ndc_live_search_http_enabled' => (bool) config('suppliers.sabre.ndc.search_enabled', false),
                'last_matrix_total_cells' => (int) ($evidence['last_matrix_total_cells'] ?? 0),
                'last_matrix_http_200_count' => (int) ($evidence['last_matrix_http_200_count'] ?? 0),
                'last_matrix_zero_offer_count' => (int) ($evidence['last_matrix_zero_offer_count'] ?? 0),
                'last_matrix_message_codes' => array_fill_keys($messageCodes, 1),
                'entitlement_gap_likely' => (bool) ($evidence['entitlement_gap_likely'] ?? false),
            ]),
        ];
    }
}
