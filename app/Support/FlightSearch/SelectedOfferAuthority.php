<?php

namespace App\Support\FlightSearch;

use Carbon\Carbon;

/**
 * Booking authority reuse for a just-revalidated selected offer.
 * Independent of search-result snapshot retention (FlightSearchResultStore TTL)
 * and independent of display freshness (refresh_due / stale_after).
 */
final class SelectedOfferAuthority
{
    public const DEFAULT_REUSE_SECONDS = 5;

    public function reuseSeconds(): int
    {
        return max(1, (int) config('ota.selected_offer_authority.reuse_seconds', self::DEFAULT_REUSE_SECONDS));
    }

    /**
     * Exact authority signature. Any dimension change invalidates reuse.
     *
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $context
     */
    public function buildSignature(array $offer, array $context = []): array
    {
        $criteria = is_array($context['search_criteria'] ?? null) ? $context['search_criteria'] : [];
        $payload = is_array($context['search_payload'] ?? null) ? $context['search_payload'] : [];
        $payloadCriteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];

        $origin = $this->firstNonEmpty($context['origin'] ?? null, $offer['origin'] ?? null, $criteria['origin'] ?? null, $payloadCriteria['origin'] ?? null);
        $destination = $this->firstNonEmpty($context['destination'] ?? null, $offer['destination'] ?? null, $criteria['destination'] ?? null, $payloadCriteria['destination'] ?? null);
        $depart = $this->dateOnly($this->firstNonEmpty(
            $context['depart_date'] ?? null,
            $context['departure_date'] ?? null,
            $offer['depart_date'] ?? null,
            $offer['depart_at'] ?? null,
            $criteria['depart_date'] ?? null,
            $payloadCriteria['depart_date'] ?? null,
        ));
        $returnDate = $this->dateOnly($this->firstNonEmpty(
            $context['return_date'] ?? null,
            $offer['return_date'] ?? null,
            $offer['return_at'] ?? null,
            $criteria['return_date'] ?? null,
            $payloadCriteria['return_date'] ?? null,
        ));

        $signature = [
            'search_id' => $this->norm($context['search_id'] ?? $offer['search_id'] ?? $payload['search_id'] ?? ''),
            'offer_id' => $this->norm($offer['offer_id'] ?? $offer['id'] ?? $context['offer_id'] ?? ''),
            'supplier' => strtolower($this->norm($offer['supplier_provider'] ?? $context['supplier_provider'] ?? '')),
            'fare_key' => $this->norm(
                $context['fare_option_key'] ?? $offer['fare_option_key'] ?? $context['selected_fare_option_id'] ?? $offer['selected_fare_option_id'] ?? ''
            ),
            'brand_key' => $this->norm($this->brandKey($offer, $context)),
            'itinerary' => $this->itineraryKey($offer),
            'origin' => strtoupper($origin),
            'destination' => strtoupper($destination),
            'depart_date' => $depart,
            'return_date' => $returnDate,
            'adults' => $this->intDim($context['adults'] ?? $criteria['adults'] ?? $payloadCriteria['adults'] ?? $offer['adults'] ?? 1),
            'children' => $this->intDim($context['children'] ?? $criteria['children'] ?? $payloadCriteria['children'] ?? $offer['children'] ?? 0),
            'infants' => $this->intDim($context['infants'] ?? $criteria['infants'] ?? $payloadCriteria['infants'] ?? $offer['infants'] ?? 0),
            'cabin' => strtolower($this->norm($context['cabin'] ?? $criteria['cabin'] ?? $payloadCriteria['cabin'] ?? $offer['cabin'] ?? '')),
            'currency' => strtoupper($this->norm($offer['currency'] ?? $context['currency'] ?? $offer['fare_breakdown']['currency'] ?? '')),
            'combo_id' => $this->norm($context['combo_id'] ?? $offer['combo_id'] ?? ''),
            'outbound_key' => $this->norm($context['outbound_key'] ?? $offer['outbound_key'] ?? ''),
            'outbound_fare_option_key' => $this->norm($context['outbound_fare_option_key'] ?? $offer['outbound_fare_option_key'] ?? ''),
            'return_fare_option_key' => $this->norm($context['return_fare_option_key'] ?? $offer['return_fare_option_key'] ?? ''),
            'pricing_total' => $this->norm($offer['total'] ?? $offer['displayed_total'] ?? ''),
        ];

        $signature['fingerprint'] = implode('|', $signature);

