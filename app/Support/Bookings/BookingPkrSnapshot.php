<?php

namespace App\Support\Bookings;

/**
 * Booking-time commercial PKR from quote/pricing snapshot only.
 * Never copy a foreign-currency supplier total into PKR.
 */
final class BookingPkrSnapshot
{
    /**
     * @param  array<string, mixed>  $offer
     */
    public static function fromOffer(array $offer): ?float
    {
        $pricing = is_array($offer['pricing_components'] ?? null) ? $offer['pricing_components'] : [];
        $pricingCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
            $pricing['pricing_currency'] ?? $offer['pricing_currency'] ?? null
        );
        $conversionStatus = (string) ($pricing['conversion_status'] ?? $offer['conversion_status'] ?? '');
        $finalTotal = (float) ($pricing['final_total'] ?? $offer['final_customer_price'] ?? 0);

        if ($pricingCurrency === 'PKR' && $finalTotal > 0 && in_array($conversionStatus, ['converted', 'same_currency', ''], true)) {
            return $finalTotal;
        }

        foreach (['customer_total_pkr', 'converted_total_pkr', 'displayed_total_pkr'] as $key) {
            $candidate = data_get($offer, $key) ?? data_get($pricing, $key);
            if ($candidate === null || $candidate === '') {
                continue;
            }
            $amount = (float) $candidate;
            if ($amount > 0) {
                return $amount;
            }
        }

        $offerCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency($offer['currency'] ?? null);
        $offerTotal = (float) ($offer['total'] ?? 0);
        $supplierCurrency = BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
            $offer['supplier_currency'] ?? $pricing['supplier_currency'] ?? null
        );
        if ($offerCurrency === 'PKR' && $offerTotal > 0 && $supplierCurrency !== '' && $supplierCurrency !== 'PKR') {
            return $offerTotal;
        }
        if ($offerCurrency === 'PKR' && $offerTotal > 0 && ($supplierCurrency === '' || $supplierCurrency === 'PKR')) {
            return $offerTotal;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function conversionMeta(array $offer): array
    {
        $pricing = is_array($offer['pricing_components'] ?? null) ? $offer['pricing_components'] : [];

        return [
            'original_supplier_amount' => (float) ($pricing['supplier_total_source'] ?? $offer['supplier_total_source'] ?? $offer['supplier_total'] ?? 0),
            'original_supplier_currency' => BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
                $pricing['supplier_currency'] ?? $offer['supplier_currency'] ?? null
            ),
            'customer_total_pkr' => self::fromOffer($offer),
            'conversion_status' => (string) ($pricing['conversion_status'] ?? $offer['conversion_status'] ?? ''),
            'fx_rate' => isset($pricing['fx_rate']) ? (float) $pricing['fx_rate'] : null,
            'fx_fetched_at' => $pricing['fx_fetched_at'] ?? null,
            'pricing_currency' => BookingAuthoritativeCurrencyResolver::normalizeIsoCurrency(
                $pricing['pricing_currency'] ?? $offer['pricing_currency'] ?? null
            ),
            'markup_snapshot' => [
                'admin_markup' => (float) ($pricing['admin_markup'] ?? 0),
                'route_markup' => (float) ($pricing['route_markup'] ?? 0),
                'airline_markup' => (float) ($pricing['airline_markup'] ?? 0),
                'agent_markup_or_commission' => (float) ($pricing['agent_markup_or_commission'] ?? 0),
                'service_fee' => (float) ($pricing['service_fee'] ?? 0),
            ],
        ];
    }
}
