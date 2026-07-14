<?php

namespace App\Services\Suppliers\Iati;

use App\Services\Suppliers\Iati\Exceptions\IatiValidationException;

/**
 * Resolves exactly one IATI /fare offer key for /option or /book payloads.
 */
class IatiSelectedOfferKeyResolver
{
    /**
     * @param  array<string, mixed>  $fare  Normalized fare from {@see IatiResponseNormalizer::normalizeFareResponse}
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $bookingMeta
     * @return array{
     *     offer_key: string,
     *     offer_index: int,
     *     can_book: bool,
     *     selection_reason: string
     * }
     */
    public function resolve(array $fare, array $providerContext, array $bookingMeta = []): array
    {
        $offers = $this->fareOffers($fare, $providerContext);
        if ($offers === []) {
            throw new IatiValidationException(
                'selected_offer_unresolved',
                422,
                'IATI fare confirmation returned no bookable offers. Admin review required.',
            );
        }

        $context = array_merge($providerContext, $this->selectionHintsFromMeta($bookingMeta));
        $index = $this->resolveOfferIndex($offers, $context, $bookingMeta);

        if ($index === null) {
            throw new IatiValidationException(
                'selected_offer_unresolved',
                422,
                'Could not determine the selected IATI fare offer safely. Admin review required.',
            );
        }

        $selected = $offers[$index] ?? null;
        $offerKey = trim((string) ($selected['offer_key'] ?? ''));
        if (! is_array($selected) || $offerKey === '') {
            throw new IatiValidationException(
                'selected_offer_unresolved',
                422,
                'Selected IATI fare offer is missing a bookable key. Admin review required.',
            );
        }

        return [
            'offer_key' => $offerKey,
            'offer_index' => $index,
            'can_book' => (bool) ($selected['can_book'] ?? true),
            'selection_reason' => (string) ($selected['_selection_reason'] ?? 'resolved'),
        ];
    }

