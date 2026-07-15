<?php

namespace App\Support\Pricing;

use Illuminate\Support\Facades\Log;

/**
 * IATI fare currency resolution and double USD→PKR conversion prevention.
 */
class IatiFarePricingResolver
{
    public const DOUBLE_CONVERSION_WARNING = 'iati_currency_double_conversion_prevented';

    /**
     * @param  array<string, mixed>  $fare
     * @return array{base_fare: float, taxes: float, supplier_total: float, currency: string, passenger_pricing: list<array<string, mixed>>}
     */
    public static function supplierFareFromBreakdown(array $fare): array
    {
        $baseFare = (float) ($fare['base_fare'] ?? 0);
        $taxes = (float) ($fare['taxes'] ?? 0);
        $supplierTotal = (float) ($fare['supplier_total'] ?? 0);
        if ($supplierTotal <= 0.0) {
            $supplierTotal = $baseFare + $taxes;
        }

        return [
            'base_fare' => $baseFare,
            'taxes' => $taxes,
            'supplier_total' => $supplierTotal > 0 ? $supplierTotal : ($baseFare + $taxes),
            'currency' => self::resolveCurrency($fare),
            'passenger_pricing' => is_array($fare['passenger_pricing'] ?? null) ? array_values($fare['passenger_pricing']) : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $fare
     */
    public static function resolveCurrency(array $fare, ?array $offer = null): string
    {
        $resolved = self::resolveCurrencyWithSource($fare, $offer);

        return $resolved['currency'];
    }

    /**
     * @param  array<string, mixed>  $fare
     * @return array{currency: string, source: string}
     */
    public static function resolveCurrencyWithSource(array $fare, ?array $offer = null): array
    {
        $passengerPricing = is_array($fare['passenger_pricing'] ?? null) ? $fare['passenger_pricing'] : [];
        $passengerCurrency = self::passengerPricingCurrency($passengerPricing);
        $fareCurrency = strtoupper(trim((string) ($fare['currency'] ?? '')));
        $displayCurrency = strtoupper(trim((string) (
            $offer['displayed_currency']
            ?? $offer['pricing_currency']
            ?? $offer['currency']
            ?? ''
        )));

        if ($passengerCurrency !== null) {
            if ($fareCurrency === '' || $fareCurrency === 'USD' || $passengerCurrency === $fareCurrency) {
                return ['currency' => $passengerCurrency, 'source' => 'passenger_pricing'];
            }

            if ($passengerCurrency === 'PKR' && $fareCurrency === 'USD') {
                return ['currency' => 'PKR', 'source' => 'passenger_pricing'];
            }
        }

        if ($displayCurrency === 'PKR') {
            return ['currency' => 'PKR', 'source' => 'displayed_currency'];
        }

        if ($fareCurrency !== '') {
            return ['currency' => $fareCurrency, 'source' => 'fare_breakdown'];
        }

        if ($displayCurrency !== '') {
            return ['currency' => $displayCurrency, 'source' => 'displayed_currency'];
        }

        return ['currency' => 'PKR', 'source' => 'default_pkr'];
    }

    /**
     * @param  list<array<string, mixed>>  $passengerPricing
     */
    public static function passengerPricingCurrency(array $passengerPricing): ?string
    {
        $currencies = [];
        foreach ($passengerPricing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));
            if ($currency !== '') {
                $currencies[] = $currency;
            }
        }

        if ($currencies === []) {
            return null;
        }

        $unique = array_values(array_unique($currencies));
        if (count($unique) === 1) {
            return $unique[0];
        }

        $counts = array_count_values($currencies);
        arsort($counts);

        return (string) array_key_first($counts);
    }

    public static function looksPkrMarketAmount(float $amount): bool
    {
        return $amount >= 10000.0 && $amount <= 5000000.0;
    }

    public static function inflationTolerance(float $expectedTotalPkr, float $fxRate): float
    {
        $inflated = $expectedTotalPkr * $fxRate;

        return max(5.0, $inflated * 0.005);
    }

    /**
     * @param  array<string, mixed>  $pricingSnapshot
     * @param  array<string, mixed>  $meta
     */
    public static function persistedSourceLooksPkr(float $expectedTotal, array $pricingSnapshot, array $meta = []): bool
    {
        $passengerPricing = is_array($meta['passenger_pricing'] ?? null) ? $meta['passenger_pricing'] : [];
        if (self::passengerPricingCurrency($passengerPricing) === 'PKR') {
            return true;
        }

        $family = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $familyCurrency = strtoupper(trim((string) ($family['displayed_currency'] ?? $family['currency'] ?? '')));
        if ($familyCurrency === 'PKR') {
            return true;
        }

        $supplierCurrency = strtoupper(trim((string) ($pricingSnapshot['supplier_currency'] ?? '')));
        $pricingCurrency = strtoupper(trim((string) ($pricingSnapshot['pricing_currency'] ?? '')));
        $conversionStatus = strtolower(trim((string) ($pricingSnapshot['conversion_status'] ?? '')));
        if ($supplierCurrency === 'USD' && $pricingCurrency === 'PKR' && $conversionStatus === 'converted') {
            return self::looksPkrMarketAmount($expectedTotal);
        }

        return self::looksPkrMarketAmount($expectedTotal);
    }

