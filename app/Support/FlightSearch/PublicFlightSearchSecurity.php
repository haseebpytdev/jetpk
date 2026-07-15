<?php

namespace App\Support\FlightSearch;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public flight search hardening: debug fare gate, search_id validation,
 * internal URL checks, and customer-safe JSON sanitization.
 */
class PublicFlightSearchSecurity
{
    /** @var list<string> */
    private const SENSITIVE_OFFER_KEYS = [
        'supplier_connection_id',
        'supplier_offer_id',
        'fare_verification_digest',
        'raw_payload',
        'raw_reference',
        'sabre_shop_context',
        'sabre_booking_context',
        'sabre_fare_excerpt',
        'sabre_shop_identifiers',
        'sabre_bfm_gir_archive',
        'pricing_components',
        'expected_ui_price',
        'fare_debug',
    ];

    public static function allowsDebugFares(Request $request): bool
    {
        if (! $request->boolean('debug_fares')) {
            return false;
        }

        if (app()->environment('production')) {
            return false;
        }

        $user = $request->user();

        return $user instanceof User
            && ($user->isPlatformAdmin() || $user->isAgencyAdmin() || $user->isStaff());
    }

    public static function isValidSearchId(string $searchId): bool
    {
        return $searchId !== '' && Str::isUuid($searchId);
    }

    public static function missingSearchIdResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Missing search_id.',
            'offers' => [],
            'total' => 0,
            'has_more' => false,
        ], 422);
    }

    public static function invalidSearchIdResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Invalid search_id.',
            'offers' => [],
            'total' => 0,
            'has_more' => false,
        ], 422);
    }

    public static function expiredSearchIdResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This fare search has expired. Please search again.',
            'offers' => [],
            'total' => 0,
            'has_more' => false,
        ], 410);
    }

    public static function isAllowedInternalUrl(?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        $url = trim($url);
        if ($url === '') {
            return false;
        }

        if (preg_match('#^\s*(javascript|data|vbscript):#i', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '//')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_contains($url, '..');
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $urlHost = parse_url($url, PHP_URL_HOST);

            return is_string($appHost)
                && $appHost !== ''
                && is_string($urlHost)
                && strcasecmp($appHost, $urlHost) === 0;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    public static function sanitizeResultsOffer(array $offer, bool $debugAllowed = false): array
    {
        foreach (self::SENSITIVE_OFFER_KEYS as $key) {
            if ($key === 'fare_debug' && $debugAllowed) {
                continue;
            }
            unset($offer[$key]);
        }

        foreach (array_keys($offer) as $key) {
            if (is_string($key) && (str_starts_with($key, 'raw_') || str_starts_with($key, '_'))) {
                unset($offer[$key]);
            }
        }

        if (array_key_exists('select_url', $offer) && ! self::isAllowedInternalUrl(
            is_string($offer['select_url'] ?? null) ? (string) $offer['select_url'] : null
        )) {
            $offer['select_url'] = null;
            $offer['can_book'] = false;
        }

        if (array_key_exists('details_url', $offer) && ! self::isAllowedInternalUrl(
            is_string($offer['details_url'] ?? null) ? (string) $offer['details_url'] : null
        )) {
            unset($offer['details_url']);
        }

        return $offer;
    }

    public static function sanitizeDisplayText(string $value): string
    {
        $stripped = strip_tags($value);

        return str_replace(["\0", "\r"], '', $stripped);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function sanitizeAirportSuggestionRow(array $row): array
    {
        foreach (['label', 'description', 'name', 'city', 'country'] as $field) {
            if (! array_key_exists($field, $row)) {
                continue;
            }
            $row[$field] = self::sanitizeDisplayText((string) $row[$field]);
        }

        return $row;
    }
}
