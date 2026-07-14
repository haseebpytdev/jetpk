<?php

namespace App\Support\Client;

use App\Enums\JetpkHomepageFareRefreshStatus;
use Illuminate\Support\Carbon;

/**
 * Resolves public homepage fare labels without exposing PKR 0 or invalid amounts.
 */
final class JetpkHomepageFareDisplay
{
    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $fareCache
     * @return array{amount: float, currency: string, label: string, source: string, status: string}|null
     */
    public static function resolve(array $item, ?array $fareCache = null): ?array
    {
        $currency = strtoupper(trim((string) ($item['currency'] ?? config('jetpk_homepage.default_currency', 'PKR'))));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $dynamicEnabled = self::isTruthy($item['dynamic_fare_enabled'] ?? '0');
        $manual = self::positiveAmount($item['manual_fallback_price'] ?? null);

        if ($dynamicEnabled && is_array($fareCache)) {
            $resolved = self::positiveAmount($fareCache['resolved_fare'] ?? null);
            $refreshedAt = self::parseTimestamp($fareCache['fare_refreshed_at'] ?? null);
            $status = (string) ($fareCache['fare_status'] ?? '');
            $fresh = $refreshedAt !== null && self::isFresh($refreshedAt);

            if ($resolved !== null && ($fresh || (bool) config('jetpk_homepage.allow_stale_fare_display', true))) {
                $cacheCurrency = strtoupper(trim((string) ($fareCache['resolved_currency'] ?? $currency)));

                return self::buildResult($resolved, $cacheCurrency !== '' ? $cacheCurrency : $currency, 'dynamic', $fresh ? JetpkHomepageFareRefreshStatus::Success->value : JetpkHomepageFareRefreshStatus::Stale->value);
            }
        }

        if ($manual !== null) {
            return self::buildResult($manual, $currency, 'manual', JetpkHomepageFareRefreshStatus::Manual->value);
        }

        $legacyPrice = self::positiveAmount($item['price'] ?? null);
        if ($legacyPrice !== null) {
            return self::buildResult($legacyPrice, $currency, 'manual', JetpkHomepageFareRefreshStatus::Manual->value);
        }

        return null;
    }

    public static function formatLabel(float $amount, string $currency): string
    {
        $code = strtoupper(trim($currency));
        $formatted = number_format((int) round($amount));

        return $code === 'PKR' ? 'PKR '.$formatted : $code.' '.$formatted;
    }

    public static function neutralAvailabilityLabel(): string
    {
        return 'Fares available';
    }

    public static function isFresh(Carbon $refreshedAt): bool
    {
        $hours = max(1, (int) config('jetpk_homepage.fare_freshness_hours', 30));

        return $refreshedAt->greaterThanOrEqualTo(now()->subHours($hours));
    }

    /**
     * @return array{amount: float, currency: string, label: string, source: string, status: string}
     */
    private static function buildResult(float $amount, string $currency, string $source, string $status): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'label' => self::formatLabel($amount, $currency),
            'source' => $source,
            'status' => $status,
        ];
    }

    private static function positiveAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = preg_replace('/[^\d.]/', '', $value) ?? '';
        }

        $amount = (float) $value;

        return $amount > 0 ? $amount : null;
    }

    private static function parseTimestamp(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isTruthy(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }
}