    /**
     * Detect inflated persisted totals: stored ≈ expected × fx_rate.
     *
     * @param  array<string, mixed>  $pricingSnapshot
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    public static function detectPersistedDoubleConversion(
        float $expectedTotalPkr,
        float $storedTotalPkr,
        array $pricingSnapshot,
        array $meta = [],
    ): ?array {
        if ($expectedTotalPkr <= 0.0 || $storedTotalPkr <= 0.0 || $storedTotalPkr <= $expectedTotalPkr) {
            return null;
        }

        $fxRate = (float) ($pricingSnapshot['fx_rate'] ?? 0);
        if ($fxRate <= 10.0) {
            return null;
        }

        if (! self::persistedSourceLooksPkr($expectedTotalPkr, $pricingSnapshot, $meta)) {
            return null;
        }

        $inflatedExpected = round($expectedTotalPkr * $fxRate, 2);
        $tolerance = self::inflationTolerance($expectedTotalPkr, $fxRate);
        if (abs($storedTotalPkr - $inflatedExpected) > $tolerance) {
            return null;
        }

        $passengerTotals = self::passengerPricingTotals(
            is_array($meta['passenger_pricing'] ?? null) ? $meta['passenger_pricing'] : [],
        );
        $inflatedBase = (float) ($pricingSnapshot['base_fare'] ?? 0);
        $inflatedTax = (float) ($pricingSnapshot['taxes'] ?? 0);
        $expectedBase = (float) ($passengerTotals['base'] ?? 0);
        $expectedTax = (float) ($passengerTotals['tax'] ?? 0);
        if ($expectedBase + $expectedTax <= 0.0 && $inflatedBase + $inflatedTax > 0.0) {
            $expectedBase = round($inflatedBase / $fxRate, 2);
            $expectedTax = round($inflatedTax / $fxRate, 2);
        }
        if ($expectedBase + $expectedTax <= 0.0) {
            $expectedBase = $expectedTotalPkr;
            $expectedTax = 0.0;
        }

        $currencySource = 'fare_breakdown';
        if ($passengerTotals !== null && ($passengerTotals['total'] ?? 0) > 0) {
            $currencySource = 'passenger_pricing';
        } elseif (strtoupper(trim((string) ($meta['selected_fare_family_option']['displayed_currency'] ?? ''))) === 'PKR') {
            $currencySource = 'selected_fare_family_option';
        } elseif ((float) ($pricingSnapshot['supplier_total_source'] ?? 0) > 0) {
            $currencySource = 'pricing_snapshot';
        }

        return [
            'detected' => true,
            'source_total' => $expectedTotalPkr,
            'fx_rate' => $fxRate,
            'inflated_total' => $storedTotalPkr,
            'expected_total_pkr' => $expectedTotalPkr,
            'expected_base_pkr' => $expectedBase,
            'expected_tax_pkr' => $expectedTax,
            'currency_source_used' => $currencySource,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $passengerPricing
     */
    public static function passengerPricingTotals(array $passengerPricing): ?array
    {
        if ($passengerPricing === []) {
            return null;
        }

        $total = 0.0;
        $base = 0.0;
        $tax = 0.0;
        $currency = self::passengerPricingCurrency($passengerPricing);

        foreach ($passengerPricing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $qty = max(1, (int) ($row['quantity'] ?? $row['number_of_pax'] ?? $row['count'] ?? 1));
            $total += (float) ($row['total'] ?? 0) * $qty;
            $base += (float) ($row['base'] ?? $row['base_fare'] ?? 0) * $qty;
            $tax += (float) ($row['tax'] ?? $row['taxes'] ?? 0) * $qty;
        }

        if ($total <= 0.0) {
            return null;
        }

        return [
            'total' => round($total, 2),
            'base' => round($base, 2),
            'tax' => round($tax, 2),
            'currency' => $currency ?? 'PKR',
        ];
    }

