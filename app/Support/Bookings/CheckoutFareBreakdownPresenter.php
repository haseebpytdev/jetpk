<?php

namespace App\Support\Bookings;

use App\Models\BookingFareBreakdown;

/**
 * RETURN-SPLIT-SELECT-R2/R3 — authoritative checkout fare breakdown for sidebar/review.
 *
 * Resolves PKR display rows from pricing_components; avoids misleading supplier-currency passenger_pricing.
 * R3: "Agency charges" row shows admin_markup bucket only (engine-sourced, no legacy offer.markup fallback).
 * R4: authoritative total reconciles base + taxes + visible admin/service fees; rejects hidden non-admin buckets.
 */
class CheckoutFareBreakdownPresenter
{
    private const RECONCILIATION_TOLERANCE = 2.0;

    /**
     * @param  array<string, mixed>|null  $offer  Post-presentValidatedOffer snapshot
     * @param  array{adults?: int, children?: int, infants?: int, total?: int}|null  $passengerCounts
     * @return array{
     *     mode: string,
     *     currency: string,
     *     total: float,
     *     rows: list<array{label: string, amount: float, type?: string}>,
     *     show_passenger_mix: bool,
     *     passenger_mix: array{adults: int, children: int, infants: int, total: int},
     *     fee_source: array<string, mixed>,
     *     reconciliation_delta: float,
     * }
     */
    public static function present(?array $offer, ?BookingFareBreakdown $fareBreakdown = null, ?array $passengerCounts = null): array
    {
        $offer = is_array($offer) ? $offer : [];
        $pricing = is_array($offer['pricing_components'] ?? null) ? $offer['pricing_components'] : [];
        $displayCurrency = strtoupper(trim((string) ($pricing['pricing_currency'] ?? $offer['currency'] ?? 'PKR')));
        if ($displayCurrency === '') {
            $displayCurrency = 'PKR';
        }

        $conversionStatus = (string) ($pricing['conversion_status'] ?? $offer['conversion_status'] ?? 'same_currency');

        $totalFromDb = $fareBreakdown !== null ? (float) ($fareBreakdown->total ?? 0) : 0.0;
        $totalFromOffer = (float) ($offer['total'] ?? $offer['final_customer_price'] ?? $pricing['final_total'] ?? 0);

        $adminMarkup = self::adminMarkupAmount($pricing, $offer, $fareBreakdown);
        $serviceFee = self::serviceFeeAmount($pricing, $offer, $fareBreakdown);
        $appliedRules = is_array($pricing['applied_rules'] ?? null) ? $pricing['applied_rules'] : [];
        $markupDisplayEligible = self::markupDisplayEligible($adminMarkup, $appliedRules);
        $serviceFeeDisplayEligible = self::serviceFeeDisplayEligible($serviceFee, $appliedRules);

        $baseFareForTotal = self::resolveBaseFare($offer, $pricing, $fareBreakdown);
        $taxesForTotal = self::resolveTaxes($offer, $pricing, $fareBreakdown);
        $authoritativeTotal = self::resolveAuthoritativeTotal(
            $totalFromDb,
            $totalFromOffer,
            $pricing,
            $baseFareForTotal,
            $taxesForTotal,
            $adminMarkup,
            $markupDisplayEligible,
            $serviceFee,
            $serviceFeeDisplayEligible,
        );

        $feeSource = [
            'conversion_status' => $conversionStatus,
            'pricing_currency' => $displayCurrency,
            'supplier_currency' => (string) ($pricing['supplier_currency'] ?? ''),
            'admin_markup' => (float) ($pricing['admin_markup'] ?? 0),
            'route_markup' => (float) ($pricing['route_markup'] ?? 0),
            'airline_markup' => (float) ($pricing['airline_markup'] ?? 0),
            'agent_markup_or_commission' => (float) ($pricing['agent_markup_or_commission'] ?? 0),
            'service_fee' => $serviceFee,
            'markup_total' => $markupDisplayEligible ? $adminMarkup : 0.0,
            'markup_display_eligible' => $markupDisplayEligible,
            'service_fee_display_eligible' => $serviceFeeDisplayEligible,
            'applied_rules' => $appliedRules,
            'total_source' => $totalFromDb > 0 ? 'booking_fare_breakdown' : 'offer_pricing_snapshot',
            'public_pricing_rejected_markup' => (float) ($pricing['public_pricing_rejected_markup'] ?? 0),
            'public_pricing_sanitized' => (bool) ($pricing['public_pricing_sanitized'] ?? false),
        ];

        $passengerMix = [
            'adults' => max(0, (int) ($passengerCounts['adults'] ?? 1)),
            'children' => max(0, (int) ($passengerCounts['children'] ?? 0)),
            'infants' => max(0, (int) ($passengerCounts['infants'] ?? 0)),
            'total' => max(0, (int) ($passengerCounts['total'] ?? 0)),
        ];
        if ($passengerMix['total'] <= 0) {
            $passengerMix['total'] = $passengerMix['adults'] + $passengerMix['children'] + $passengerMix['infants'];
        }

        $groupedPassenger = self::groupPassengerPricing($offer);
        $passengerPricingAvailable = (bool) (data_get($offer, 'fare_breakdown.passenger_pricing_available') ?? $groupedPassenger['has_rows']);
        $passengerRowsTrusted = $passengerPricingAvailable
            && self::passengerPricingCurrencyTrusted($offer, $displayCurrency, $conversionStatus)
            && self::passengerPricingReconciles(
                $groupedPassenger,
                $authoritativeTotal,
                $markupDisplayEligible ? $adminMarkup : 0.0,
                $serviceFeeDisplayEligible ? $serviceFee : 0.0,
            );

        $rows = [];
        $mode = 'total_only';
        $reconciliationDelta = 0.0;

        if ($authoritativeTotal <= 0) {
            return self::result($mode, $displayCurrency, 0.0, $rows, false, $passengerMix, $feeSource, $reconciliationDelta);
        }

        if ($passengerRowsTrusted) {
            $mode = 'detailed';
            foreach (['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'] as $type => $label) {
                $group = $groupedPassenger['groups'][$type];
                if ($group['count'] <= 0) {
                    continue;
                }
                $rows[] = [
                    'label' => $label.' × '.$group['count'],
                    'amount' => round($group['total'], 2),
                    'type' => 'passenger',
                ];
            }
            $reconciliationDelta = self::reconciliationDelta(
                $groupedPassenger['grand_total'],
                $authoritativeTotal,
                $markupDisplayEligible ? $adminMarkup : 0.0,
                $serviceFeeDisplayEligible ? $serviceFee : 0.0,
            );
        } else {
            $baseFare = self::resolveBaseFare($offer, $pricing, $fareBreakdown);
            $taxes = self::resolveTaxes($offer, $pricing, $fareBreakdown);

            if ($baseFare > 0 || $taxes > 0) {
                $mode = 'simplified';
                if ($baseFare > 0) {
                    $rows[] = ['label' => 'Base fare', 'amount' => round($baseFare, 2), 'type' => 'base'];
                }
                if ($taxes > 0) {
                    $rows[] = ['label' => 'Taxes & fees', 'amount' => round($taxes, 2), 'type' => 'taxes'];
                }
                $reconciliationDelta = abs(($baseFare + $taxes + ($markupDisplayEligible ? $adminMarkup : 0.0) + ($serviceFeeDisplayEligible ? $serviceFee : 0.0)) - $authoritativeTotal);
            }
        }

        if ($markupDisplayEligible) {
            $rows[] = ['label' => 'Agency charges', 'amount' => round($adminMarkup, 2), 'type' => 'markup'];
        }
        if ($serviceFeeDisplayEligible) {
            $rows[] = ['label' => 'Service fee', 'amount' => round($serviceFee, 2), 'type' => 'service_fee'];
        }

        if ($rows === [] && $authoritativeTotal > 0) {
            $mode = 'total_only';
        }

        $rows[] = [
            'label' => $mode === 'total_only' ? 'Estimated total' : 'Total',
            'amount' => round($authoritativeTotal, 2),
            'type' => 'total',
        ];

        return self::result(
            $mode,
            $displayCurrency,
            round($authoritativeTotal, 2),
            $rows,
            $passengerRowsTrusted,
            $passengerMix,
            $feeSource,
            round($reconciliationDelta, 2),
        );
    }