        return $signature;
    }

    public function fingerprint(array $offer, array $context = []): string
    {
        return (string) ($this->buildSignature($offer, $context)['fingerprint'] ?? '');
    }

    public function signaturesMatch(?string $left, ?string $right): bool
    {
        $a = trim((string) $left);
        $b = trim((string) $right);

        return $a !== '' && $b !== '' && hash_equals($a, $b);
    }

    public function ageSeconds(?string $iso, ?Carbon $now = null): ?int
    {
        $parsed = app(SabreOfferFreshness::class)->parseTimestamp($iso);
        if ($parsed === null) {
            return null;
        }

        return app(SabreOfferFreshness::class)->offerAgeSeconds($parsed, $now ?? now());
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $freshnessMeta
     */
    public function mayReuse(
        array $offer,
        array $context = [],
        ?string $revalidatedAt = null,
        array $freshnessMeta = [],
        bool $requiresAcceptance = false,
        ?string $storedFingerprint = null,
        ?Carbon $now = null,
    ): bool {
        if ($requiresAcceptance) {
            return false;
        }

        $status = strtolower(trim((string) (
            $freshnessMeta['revalidation_status']
            ?? $freshnessMeta['selected_offer_revalidation_status']
            ?? $offer['revalidation_status']
            ?? $offer['selected_offer_revalidation_status']
            ?? $context['revalidation_status']
            ?? ''
        )));
        if (! in_array($status, ['success', 'revalidated', 'valid'], true)) {
            return false;
        }

        $highRisk = $freshnessMeta['high_risk_cached_offer'] ?? $offer['high_risk_cached_offer'] ?? false;
        if ($highRisk === true) {
            return false;
        }
        $reasons = is_array($freshnessMeta['high_risk_reasons'] ?? null) ? $freshnessMeta['high_risk_reasons'] : [];
        if ($reasons !== []) {
            return false;
        }

        $stamp = $revalidatedAt
            ?? (is_string($freshnessMeta['last_revalidated_at'] ?? null) ? (string) $freshnessMeta['last_revalidated_at'] : null)
            ?? (is_string($freshnessMeta['selected_offer_last_revalidated_at'] ?? null) ? (string) $freshnessMeta['selected_offer_last_revalidated_at'] : null)
            ?? (is_string($offer['last_revalidated_at'] ?? null) ? (string) $offer['last_revalidated_at'] : null)
            ?? (is_string($context['authoritative_revalidation_at'] ?? null) ? (string) $context['authoritative_revalidation_at'] : null);

        $age = $this->ageSeconds($stamp, $now);
        if ($age === null || $age >= $this->reuseSeconds()) {
            return false;
        }

        $current = $this->fingerprint($offer, $context);
        $stored = trim((string) (
            $storedFingerprint
            ?? $offer['authoritative_signature']
            ?? $context['authoritative_signature']
            ?? $freshnessMeta['authoritative_signature']
            ?? ''
        ));
        if ($stored === '') {
            $stored = $current;
        }

        return $this->signaturesMatch($stored, $current);
    }

    /**
     * @param  array<string, mixed>  $draft
     * @param  array<string, mixed>  $offer
     */
    public function mayReuseFromDraft(array $draft, array $offer = []): bool
    {
        $freshness = is_array($draft['offer_freshness'] ?? null) ? $draft['offer_freshness'] : [];

        return $this->mayReuse(
            $offer !== [] ? $offer : [
                'offer_id' => $draft['offer_id'] ?? $draft['flight_id'] ?? '',
                'search_id' => $draft['search_id'] ?? '',
                'authoritative_signature' => $draft['authoritative_signature'] ?? null,
                'last_revalidated_at' => $draft['authoritative_revalidation_at'] ?? null,
                'revalidation_status' => $freshness['revalidation_status'] ?? 'success',
            ],
            $draft,
            is_string($draft['authoritative_revalidation_at'] ?? null) ? (string) $draft['authoritative_revalidation_at'] : null,
            $freshness,
            false,
            is_string($draft['authoritative_signature'] ?? null) ? (string) $draft['authoritative_signature'] : null,
        );
    }

    private function brandKey(array $offer, array $context): string
    {
        $branded = is_array($context['selected_branded_fare_checkout_context'] ?? null)
            ? $context['selected_branded_fare_checkout_context']
            : (is_array($offer['selected_branded_fare_checkout_context'] ?? null) ? $offer['selected_branded_fare_checkout_context'] : []);
        $family = is_array($context['selected_fare_family_option'] ?? null)
            ? $context['selected_fare_family_option']
            : (is_array($offer['selected_fare_family_option'] ?? null) ? $offer['selected_fare_family_option'] : []);

        return $this->firstNonEmpty(
            $branded['brand_code'] ?? null,
            $branded['fare_family'] ?? null,
            $family['code'] ?? null,
            $family['name'] ?? null,
            $offer['fare_family'] ?? null,
            $offer['brand_code'] ?? null,
        );
    }

    private function itineraryKey(array $offer): string
    {
        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        $parts = [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $parts[] = implode('-', [
                strtoupper($this->norm($segment['marketing_carrier'] ?? $segment['airline_code'] ?? '')),
                $this->norm($segment['flight_number'] ?? ''),
                $this->norm($segment['origin'] ?? $segment['from'] ?? ''),
                $this->norm($segment['destination'] ?? $segment['to'] ?? ''),
                $this->norm($segment['depart_at'] ?? $segment['departure_at'] ?? ''),
            ]);
        }

        return implode('>', $parts);
    }

    private function firstNonEmpty(mixed ...$values): string
    {
        foreach ($values as $value) {
            $norm = $this->norm($value);
            if ($norm !== '') {
                return $norm;
            }
        }

        return '';
    }

    private function norm(mixed $value): string
    {
        if (is_bool($value) || is_array($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function intDim(mixed $value): string
    {
        return (string) max(0, (int) $value);
    }

    private function dateOnly(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m) === 1) {
            return $m[1];
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }
}
