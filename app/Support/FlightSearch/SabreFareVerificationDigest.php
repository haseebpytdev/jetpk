<?php

namespace App\Support\FlightSearch;

use App\Models\Agency;
use App\Services\FlightSearch\FlightSearchService;
use App\Services\Pricing\PricingRuleService;

/**
 * Safe fare verification fields for Sabre shop offers (no secrets, no raw Sabre payload).
 */
final class SabreFareVerificationDigest
{
    public const STATUS_OK = 'ok';

    public const STATUS_PRICE_MISMATCH = 'price_mismatch';

    public const STATUS_OFFER_IDENTITY_MISMATCH = 'offer_identity_mismatch';

    public const STATUS_STALE_CACHED_RESULT_POSSIBLE = 'stale_cached_result_possible';

    public const STATUS_NORMALIZER_RAW_MISMATCH = 'normalizer_raw_vs_normalized_mismatch';

    public static function shortOfferId(?string $offerId): string
    {
        if ($offerId === null || trim($offerId) === '') {
            return '';
        }

        return substr(hash('sha256', $offerId), 0, 12);
    }

    /**
     * @param  array<string, mixed>  $displayOffer  Row after {@see FlightSearchService::toDisplayOffer}
     * @return array<string, mixed>
     */
    public static function buildFromDisplayOffer(array $displayOffer): array
    {
        $offerId = (string) ($displayOffer['offer_id'] ?? $displayOffer['id'] ?? '');
        $fare = is_array($displayOffer['fare_breakdown'] ?? null) ? $displayOffer['fare_breakdown'] : [];
        $rawPayload = is_array($displayOffer['raw_payload'] ?? null) ? $displayOffer['raw_payload'] : [];
        $excerpt = is_array($rawPayload['sabre_fare_excerpt'] ?? null) ? $rawPayload['sabre_fare_excerpt'] : [];
        $legacyFare = is_array($rawPayload['fare'] ?? null) ? $rawPayload['fare'] : [];

        $rawTotal = (float) ($excerpt['total_price'] ?? $legacyFare['total'] ?? $fare['supplier_total'] ?? 0);
        $rawCur = strtoupper(trim((string) ($excerpt['currency'] ?? $legacyFare['currency'] ?? $fare['currency'] ?? '')));

        $normTotal = (float) ($fare['supplier_total'] ?? 0);
        $normCur = strtoupper(trim((string) ($fare['currency'] ?? '')));

        $pricing = is_array($displayOffer['pricing_components'] ?? null) ? $displayOffer['pricing_components'] : [];
        $pricingSupplierTotal = (float) ($pricing['supplier_total'] ?? 0);
        $pricingCurrency = strtoupper(trim((string) ($pricing['pricing_currency'] ?? $displayOffer['pricing_currency'] ?? $displayOffer['currency'] ?? '')));

        $markup = (float) ($displayOffer['markup'] ?? 0);
        $serviceFee = (float) ($displayOffer['service_fee'] ?? 0);
        $final = (float) ($displayOffer['final_customer_price'] ?? $displayOffer['total'] ?? 0);
        $conversionStatus = (string) ($displayOffer['conversion_status'] ?? 'unknown');

        $supplierTotalSource = (float) ($displayOffer['supplier_total_source'] ?? 0);

        $routeChain = self::routeChainFromOffer($displayOffer);
        $carrierChain = self::carrierChainFromOffer($displayOffer);
        $flightNumbers = self::flightNumbersFromOffer($displayOffer);

        $uiDisplay = (float) round($final, 0);

        $staleCandidate = $supplierTotalSource > 0 && $normTotal > 0 && abs($supplierTotalSource - $normTotal) > 0.5;

        $displayBase = (float) ($displayOffer['base_fare'] ?? $fare['display_base_fare'] ?? $fare['base_fare'] ?? 0);
        $displayTaxes = (float) ($displayOffer['taxes'] ?? $fare['display_taxes'] ?? $fare['taxes'] ?? 0);
        $breakdownReconciled = (bool) ($fare['breakdown_reconciled'] ?? false);
        $baseDisplaySource = (string) ($fare['base_fare_display_source'] ?? '');
        $supplierForBreakdown = $pricingSupplierTotal > 0 ? $pricingSupplierTotal : $normTotal;
        $breakdownSumMatchesSupplier = $supplierForBreakdown > 0
            && abs($displayBase + $displayTaxes - $supplierForBreakdown) <= 0.5;
        $breakdownSumMatchesTotal = abs($displayBase + $displayTaxes + $markup + $serviceFee - $final) <= 0.05;

        $status = self::resolveVerificationStatus(
            rawTotal: $rawTotal,
            rawCurrency: $rawCur,
            normalizedTotal: $normTotal,
            normalizedCurrency: $normCur,
            pricingSupplierTotal: $pricingSupplierTotal,
            pricingCurrency: $pricingCurrency,
            finalCustomerPrice: $final,
            uiDisplayPrice: $uiDisplay,
            markupAmount: $markup,
            serviceFeeAmount: $serviceFee,
        );

        return [
            'offer_id' => $offerId,
            'short_offer_id' => self::shortOfferId($offerId),
            'route_chain' => $routeChain,
            'carrier_chain' => $carrierChain,
            'flight_numbers' => $flightNumbers,
            'raw_total_fare' => $rawTotal,
            'raw_fare_currency' => $rawCur,
            'raw_total_source_field' => (string) ($excerpt['total_price_field'] ?? 'totalFare.totalPrice'),
            'normalized_supplier_total' => $normTotal,
            'normalized_supplier_currency' => $normCur,
            'pricing_supplier_total' => $pricingSupplierTotal,
            'pricing_currency' => $pricingCurrency,
            'markup_amount' => $markup,
            'service_fee_amount' => $serviceFee,
            'final_customer_price' => $final,
            'ui_display_price' => $uiDisplay,
            'expected_ui_price' => $uiDisplay,
            'conversion_status' => $conversionStatus,
            'fare_verification_status' => $status,
            'stale_cached_result_possible' => $staleCandidate,
            'offer_identity_mismatch' => false,
            'breakdown_reconciled' => $breakdownReconciled,
            'base_display_source' => $baseDisplaySource !== '' ? $baseDisplaySource : null,
            'breakdown_sum_matches_supplier_total' => $breakdownSumMatchesSupplier,
            'breakdown_sum_matches_total' => $breakdownSumMatchesTotal,
        ];
    }