    /**
     * @return array{input: array<string, mixed>, resolved: array<string, mixed>, markup_source: array<string, mixed>}
     */
    public static function traceAgencyChargePayload(
        ?array $offer,
        array $presented,
        ?string $searchId = null,
        ?string $offerId = null,
    ): array {
        $debug = self::debugLogPayload($offer, $presented, $searchId, $offerId);
        $feeSource = is_array($presented['fee_source'] ?? null) ? $presented['fee_source'] : [];
        $appliedRules = is_array($feeSource['applied_rules'] ?? null) ? $feeSource['applied_rules'] : [];
        $adminRules = array_values(array_filter($appliedRules, static function (array $rule): bool {
            return strtolower((string) ($rule['bucket'] ?? '')) === 'admin_markup';
        }));

        return [
            'input' => $debug['input'],
            'resolved' => array_merge($debug['resolved'], [
                'admin_markup' => (float) ($feeSource['admin_markup'] ?? 0),
                'service_fee' => (float) ($feeSource['service_fee'] ?? 0),
                'markup_display_eligible' => (bool) ($feeSource['markup_display_eligible'] ?? false),
                'service_fee_display_eligible' => (bool) ($feeSource['service_fee_display_eligible'] ?? false),
                'displayed_agency_charge' => self::rowAmountByType($presented, 'markup'),
                'displayed_service_fee' => self::rowAmountByType($presented, 'service_fee'),
            ]),
            'markup_source' => [
                'applied_rules' => $appliedRules,
                'admin_markup_rules' => $adminRules,
                'route_markup' => (float) ($feeSource['route_markup'] ?? 0),
                'airline_markup' => (float) ($feeSource['airline_markup'] ?? 0),
                'agent_markup_or_commission' => (float) ($feeSource['agent_markup_or_commission'] ?? 0),
                'source' => $feeSource['total_source'] ?? 'offer_pricing_snapshot',
            ],
            'fee_source' => $feeSource,
        ];
    }

