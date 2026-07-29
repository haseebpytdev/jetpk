<?php

namespace App\Support\FlightSearch;

/**
 * Customer-safe public offer revalidation / fare-change normalization for Next.js.
 */
class PublicOfferRevalidationPresenter
{
    public static function customerDisplayTotal(array $offer): ?int
    {
        $final = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
        $currency = strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'));
        $conversion = (string) ($offer['conversion_status'] ?? 'same_currency');

        if ($final <= 0 || $currency !== 'PKR' || ! in_array($conversion, ['same_currency', 'converted'], true)) {
            return null;
        }

        return (int) round($final);
    }

    public static function customerDisplayCurrency(array $offer): string
    {
        $currency = strtoupper((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'));

        return $currency !== '' ? $currency : 'PKR';
    }

    public static function priceChanged(?int $oldTotal, ?int $newTotal): bool
    {
        if ($oldTotal === null || $newTotal === null || $oldTotal <= 0 || $newTotal <= 0) {
            return false;
        }

        return abs($oldTotal - $newTotal) > 1;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildFareChangeRevalidation(
        array $offer,
        ?int $oldTotal,
        ?int $newTotal,
        string $provider,
        ?string $currency = null,
    ): array {
        $currency = $currency ?? self::customerDisplayCurrency($offer);
        $message = (string) __('The airline fare has changed. Please review the updated price before continuing.');

        return [
            'revalidation_status' => 'changed',
            'provider' => strtolower(trim($provider)),
            'price_changed' => true,
            'original_total' => $oldTotal,
            'confirmed_total' => $newTotal,
            'old_total' => $oldTotal,
            'new_total' => $newTotal,
            'currency' => $currency,
            'safe_customer_message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $revalidation
     * @return array<string, mixed>
     */
    public static function withFareChangeAliases(array $revalidation): array
    {
        if (isset($revalidation['original_total']) && ! isset($revalidation['old_total'])) {
            $revalidation['old_total'] = $revalidation['original_total'];
        }
        if (isset($revalidation['confirmed_total']) && ! isset($revalidation['new_total'])) {
            $revalidation['new_total'] = $revalidation['confirmed_total'];
        }
        if (isset($revalidation['old_total']) && ! isset($revalidation['original_total'])) {
            $revalidation['original_total'] = $revalidation['old_total'];
        }
        if (isset($revalidation['new_total']) && ! isset($revalidation['confirmed_total'])) {
            $revalidation['confirmed_total'] = $revalidation['new_total'];
        }

        return $revalidation;
    }

    public static function requiresFareChangeAcceptance(
        array $revalidation,
        ?string $apiStatus = null,
        bool $acceptFareChange = false,
    ): bool {
        if ($acceptFareChange) {
            return false;
        }

        if (($apiStatus ?? '') === 'fare_changed') {
            return true;
        }

        if (($revalidation['price_changed'] ?? false) === true) {
            return true;
        }

        return ($revalidation['revalidation_status'] ?? '') === 'changed';
    }

    /**
     * @param  array<string, mixed>  $revalidation
     */
    public static function resolveApiStatus(array $revalidation, bool $requiresAcceptance): string
    {
        return $requiresAcceptance ? 'fare_changed' : 'success';
    }
}
