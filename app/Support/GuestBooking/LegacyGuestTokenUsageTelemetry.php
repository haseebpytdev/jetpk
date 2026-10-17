<?php

namespace App\Support\GuestBooking;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Privacy-safe usage telemetry for OPS-DEF-0011 legacy guest-token routes.
 *
 * Records only allowlisted static fields. Never accepts or logs tokens, booking
 * identifiers, URIs, query strings, PII, or request headers.
 */
final class LegacyGuestTokenUsageTelemetry
{
    public const EVENT = 'guest.legacy_token.usage';

    /** @var list<string> */
    public const ALLOWED_CONTEXT_KEYS = ['event', 'route_category', 'method', 'outcome'];

    /** @var list<string> */
    public const ALLOWED_OUTCOMES = ['success', 'denied', 'validation_failure', 'other_failure'];

    /** @var array<string, string> */
    public const ROUTE_CATEGORIES = [
        'guest.bookings.exchange' => 'guest_detail_exchange',
        'guest.bookings.payment-proof.legacy' => 'payment_proof',
        'guest.bookings.promo.apply.legacy' => 'promo_apply',
        'guest.bookings.promo.remove.legacy' => 'promo_remove',
        'guest.bookings.abhipay.start.legacy' => 'abhipay_start',
        'guest.bookings.cancellations.store.legacy' => 'cancellation',
    ];

    /**
     * Max structured log writes per route_category per UTC minute (scanner flood gate).
     */
    public const MAX_WRITES_PER_CATEGORY_PER_MINUTE = 30;

    /**
     * Optional in-memory sink for automated privacy tests (never used in production).
     *
     * @var list<array{event: string, route_category: string, method: string, outcome: string}>|null
     */
    private static ?array $testSink = null;

    public static function enableTestSink(): void
    {
        self::$testSink = [];
    }

    /**
     * @return list<array{event: string, route_category: string, method: string, outcome: string}>
     */
    public static function drainTestSink(): array
    {
        $records = self::$testSink ?? [];
        self::$testSink = null;

        return $records;
    }

    public static function categoryForRouteName(?string $routeName): ?string
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        return self::ROUTE_CATEGORIES[$routeName] ?? null;
    }

    public static function outcomeFromStatus(int $status): string
    {
        if ($status >= 200 && $status < 400) {
            return 'success';
        }

        if ($status === 401 || $status === 403) {
            return 'denied';
        }

        if ($status === 419 || $status === 422) {
            return 'validation_failure';
        }

        return 'other_failure';
    }

    /**
     * @param  'GET'|'POST'|'PUT'|'PATCH'|'DELETE'|'HEAD'|'OPTIONS'|string  $method
     */
    public static function record(string $routeCategory, string $outcome, string $method): void
    {
        if (! in_array($routeCategory, self::ROUTE_CATEGORIES, true)) {
            return;
        }

        if (! in_array($outcome, self::ALLOWED_OUTCOMES, true)) {
            $outcome = 'other_failure';
        }

        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)) {
            $method = 'OTHER';
        }

        if (! self::acquireFloodSlot($routeCategory)) {
            return;
        }

        $payload = [
            'event' => self::EVENT,
            'route_category' => $routeCategory,
            'method' => $method,
            'outcome' => $outcome,
        ];

        if (self::$testSink !== null) {
            self::$testSink[] = $payload;
        }

        Log::info(self::EVENT, $payload);
    }

    public static function acquireFloodSlot(string $routeCategory): bool
    {
        $bucket = gmdate('YmdHi');
        $key = 'guest_legacy_token_telemetry:'.$routeCategory.':'.$bucket;

        // Ensure TTL exists before increment (array/file cache drivers vary).
        Cache::add($key, 0, 120);
        $count = (int) Cache::increment($key);

        return $count <= self::MAX_WRITES_PER_CATEGORY_PER_MINUTE;
    }
}