    /**
     * @return array{input: array<string, mixed>, resolved: array<string, mixed>}
     */
    public static function debugLogPayload(?array $offer, array $presented, ?string $searchId = null, ?string $offerId = null): array
    {
        $offer = is_array($offer) ? $offer : [];
        $pricing = is_array($offer['pricing_components'] ?? null) ? $offer['pricing_components'] : [];
        $passengerPricing = data_get($offer, 'fare_breakdown.passenger_pricing');
        $passengerSampleTotal = null;
        $passengerSampleCurrency = null;
        if (is_array($passengerPricing) && isset($passengerPricing[0]) && is_array($passengerPricing[0])) {
            $passengerSampleTotal = (float) ($passengerPricing[0]['total_amount'] ?? 0);
            $passengerSampleCurrency = (string) ($passengerPricing[0]['currency'] ?? '');
        }

        return [
            'input' => [
                'search_id' => $searchId,
                'offer_id' => $offerId,
                'conversion_status' => (string) ($pricing['conversion_status'] ?? $offer['conversion_status'] ?? ''),
                'pricing_currency' => (string) ($pricing['pricing_currency'] ?? $offer['currency'] ?? ''),
                'offer_total' => (float) ($offer['total'] ?? 0),
                'pricing_final_total' => (float) ($pricing['final_total'] ?? 0),
                'passenger_pricing_available' => (bool) data_get($offer, 'fare_breakdown.passenger_pricing_available'),
                'passenger_sample_total' => $passengerSampleTotal,
                'passenger_sample_currency' => $passengerSampleCurrency,
            ],
            'resolved' => [
                'mode' => (string) ($presented['mode'] ?? ''),
                'total' => (float) ($presented['total'] ?? 0),
                'row_count' => count($presented['rows'] ?? []),
                'reconciliation_delta' => (float) ($presented['reconciliation_delta'] ?? 0),
                'show_passenger_mix' => (bool) ($presented['show_passenger_mix'] ?? false),
            ],
            'fee_source' => is_array($presented['fee_source'] ?? null) ? $presented['fee_source'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $offer
     */
    private static function adminMarkupAmount(array $pricing, array $offer, ?BookingFareBreakdown $fareBreakdown): float
    {
        if ($pricing !== []) {
            return (float) ($pricing['admin_markup'] ?? 0);
        }

        if ($fareBreakdown !== null && is_array($fareBreakdown->breakdown)) {
            foreach ($fareBreakdown->breakdown as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (($row['label'] ?? '') === 'Admin markup') {
                    return (float) ($row['amount'] ?? 0);
                }
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  array<string, mixed>  $offer
     */
    private static function serviceFeeAmount(array $pricing, array $offer, ?BookingFareBreakdown $fareBreakdown): float
    {
        if ($pricing !== []) {
            return (float) ($pricing['service_fee'] ?? 0);
        }

        if ($fareBreakdown !== null && (float) ($fareBreakdown->fees ?? 0) > 0) {
            return (float) $fareBreakdown->fees;
        }

        return 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $appliedRules
     */
    private static function markupDisplayEligible(float $adminMarkup, array $appliedRules): bool
    {
        if ($adminMarkup <= 0) {
            return false;
        }

        if ($appliedRules === []) {
            return false;
        }

        foreach ($appliedRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            if (strtolower((string) ($rule['bucket'] ?? '')) === 'admin_markup') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $appliedRules
     */
    private static function serviceFeeDisplayEligible(float $serviceFee, array $appliedRules): bool
    {
        if ($serviceFee <= 0) {
            return false;
        }

        if ($appliedRules === []) {
            return false;
        }

        foreach ($appliedRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            if (strtolower((string) ($rule['bucket'] ?? '')) === 'service_fee') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $presented
     */
    private static function rowAmountByType(array $presented, string $type): float
    {
        foreach ($presented['rows'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['type'] ?? '') === $type) {
                return (float) ($row['amount'] ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private static function resolveAuthoritativeTotal(
        float $totalFromDb,
        float $totalFromOffer,
        array $pricing,
        float $baseFare,
        float $taxes,
        float $adminMarkup,
        bool $markupDisplayEligible,
        float $serviceFee,
        bool $serviceFeeDisplayEligible,
    ): float {
        if ($totalFromDb > 0) {
            return $totalFromDb;
        }

        $visibleMarkup = $markupDisplayEligible ? $adminMarkup : 0.0;
        $visibleServiceFee = $serviceFeeDisplayEligible ? $serviceFee : 0.0;
        $componentTotal = round($baseFare + $taxes + $visibleMarkup + $visibleServiceFee, 2);

        if ($pricing !== []) {
            if (! empty($pricing['public_pricing_sanitized'])) {
                return (float) ($pricing['final_total'] ?? $componentTotal);
            }

            if ($componentTotal > 0 && abs($totalFromOffer - $componentTotal) > self::RECONCILIATION_TOLERANCE) {
                return $componentTotal;
            }

            return $totalFromOffer > 0 ? $totalFromOffer : $componentTotal;
        }

        return $totalFromOffer;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $pricing
     */
    private static function resolveBaseFare(array $offer, array $pricing, ?BookingFareBreakdown $fareBreakdown): float
    {
        if ($fareBreakdown !== null && (float) ($fareBreakdown->base_fare ?? 0) > 0) {
            return (float) $fareBreakdown->base_fare;
        }
        if ((float) ($pricing['base_fare'] ?? 0) > 0) {
            return (float) $pricing['base_fare'];
        }

        return (float) (data_get($offer, 'fare_breakdown.display_base_fare') ?? $offer['base_fare'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $pricing
     */
    private static function resolveTaxes(array $offer, array $pricing, ?BookingFareBreakdown $fareBreakdown): float
    {
        if ($fareBreakdown !== null && (float) ($fareBreakdown->taxes ?? 0) > 0) {
            return (float) $fareBreakdown->taxes;
        }
        if ((float) ($pricing['taxes'] ?? 0) > 0) {
            return (float) ($pricing['taxes'] ?? 0);
        }

        return (float) (data_get($offer, 'fare_breakdown.display_taxes') ?? $offer['taxes'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{
     *     has_rows: bool,
     *     grand_total: float,
     *     groups: array<string, array{count: int, base: float, tax: float, total: float}>
     * }
     */
    private static function groupPassengerPricing(array $offer): array
    {
        $groups = [
            'adult' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
            'child' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
            'infant' => ['count' => 0, 'base' => 0.0, 'tax' => 0.0, 'total' => 0.0],
        ];
        $rows = data_get($offer, 'fare_breakdown.passenger_pricing');
        if (! is_array($rows)) {
            return ['has_rows' => false, 'grand_total' => 0.0, 'groups' => $groups];
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower((string) ($row['passenger_type'] ?? 'adult'));
            if ($type === 'children') {
                $type = 'child';
            } elseif ($type === 'adults') {
                $type = 'adult';
            } elseif ($type === 'infants') {
                $type = 'infant';
            }
            if (! isset($groups[$type])) {
                $type = 'adult';
            }
            $groups[$type]['count'] += max(1, (int) ($row['passenger_count'] ?? 1));
            $groups[$type]['base'] += (float) ($row['base_amount'] ?? 0);
            $groups[$type]['tax'] += (float) ($row['tax_amount'] ?? 0);
            $groups[$type]['total'] += (float) ($row['total_amount'] ?? 0);
        }

        $grandTotal = $groups['adult']['total'] + $groups['child']['total'] + $groups['infant']['total'];

        return [
            'has_rows' => $grandTotal > 0 || $groups['adult']['count'] + $groups['child']['count'] + $groups['infant']['count'] > 0,
            'grand_total' => $grandTotal,
            'groups' => $groups,
        ];
    }

    /**
     * Whether per-PTC passenger_pricing rows are safe to show in customer-facing PKR UI.
     *
     * @param  array<string, mixed>  $context  Offer snapshot or results API row (top-level or fare_breakdown passenger_pricing)
     */
    public static function passengerPricingCurrencyTrusted(array $context, string $displayCurrency, string $conversionStatus): bool
    {
        if ($conversionStatus === 'converted') {
            return false;
        }

        $rows = data_get($context, 'fare_breakdown.passenger_pricing');
        if (! is_array($rows) || $rows === []) {
            $rows = $context['passenger_pricing'] ?? null;
        }
        if (! is_array($rows) || $rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rowCurrency = strtoupper(trim((string) ($row['currency'] ?? $displayCurrency)));
            if ($rowCurrency !== '' && $rowCurrency !== $displayCurrency) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $resultsRow  mapOfferForResultsApi row or equivalent
     */
    public static function passengerPricingTrustedForResultsRow(array $resultsRow): bool
    {
        $passengerPricingAvailable = (bool) ($resultsRow['passenger_pricing_available'] ?? false);
        if (! $passengerPricingAvailable) {
            return false;
        }

        $displayCurrency = strtoupper(trim((string) ($resultsRow['pricing_currency'] ?? 'PKR')));
        if ($displayCurrency === '') {
            $displayCurrency = 'PKR';
        }
        $conversionStatus = (string) ($resultsRow['conversion_status'] ?? 'same_currency');

        return self::passengerPricingCurrencyTrusted($resultsRow, $displayCurrency, $conversionStatus);
    }

    /**
     * @param  array{grand_total: float}  $groupedPassenger
     */
    private static function passengerPricingReconciles(
        array $groupedPassenger,
        float $authoritativeTotal,
        float $markupTotal,
        float $serviceFee,
    ): bool {
        if ($groupedPassenger['grand_total'] <= 0) {
            return false;
        }

        return self::reconciliationDelta($groupedPassenger['grand_total'], $authoritativeTotal, $markupTotal, $serviceFee)
            <= self::RECONCILIATION_TOLERANCE;
    }

    private static function reconciliationDelta(
        float $passengerGrandTotal,
        float $authoritativeTotal,
        float $markupTotal,
        float $serviceFee,
    ): float {
        return abs(($passengerGrandTotal + $markupTotal + $serviceFee) - $authoritativeTotal);
    }

    /**
     * @param  list<array{label: string, amount: float, type?: string}>  $rows
     * @param  array{adults: int, children: int, infants: int, total: int}  $passengerMix
     * @param  array<string, mixed>  $feeSource
     * @return array{
     *     mode: string,
     *     currency: string,
     *     total: float,
     *     rows: list<array{label: string, amount: float, type?: string}>,
     *     show_passenger_mix: bool,
     *     passenger_mix: array{adults: int, children: int, infants: int, total: int},
     *     fee_source: array<string, mixed>,
     *     reconciliation_delta: float,
     * }
     */
    private static function result(
        string $mode,
        string $currency,
        float $total,
        array $rows,
        bool $showPassengerMix,
        array $passengerMix,
        array $feeSource,
        float $reconciliationDelta,
    ): array {
        return [
            'mode' => $mode,
            'currency' => $currency,
            'total' => $total,
            'rows' => $rows,
            'show_passenger_mix' => $showPassengerMix,
            'passenger_mix' => $passengerMix,
            'fee_source' => $feeSource,
            'reconciliation_delta' => $reconciliationDelta,
        ];
    }
}
