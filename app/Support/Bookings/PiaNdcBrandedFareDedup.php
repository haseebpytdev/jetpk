<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Support\FlightSearch\ItineraryFareConsolidator;
use Illuminate\Support\Facades\Log;

/**
 * Safe PIA NDC branded fare deduplication at normalization/service layer.
 * Removes exact duplicate fare cards; keeps same brand when fare product differs.
 */
final class PiaNdcBrandedFareDedup
{
    /**
     * @param  list<array<string, mixed>>  $options
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $context
     * @return array{
     *     options: list<array<string, mixed>>,
     *     stats: array<string, mixed>
     * }
     */
    public static function dedupeOptions(array $options, array $offer, array $context = []): array
    {
        $before = count($options);
        if ($before < 2) {
            return [
                'options' => self::enrichVariantPresentation($options),
                'stats' => self::emptyStats($before),
            ];
        }

        $seen = [];
        $deduped = [];
        $dropped = [];
        $duplicateGroups = [];

        foreach ($options as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = self::identityKey($row, $offer, $context);
            if (isset($seen[$key])) {
                $dropped[] = self::identitySnapshot($row, $offer, $key);
                $duplicateGroups[$key][] = self::identitySnapshot($row, $offer, $key);

                continue;
            }
            $seen[$key] = true;
            $deduped[] = $row;
        }

        $deduped = self::enrichVariantPresentation($deduped);
        $sameBrandGroups = self::sameBrandDifferentProductGroups($deduped);

        $stats = [
            'before_count' => $before,
            'after_count' => count($deduped),
            'dropped_duplicate_count' => count($dropped),
            'duplicate_groups' => array_values(array_map(
                static fn (array $group): array => ['count' => count($group), 'samples' => $group],
                array_filter($duplicateGroups, static fn (array $g): bool => count($g) >= 1),
            )),
            'same_brand_different_product_groups' => $sameBrandGroups,
            'search_type' => (string) ($context['search_type'] ?? self::inferSearchType($offer)),
            'supplier' => SupplierProvider::PiaNdc->value,
        ];

        if ($stats['dropped_duplicate_count'] > 0) {
            self::logDedup($offer, $stats, $dropped);
        }

        return [
            'options' => $deduped,
            'stats' => $stats,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $context
     */
    public static function identityKey(array $row, array $offer, array $context = []): string
    {
        $ctx = is_array($row['provider_context'] ?? null) ? $row['provider_context'] : [];
        if ($ctx === []) {
            $ctx = PiaNdcFareFamilyPolicy::extractProviderContextFromOption($row, $offer);
        }

        $offerRef = trim((string) ($ctx['offer_ref_id'] ?? ''));
        $offerItemRef = trim((string) ($ctx['offer_item_ref_id'] ?? ''));
        $brandName = self::normalizeToken((string) ($row['brand_name'] ?? $row['name'] ?? ''));
        $fareBasis = self::normalizeToken((string) ($ctx['fare_basis'] ?? $row['fare_basis'] ?? ''));
        $cabin = self::normalizeToken((string) ($row['cabin'] ?? $ctx['cabin_type'] ?? $offer['cabin'] ?? ''));
        $rbd = self::normalizeToken((string) ($ctx['rbd'] ?? $row['booking_class'] ?? ''));
        $baggage = self::normalizeToken(self::baggageToken($row, $offer));
        $refundability = self::normalizeToken((string) ($row['refundable_display'] ?? ($offer['refundable'] ?? false ? 'refundable' : 'non-refundable')));
        $meal = self::normalizeToken((string) ($row['meal_included'] ?? ''));
        $price = self::normalizePrice($row, $offer);
        $currency = strtoupper(trim((string) ($row['currency'] ?? data_get($offer, 'fare_breakdown.currency', 'PKR'))));
        $direction = self::normalizeToken((string) ($context['direction'] ?? self::inferDirection($offer)));
        $segmentGroup = self::segmentGroupKey($row, $offer);
        $supplierCode = SupplierProvider::PiaNdc->value;
        $offerId = trim((string) ($row['source_offer_id'] ?? $offer['offer_id'] ?? $offer['id'] ?? ''));

        if ($offerItemRef !== '') {
            $primary = implode('|', [$supplierCode, $offerRef, $offerItemRef]);

            return hash('sha256', implode('|', [
                $primary,
                $direction,
                $segmentGroup,
                $offerId,
                $brandName,
                $fareBasis,
                $cabin,
                $rbd,
                $baggage,
                $refundability,
                $meal,
                $price,
                $currency,
            ]));
        }

        return hash('sha256', implode('|', [
            $supplierCode,
            $direction,
            $segmentGroup,
            $offerRef,
            $offerItemRef,
            $offerId,
            $brandName,
            $fareBasis,
            $cabin,
            $rbd,
            $baggage,
            $refundability,
            $meal,
            $price,
            $currency,
        ]));
    }

    /**
     * Attach fare variant subtitles for PIA NDC branded fare cards (all options).
     *
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public static function enrichVariantPresentation(array $options): array
    {
        foreach ($options as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }
            $subtitle = self::buildVariantSubtitle($row);
            if ($subtitle === '') {
                continue;
            }
            $options[$idx]['fare_variant_subtitle'] = $subtitle;
            $options[$idx]['fare_product_disambiguator'] = $subtitle;
        }

        return $options;
    }

    /**
     * Variant line under brand title: fare basis · baggage · class/RBD.
     *
     * @param  array<string, mixed>  $row
     */
    public static function buildVariantSubtitle(array $row): string
    {
        $ctx = is_array($row['provider_context'] ?? null) ? $row['provider_context'] : [];

        $parts = [];
        $fareBasis = strtoupper(trim((string) ($row['fare_basis'] ?? $ctx['fare_basis'] ?? '')));
        if ($fareBasis !== '') {
            $parts[] = $fareBasis;
        }

        $baggage = self::formatBaggageSubtitle($row);
        if ($baggage !== '') {
            $parts[] = $baggage;
        }

        $rbd = strtoupper(trim((string) ($row['booking_class'] ?? $ctx['rbd'] ?? '')));
        if ($rbd !== '') {
            $parts[] = 'Class '.$rbd;
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function buildDisambiguatorLabel(array $row): string
    {
        return self::buildVariantSubtitle($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function formatBaggageSubtitle(array $row): string
    {
        $checked = trim((string) ($row['check_in_summary'] ?? ''));
        if ($checked === '') {
            $checked = trim((string) ($row['baggage_summary'] ?? ''));
        }
        if ($checked === '') {
            return '';
        }

        if (preg_match('/(\d+)\s*kg/i', $checked, $matches)) {
            return $matches[1].' kg';
        }

        $lower = strtolower($checked);

        return $lower !== '' ? $lower : '';
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<array<string, mixed>>
     */
    public static function sameBrandDifferentProductGroups(array $options): array
    {
        $groups = [];
        $byBrand = [];
        foreach ($options as $row) {
            $brand = self::normalizeToken((string) ($row['brand_name'] ?? $row['name'] ?? ''));
            if ($brand === '') {
                continue;
            }
            $byBrand[$brand][] = $row;
        }

        foreach ($byBrand as $brand => $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $groups[] = [
                'brand_name' => $brand,
                'count' => count($rows),
                'samples' => array_map(
                    static fn (array $row): array => [
                        'offer_item_ref_id' => data_get($row, 'provider_context.offer_item_ref_id'),
                        'offer_ref_id' => data_get($row, 'provider_context.offer_ref_id'),
                        'fare_basis' => $row['fare_basis'] ?? null,
                        'booking_class' => $row['booking_class'] ?? null,
                        'price_total' => $row['price_total'] ?? null,
                        'disambiguator' => $row['fare_product_disambiguator'] ?? null,
                    ],
                    $rows,
                ),
            ];
        }

        return $groups;
    }

    public static function shouldLog(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        return (bool) config('suppliers.pia_ndc.branded_fare_dedup_log', false)
            || (bool) config('app.debug', false);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, mixed>  $stats
     * @param  list<array<string, mixed>>  $dropped
     */
    private static function logDedup(array $offer, array $stats, array $dropped): void
    {
        if (! self::shouldLog()) {
            return;
        }

        try {
            Log::channel('pia-ndc')->info('pia_ndc.branded_fare_dedup', [
                'offer_id' => $offer['offer_id'] ?? $offer['id'] ?? null,
                'search_type' => $stats['search_type'] ?? null,
                'before_count' => $stats['before_count'] ?? null,
                'after_count' => $stats['after_count'] ?? null,
                'dropped_duplicate_count' => $stats['dropped_duplicate_count'] ?? 0,
                'duplicate_groups' => $stats['duplicate_groups'] ?? [],
                'same_brand_different_product_groups' => $stats['same_brand_different_product_groups'] ?? [],
                'dropped_samples' => array_slice($dropped, 0, 8),
            ]);
        } catch (\Throwable) {
            // Non-blocking diagnostic logging.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function identitySnapshot(array $row, array $offer, string $dedupeKey): array
    {
        $ctx = is_array($row['provider_context'] ?? null) ? $row['provider_context'] : [];

        return [
            'dedupe_key' => $dedupeKey,
            'offer_id' => $row['source_offer_id'] ?? $offer['offer_id'] ?? null,
            'offer_ref_id' => $ctx['offer_ref_id'] ?? null,
            'offer_item_ref_id' => $ctx['offer_item_ref_id'] ?? null,
            'brand_name' => $row['brand_name'] ?? $row['name'] ?? null,
            'fare_basis' => $row['fare_basis'] ?? $ctx['fare_basis'] ?? null,
            'booking_class' => $row['booking_class'] ?? $ctx['rbd'] ?? null,
            'price_total' => $row['price_total'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     */
    private static function baggageToken(array $row, array $offer): string
    {
        $parts = [
            $row['baggage_summary'] ?? null,
            $row['check_in_summary'] ?? null,
            $row['carry_on_summary'] ?? null,
            data_get($offer, 'baggage.summary'),
            data_get($offer, 'baggage.checked'),
        ];

        return implode(' ', array_values(array_filter(array_map(
            static fn (mixed $v): string => trim((string) $v),
            $parts,
        ))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     */
    private static function normalizePrice(array $row, array $offer): string
    {
        $price = $row['price_total'] ?? data_get($offer, 'fare_breakdown.supplier_total', 0);
        if (! is_numeric($price)) {
            return '0';
        }

        return (string) (int) round((float) $price);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $offer
     */
    private static function segmentGroupKey(array $row, array $offer): string
    {
        $sourceId = trim((string) ($row['source_offer_id'] ?? ''));
        if ($sourceId !== '') {
            $member = ItineraryFareConsolidator::resolveGroupedSourceOffer($offer, $sourceId);
            if (is_array($member)) {
                $signature = ItineraryFareConsolidator::signatureForOffer($member);

                return $signature !== null ? substr($signature, 0, 16) : $sourceId;
            }
        }

        $signature = ItineraryFareConsolidator::signatureForOffer($offer);

        return $signature !== null ? substr($signature, 0, 16) : '';
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private static function inferDirection(array $offer): string
    {
        $trip = strtolower(trim((string) ($offer['trip_type'] ?? data_get($offer, 'search_criteria.trip_type', ''))));
        if (in_array($trip, ['return', 'round_trip', 'roundtrip'], true)) {
            return 'return';
        }
        if ($trip === 'multi_city') {
            return 'multicity';
        }

        $segments = is_array($offer['segments'] ?? null) ? $offer['segments'] : [];
        if (count($segments) >= 2) {
            $firstOrigin = strtoupper(trim((string) ($segments[0]['origin'] ?? '')));
            $lastDest = strtoupper(trim((string) ($segments[array_key_last($segments)]['destination'] ?? '')));
            if ($firstOrigin !== '' && $lastDest !== '' && $firstOrigin === $lastDest) {
                return 'return';
            }
        }

        return 'one_way';
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    private static function inferSearchType(array $offer): string
    {
        $direction = self::inferDirection($offer);

        return match ($direction) {
            'return' => 'return',
            'multicity' => 'multicity',
            default => 'one_way',
        };
    }

    private static function normalizeToken(string $value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyStats(int $before): array
    {
        return [
            'before_count' => $before,
            'after_count' => $before,
            'dropped_duplicate_count' => 0,
            'duplicate_groups' => [],
            'same_brand_different_product_groups' => [],
        ];
    }
}