    /**
     * @param  array<string, mixed>  $fare
     * @param  array<string, mixed>  $providerContext
     * @return list<array<string, mixed>>
     */
    public function fareOffers(array $fare, array $providerContext): array
    {
        $fromContext = is_array($providerContext['fare_offers'] ?? null) ? $providerContext['fare_offers'] : [];
        if ($fromContext !== []) {
            return array_values($fromContext);
        }

        $fromFare = is_array($fare['fare_offers'] ?? null) ? $fare['fare_offers'] : [];

        return array_values($fromFare);
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $bookingMeta
     */
    protected function resolveOfferIndex(array $offers, array $context, array $bookingMeta): ?int
    {
        $brandId = trim((string) ($context['selected_branded_fare_id'] ?? ''));
        if ($brandId !== '' && preg_match('/^iati_brand_(\d+)$/i', $brandId, $matches) === 1) {
            $index = (int) $matches[1];
            if ($this->offerExistsAtIndex($offers, $index)) {
                return $index;
            }
        }

        $fareOptionId = trim((string) ($context['selected_fare_option_id'] ?? ''));
        if ($fareOptionId !== '' && preg_match('/^iati-fare-(\d+)(?:-|$)/i', $fareOptionId, $matches) === 1) {
            $index = max(0, (int) $matches[1] - 1);
            if ($this->offerExistsAtIndex($offers, $index)) {
                return $index;
            }
        }

        $family = is_array($bookingMeta['selected_fare_family_option'] ?? null)
            ? $bookingMeta['selected_fare_family_option']
            : [];
        if ($family !== []) {
            $byFamily = $this->matchBySelectedFareFamily($offers, $family);
            if ($byFamily !== null) {
                return $byFamily;
            }
        }

        $targetPrice = $this->targetPrice($context, $bookingMeta, $family);
        if ($targetPrice !== null) {
            $byPrice = $this->matchByPrice($offers, $targetPrice);
            if ($byPrice !== null) {
                return $byPrice;
            }
        }

        if (count($offers) === 1) {
            return 0;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $bookingMeta
     * @return array<string, mixed>
     */
    protected function selectionHintsFromMeta(array $bookingMeta): array
    {
        $hints = [];
        $family = is_array($bookingMeta['selected_fare_family_option'] ?? null)
            ? $bookingMeta['selected_fare_family_option']
            : [];

        foreach (['selected_branded_fare_id', 'selected_fare_option_id'] as $key) {
            $value = trim((string) ($bookingMeta[$key] ?? ''));
            if ($value !== '') {
                $hints[$key] = $value;
            }
        }

        if ($family !== []) {
            foreach (['selected_branded_fare_id', 'selected_fare_option_id', 'option_key', 'id'] as $key) {
                $value = trim((string) ($family[$key] ?? ''));
                if ($value !== '' && ! isset($hints[$key])) {
                    $hints[$key] = $value;
                }
            }
        }

        $snapshot = is_array($bookingMeta['validated_offer_snapshot'] ?? null)
            ? $bookingMeta['validated_offer_snapshot']
            : [];
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $snapshotContext = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];
        foreach (['selected_branded_fare_id', 'selected_fare_option_id'] as $key) {
            $value = trim((string) ($snapshotContext[$key] ?? ''));
            if ($value !== '' && ! isset($hints[$key])) {
                $hints[$key] = $value;
            }
        }

        return $hints;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @param  array<string, mixed>  $family
     */
    protected function matchBySelectedFareFamily(array $offers, array $family): ?int
    {
        $name = strtolower(trim((string) ($family['name'] ?? $family['fare_family_label'] ?? $family['brand_name'] ?? '')));
        $displayedPrice = $this->numericPrice($family['displayed_price'] ?? $family['price_total'] ?? $family['price'] ?? null);
        $baggage = strtolower(trim((string) ($family['baggage_summary'] ?? $family['baggage'] ?? $family['check_in_summary'] ?? '')));

        $matches = [];
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $score = 0;
            $offerName = strtolower(trim((string) ($offer['fare_type'] ?? '')));
            if ($name !== '' && $offerName !== '' && str_contains($offerName, $name)) {
                $score += 2;
            }
            if ($displayedPrice !== null && abs((float) ($offer['total_price'] ?? 0) - $displayedPrice) <= 0.01) {
                $score += 3;
            }
            $offerBaggage = strtolower(trim((string) ($offer['baggage_summary'] ?? '')));
            if ($baggage !== '' && $offerBaggage !== '' && $offerBaggage === $baggage) {
                $score += 2;
            }
            if ($score > 0) {
                $matches[$index] = $score;
            }
        }

        if ($matches === []) {
            return null;
        }

        arsort($matches);
        $topScore = reset($matches);
        $topIndexes = array_keys(array_filter($matches, fn (int $score): bool => $score === $topScore));

        return count($topIndexes) === 1 ? (int) $topIndexes[0] : null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    protected function matchByPrice(array $offers, float $targetPrice): ?int
    {
        $matches = [];
        foreach ($offers as $index => $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $total = (float) ($offer['total_price'] ?? 0);
            if ($total > 0 && abs($total - $targetPrice) <= 0.01) {
                $matches[] = $index;
            }
        }

        return count($matches) === 1 ? (int) $matches[0] : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $bookingMeta
     * @param  array<string, mixed>  $family
     */
    protected function targetPrice(array $context, array $bookingMeta, array $family): ?float
    {
        foreach ([
            $family['displayed_price'] ?? null,
            $family['price_total'] ?? null,
            $family['price'] ?? null,
            $bookingMeta['selected_price'] ?? null,
            data_get($bookingMeta, 'fare_breakdown.supplier_total'),
            data_get($bookingMeta, 'validated_offer_snapshot.fare_breakdown.supplier_total'),
        ] as $candidate) {
            $price = $this->numericPrice($candidate);
            if ($price !== null && $price > 0) {
                return $price;
            }
        }

        return null;
    }

    protected function numericPrice(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $price = (float) $value;

        return $price > 0 ? $price : null;
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     */
    protected function offerExistsAtIndex(array $offers, int $index): bool
    {
        return isset($offers[$index]) && trim((string) ($offers[$index]['offer_key'] ?? '')) !== '';
    }
}