    /**
     * @param  array<string, mixed>  $supplierFare
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>|null
     */
    public static function detectDoubleConversion(array $supplierFare, array $components): ?array
    {
        $passengerPricing = is_array($supplierFare['passenger_pricing'] ?? null)
            ? $supplierFare['passenger_pricing']
            : [];
        $passengerTotals = self::passengerPricingTotals($passengerPricing);
        $sourceTotal = (float) ($components['supplier_total_source'] ?? $supplierFare['supplier_total'] ?? 0);
        $finalTotal = (float) ($components['final_total'] ?? $components['supplier_total'] ?? 0);
        $fxRate = (float) ($components['fx_rate'] ?? 0);
        $conversionStatus = (string) ($components['conversion_status'] ?? '');
        $supplierCurrency = strtoupper(trim((string) ($components['supplier_currency'] ?? $supplierFare['currency'] ?? '')));

        if ($sourceTotal <= 0.0 || $fxRate <= 1.0 || $conversionStatus !== 'converted') {
            return null;
        }

        $expectedInflated = round($sourceTotal * $fxRate, 2);
        if (abs($finalTotal - $expectedInflated) > 1.0) {
            return null;
        }

        $passengerCurrency = $passengerTotals['currency'] ?? self::passengerPricingCurrency($passengerPricing);
        $passengerTotal = (float) ($passengerTotals['total'] ?? 0);

        $sourceLooksPkr = $passengerCurrency === 'PKR'
            || self::persistedSourceLooksPkr($sourceTotal, $components, [
                'passenger_pricing' => $passengerPricing,
            ]);

        if (! $sourceLooksPkr) {
            return null;
        }

        $sourceAlreadyPkr = $passengerTotal > 0
            ? abs($passengerTotal - $sourceTotal) <= 1.0
            : self::looksPkrMarketAmount($sourceTotal);

        if (! $sourceAlreadyPkr) {
            return null;
        }

        return [
            'detected' => true,
            'source_total' => $sourceTotal,
            'fx_rate' => $fxRate,
            'inflated_total' => $finalTotal,
            'expected_total_pkr' => $passengerTotal > 0 ? $passengerTotal : $sourceTotal,
            'expected_base_pkr' => (float) ($passengerTotals['base'] ?? $supplierFare['base_fare'] ?? 0),
            'expected_tax_pkr' => (float) ($passengerTotals['tax'] ?? $supplierFare['taxes'] ?? 0),
            'currency_source_used' => $passengerTotal > 0 ? 'passenger_pricing' : 'fare_breakdown',
        ];
    }

    /**
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    public static function correctDoubleConversion(array $components, array $correction): array
    {
        $correctTotal = round((float) ($correction['expected_total_pkr'] ?? 0), 2);
        $correctBase = round((float) ($correction['expected_base_pkr'] ?? $components['base_fare'] ?? 0), 2);
        $correctTax = round((float) ($correction['expected_tax_pkr'] ?? $components['taxes'] ?? 0), 2);
        if ($correctTotal <= 0.0) {
            return $components;
        }
        if ($correctBase + $correctTax <= 0.0) {
            $correctBase = $correctTotal;
            $correctTax = 0.0;
        }

        $markupDelta = (float) ($components['admin_markup'] ?? 0)
            + (float) ($components['route_markup'] ?? 0)
            + (float) ($components['airline_markup'] ?? 0)
            + (float) ($components['agent_markup_or_commission'] ?? 0)
            + (float) ($components['service_fee'] ?? 0);
        $oldSupplierTotal = (float) ($components['supplier_total'] ?? 0);
        if ($oldSupplierTotal > 0 && $markupDelta > 0) {
            $markupDelta = round($markupDelta * ($correctTotal / $oldSupplierTotal), 2);
        }

        Log::notice(self::DOUBLE_CONVERSION_WARNING, [
            'source_total' => (float) ($correction['source_total'] ?? 0),
            'fx_rate' => (float) ($correction['fx_rate'] ?? 0),
            'inflated_total' => (float) ($correction['inflated_total'] ?? 0),
            'corrected_total' => $correctTotal,
        ]);

        return array_merge($components, [
            'base_fare' => $correctBase,
            'taxes' => $correctTax,
            'supplier_total' => $correctTotal,
            'supplier_total_source' => $correctTotal,
            'supplier_currency' => 'PKR',
            'pricing_currency' => 'PKR',
            'conversion_status' => 'same_currency',
            'fx_rate' => 1.0,
            'final_total' => round($correctTotal + $markupDelta, 2),
            'iati_double_conversion_corrected' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $supplierFare
     * @param  array<string, mixed>  $components
     * @return array<string, mixed>
     */
    public static function guardPricingComponents(array $supplierFare, array $components): array
    {
        $correction = self::detectDoubleConversion($supplierFare, $components);
        if ($correction === null) {
            return $components;
        }

        return self::correctDoubleConversion($components, $correction);
    }
}