    /**
     * @param  array<string, mixed>  $row  Inspect digest row (accepted)
     * @param  array<string, mixed>  $pricingContext  {@see PricingRuleService::calculateMarkup} context
     * @return array<string, mixed>
     */
    public static function enrichInspectRowWithPricing(
        array $row,
        ?Agency $agency,
        PricingRuleService $pricing,
        array $pricingContext,
    ): array {
        $blank = [
            'pricing_supplier_total' => null,
            'final_customer_price' => null,
            'markup_amount' => null,
            'service_fee_amount' => null,
            'display_price_candidate' => null,
            'fare_verification_status' => null,
        ];
        if ($agency === null || ($row['normalizer_status'] ?? '') !== 'accepted') {
            return array_merge($row, $blank);
        }

        $supplierTotal = (float) ($row['normalized_total'] ?? 0);
        $p = $pricing->calculateMarkup($agency, [
            'base_fare' => (float) ($row['normalized_base_fare'] ?? 0),
            'taxes' => (float) ($row['normalized_taxes'] ?? 0),
            'supplier_total' => $supplierTotal > 0 ? $supplierTotal : 0.0,
            'currency' => (string) ($row['normalized_currency'] ?? 'PKR'),
        ], $pricingContext);

        $markup = (float) ($p['admin_markup'] ?? 0)
            + (float) ($p['route_markup'] ?? 0)
            + (float) ($p['airline_markup'] ?? 0)
            + (float) ($p['agent_markup_or_commission'] ?? 0);
        $serviceFee = (float) ($p['service_fee'] ?? 0);
        $final = (float) ($p['final_total'] ?? 0);

        return array_merge($row, [
            'pricing_supplier_total' => (float) ($p['supplier_total'] ?? 0),
            'pricing_currency' => (string) ($p['pricing_currency'] ?? 'PKR'),
            'final_customer_price' => $final,
            'markup_amount' => $markup,
            'service_fee_amount' => $serviceFee,
            'display_price_candidate' => round($final, 0),
            'fare_verification_status' => self::verifyInspectPricingRow($row, $p, $markup, $serviceFee),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $pricing
     */
    public static function verifyInspectPricingRow(array $row, array $pricing, float $markupSum, float $serviceFee): string
    {
        $raw = (float) ($row['total_fare'] ?? 0);
        $norm = (float) ($row['normalized_total'] ?? 0);
        $ps = (float) ($pricing['supplier_total'] ?? 0);
        $rawCur = strtoupper(trim((string) ($row['fare_currency'] ?? '')));
        $normCur = strtoupper(trim((string) ($row['normalized_currency'] ?? '')));
        if ($rawCur !== '' && $normCur !== '' && $rawCur === $normCur && abs($raw - $norm) > 0.5) {
            return self::STATUS_NORMALIZER_RAW_MISMATCH;
        }
        $pc = strtoupper(trim((string) ($pricing['pricing_currency'] ?? '')));
        if ($normCur !== '' && $pc !== '' && $normCur === $pc && abs($norm - $ps) > 0.5) {
            return self::STATUS_PRICE_MISMATCH;
        }
        $final = (float) ($pricing['final_total'] ?? 0);
        if (abs($final - ($ps + $markupSum + $serviceFee)) > 0.05) {
            return self::STATUS_PRICE_MISMATCH;
        }

        return self::STATUS_OK;
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    public static function fareDebugForApi(array $digest): array
    {
        return [
            'short_offer_id' => (string) ($digest['short_offer_id'] ?? ''),
            'supplier_total' => (float) ($digest['pricing_supplier_total'] ?? 0),
            'supplier_currency' => (string) ($digest['pricing_currency'] ?? ''),
            'final_customer_price' => (float) ($digest['final_customer_price'] ?? 0),
            'final_currency' => (string) ($digest['pricing_currency'] ?? ''),
            'raw_total_if_available' => (float) ($digest['raw_total_fare'] ?? 0),
            'raw_currency' => (string) ($digest['raw_fare_currency'] ?? ''),
            'conversion_status' => (string) ($digest['conversion_status'] ?? ''),
            'fare_verification_status' => (string) ($digest['fare_verification_status'] ?? ''),
            'breakdown_reconciled' => (bool) ($digest['breakdown_reconciled'] ?? false),
            'base_display_source' => (string) ($digest['base_display_source'] ?? ''),
            'breakdown_sum_matches_supplier_total' => (bool) ($digest['breakdown_sum_matches_supplier_total'] ?? false),
            'breakdown_sum_matches_total' => (bool) ($digest['breakdown_sum_matches_total'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $displayOffer
     */
    protected static function routeChainFromOffer(array $displayOffer): string
    {
        $segs = is_array($displayOffer['segments'] ?? null) ? $displayOffer['segments'] : [];
        if ($segs === []) {
            $o = strtoupper(trim((string) ($displayOffer['origin'] ?? '')));
            $d = strtoupper(trim((string) ($displayOffer['destination'] ?? '')));

            return $o !== '' && $d !== '' ? $o.'-'.$d : '';
        }
        $parts = [];
        $first = $segs[0] ?? null;
        if (is_array($first)) {
            $parts[] = strtoupper(trim((string) ($first['origin'] ?? '')));
        }
        foreach ($segs as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $parts[] = strtoupper(trim((string) ($seg['destination'] ?? '')));
        }

        return implode('-', array_values(array_filter($parts, fn (string $p): bool => $p !== '')));
    }

    /**
     * @param  array<string, mixed>  $displayOffer
     */
    protected static function carrierChainFromOffer(array $displayOffer): string
    {
        $m = $displayOffer['marketing_carrier_chain'] ?? null;
        if (is_array($m) && $m !== []) {
            return implode('+', array_map('strval', $m));
        }

        return strtoupper(trim((string) ($displayOffer['airline_code'] ?? '')));
    }

    /**
     * @param  array<string, mixed>  $displayOffer
     */
    protected static function flightNumbersFromOffer(array $displayOffer): string
    {
        $segs = is_array($displayOffer['segments'] ?? null) ? $displayOffer['segments'] : [];
        $bits = [];
        foreach ($segs as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $c = strtoupper(trim((string) ($seg['airline_code'] ?? '')));
            $fn = trim((string) ($seg['flight_number'] ?? ''));
            if ($c !== '' && $fn !== '') {
                $bits[] = $c.$fn;
            } elseif ($fn !== '') {
                $bits[] = $fn;
            }
        }

        if ($bits !== []) {
            return implode('+', $bits);
        }

        return trim((string) ($displayOffer['flight_number'] ?? ''));
    }

    protected static function resolveVerificationStatus(
        float $rawTotal,
        string $rawCurrency,
        float $normalizedTotal,
        string $normalizedCurrency,
        float $pricingSupplierTotal,
        string $pricingCurrency,
        float $finalCustomerPrice,
        float $uiDisplayPrice,
        float $markupAmount,
        float $serviceFeeAmount,
    ): string {
        $eps = 0.5;
        if ($rawCurrency !== '' && $normalizedCurrency !== ''
            && $rawCurrency === $normalizedCurrency
            && abs($rawTotal - $normalizedTotal) > $eps) {
            return self::STATUS_NORMALIZER_RAW_MISMATCH;
        }

        if ($normalizedCurrency !== '' && $pricingCurrency !== ''
            && $normalizedCurrency === $pricingCurrency
            && abs($normalizedTotal - $pricingSupplierTotal) > $eps) {
            return self::STATUS_PRICE_MISMATCH;
        }

        $pricedParts = $pricingSupplierTotal + $markupAmount + $serviceFeeAmount;
        if (abs($finalCustomerPrice - $pricedParts) > 0.05) {
            return self::STATUS_PRICE_MISMATCH;
        }

        return self::STATUS_OK;
    }
}
