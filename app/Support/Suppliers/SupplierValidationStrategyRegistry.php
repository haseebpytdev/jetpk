<?php

namespace App\Support\Suppliers;

use App\Enums\SupplierProvider;

/**
 * Registry of supported supplier validation / freshness strategies (separate from PNR create).
 */
final class SupplierValidationStrategyRegistry
{
    public const STRATEGY_GDS_OFFER_REFRESH = 'gds_offer_refresh';

    public const STRATEGY_GDS_BFM_REVALIDATION = 'gds_bfm_revalidation';

    public const STRATEGY_GDS_PRE_PNR_SKIP = 'gds_pre_pnr_skip';

    public const STRATEGY_NDC_OFFER_PRICE = 'ndc_offer_price';

    public const STRATEGY_NDC_ORDER_REPRICE = 'ndc_order_reprice';

    /**
     * @return list<string>
     */
    public function supportedCodesForAction(string $action): array
    {
        return match (strtolower(trim($action))) {
            SupplierValidationActionCode::GDS_OFFER_REFRESH => [self::STRATEGY_GDS_OFFER_REFRESH],
            SupplierValidationActionCode::GDS_FARE_REVALIDATION => [self::STRATEGY_GDS_BFM_REVALIDATION],
            SupplierValidationActionCode::GDS_PRE_PNR_FRESHNESS => [
                self::STRATEGY_GDS_OFFER_REFRESH,
                self::STRATEGY_GDS_BFM_REVALIDATION,
                self::STRATEGY_GDS_PRE_PNR_SKIP,
            ],
            SupplierValidationActionCode::NDC_OFFER_PRICE => [self::STRATEGY_NDC_OFFER_PRICE],
            SupplierValidationActionCode::NDC_ORDER_REPRICE => [self::STRATEGY_NDC_ORDER_REPRICE],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $strategyCode): array
    {
        return match (trim($strategyCode)) {
            self::STRATEGY_GDS_OFFER_REFRESH => $this->baseDefinition(
                self::STRATEGY_GDS_OFFER_REFRESH,
                SupplierProvider::Sabre->value,
                'gds',
                '/v1/offers/refresh',
                'gds_offer_refresh_v1',
                SupplierValidationActionCode::GDS_OFFER_REFRESH,
                automaticAllowed: true,
                adminConfirmedFallbackAllowed: false,
                requiredContextFields: ['supplier_connection_id', 'selected_offer_id', 'sabre_booking_context'],
                safeFailureFamilies: ['offer_refresh_unavailable', 'offer_refresh_failed', 'fare_changed'],
            ),
            self::STRATEGY_GDS_BFM_REVALIDATION => $this->baseDefinition(
                self::STRATEGY_GDS_BFM_REVALIDATION,
                SupplierProvider::Sabre->value,
                'gds',
                '/v5/shop/flights/revalidate',
                'bfm_revalidate_v1',
                SupplierValidationActionCode::GDS_FARE_REVALIDATION,
                automaticAllowed: true,
                adminConfirmedFallbackAllowed: true,
                requiredContextFields: ['supplier_connection_id', 'segments', 'validating_carrier'],
                safeFailureFamilies: ['revalidation_failed', 'revalidation_gatekeeper_failed', 'fare_changed'],
            ),
            self::STRATEGY_GDS_PRE_PNR_SKIP => $this->baseDefinition(
                self::STRATEGY_GDS_PRE_PNR_SKIP,
                SupplierProvider::Sabre->value,
                'gds',
                null,
                'gds_pre_pnr_skip',
                SupplierValidationActionCode::GDS_PRE_PNR_FRESHNESS,
                automaticAllowed: true,
                adminConfirmedFallbackAllowed: false,
                requiredContextFields: ['supplier_connection_id', 'sabre_booking_context'],
                safeFailureFamilies: ['freshness_not_required', 'offer_refresh_satisfied'],
            ),
            self::STRATEGY_NDC_OFFER_PRICE => $this->baseDefinition(
                self::STRATEGY_NDC_OFFER_PRICE,
                SupplierProvider::Sabre->value,
                'ndc',
                '/v1/offers/price',
                'ndc_offer_price_v1',
                SupplierValidationActionCode::NDC_OFFER_PRICE,
                automaticAllowed: true,
                adminConfirmedFallbackAllowed: true,
                requiredContextFields: ['supplier_connection_id', 'ndc_offer_id'],
                safeFailureFamilies: ['ndc_offer_price_failed', 'ndc_offer_price_disabled'],
            ),
            self::STRATEGY_NDC_ORDER_REPRICE => $this->baseDefinition(
                self::STRATEGY_NDC_ORDER_REPRICE,
                SupplierProvider::Sabre->value,
                'ndc',
                '/v1/orders/reprice',
                'ndc_order_reprice_v1',
                SupplierValidationActionCode::NDC_ORDER_REPRICE,
                automaticAllowed: false,
                adminConfirmedFallbackAllowed: true,
                requiredContextFields: ['supplier_connection_id', 'ndc_order_id'],
                safeFailureFamilies: ['ndc_order_reprice_failed'],
            ),
            default => throw new \InvalidArgumentException('Unsupported validation strategy: '.$strategyCode),
        };
    }

    /**
     * @param  list<string>  $requiredContextFields
     * @param  list<string>  $safeFailureFamilies
     * @return array<string, mixed>
     */
    protected function baseDefinition(
        string $strategyCode,
        string $provider,
        string $distributionChannel,
        ?string $endpointPath,
        string $payloadSchema,
        string $actionCode,
        bool $automaticAllowed,
        bool $adminConfirmedFallbackAllowed,
        array $requiredContextFields,
        array $safeFailureFamilies,
    ): array {
        return [
            'strategy_code' => $strategyCode,
            'provider' => $provider,
            'distribution_channel' => $distributionChannel,
            'action_code' => $actionCode,
            'endpoint_path' => $endpointPath,
            'payload_schema' => $payloadSchema,
            'automatic_allowed' => $automaticAllowed,
            'admin_confirmed_fallback_allowed' => $adminConfirmedFallbackAllowed,
            'required_context_fields' => $requiredContextFields,
            'safe_failure_classifications' => $safeFailureFamilies,
        ];
    }
}
