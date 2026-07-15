<?php

namespace App\Services\FlightSearch;

use App\Models\Agency;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds nearby-date cheapest PKR fares for the results date-price strip.
 */
class NearbyDateFareStripService
{
    public function __construct(
        private readonly FlightSearchService $flightSearch,
    ) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return array{
     *     available: bool,
     *     selected_date: string|null,
     *     dates: list<array{
     *         date: string,
     *         label: string,
     *         cheapest_pkr: int|null,
     *         is_selected: bool,
     *         search_url: string
     *     }>
     * }
     */
    public function buildForCriteria(array $criteria, Agency $agency, callable $searchUrlBuilder): array
    {
        if (! (bool) config('ota-flights.nearby_date_strip.enabled', true)) {
            return $this->emptyResponse(null);
        }

        $tripType = (string) ($criteria['trip_type'] ?? 'one_way');
        if ($tripType === 'multi_city') {
            return $this->emptyResponse(null);
        }

        $departDate = trim((string) ($criteria['depart_date'] ?? ''));
        if ($departDate === '') {
            return $this->emptyResponse(null);
        }

        try {
            $selected = Carbon::parse($departDate)->startOfDay();
        } catch (Throwable) {
            return $this->emptyResponse(null);
        }

        $radius = max(1, min((int) config('ota-flights.nearby_date_strip.radius_days', 3), 7));
        $ttl = max(60, (int) config('ota-flights.nearby_date_strip.cache_ttl_seconds', 900));
        $cacheKey = 'nearby_date_strip:'.md5(json_encode([
            'origin' => strtoupper(trim((string) ($criteria['origin'] ?? ''))),
            'destination' => strtoupper(trim((string) ($criteria['destination'] ?? ''))),
            'depart' => $selected->toDateString(),
            'return' => trim((string) ($criteria['return_date'] ?? '')),
            'trip' => $tripType,
            'adults' => (int) ($criteria['adults'] ?? 1),
            'children' => (int) ($criteria['children'] ?? 0),
            'infants' => (int) ($criteria['infants'] ?? 0),
            'cabin' => (string) ($criteria['cabin'] ?? 'economy'),
            'radius' => $radius,
        ]));

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $dates = [];
        for ($offset = -$radius; $offset <= $radius; $offset++) {
            $candidate = $selected->copy()->addDays($offset);
            if ($candidate->lt(now()->startOfDay())) {
                continue;
            }
            $dateStr = $candidate->toDateString();
            $cheapest = $this->cheapestPkrForDate($criteria, $dateStr, $agency);
            $dates[] = [
                'date' => $dateStr,
                'label' => $candidate->format('D, j M'),
                'cheapest_pkr' => $cheapest,
                'is_selected' => $dateStr === $selected->toDateString(),
                'search_url' => $searchUrlBuilder($this->criteriaWithDepartDate($criteria, $dateStr)),
            ];
        }

        if ($dates === []) {
            return $this->emptyResponse($selected->toDateString());
        }

        $response = [
            'available' => true,
            'selected_date' => $selected->toDateString(),
            'dates' => $dates,
        ];

        Cache::put($cacheKey, $response, $ttl);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    private function cheapestPkrForDate(array $criteria, string $departDate, Agency $agency): ?int
    {
        try {
            $searchCriteria = $this->criteriaWithDepartDate($criteria, $departDate);
            $result = $this->flightSearch->searchWithMeta($searchCriteria, $agency, 'public_guest');
            $offers = is_array($result['offers'] ?? null) ? $result['offers'] : [];
            $cheapest = null;
            foreach ($offers as $offer) {
                if (! is_array($offer)) {
                    continue;
                }
                $final = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);
                $pricingCurrency = strtoupper(trim((string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR')));
                $conversionStatus = (string) ($offer['conversion_status'] ?? 'same_currency');
                if ($final <= 0 || $pricingCurrency !== 'PKR' || ! in_array($conversionStatus, ['same_currency', 'converted'], true)) {
                    continue;
                }
                $amount = (int) round($final);
                if ($cheapest === null || $amount < $cheapest) {
                    $cheapest = $amount;
                }
            }

            return $cheapest;
        } catch (Throwable $e) {
            Log::warning('nearby_date_strip.search_failed', [
                'depart_date' => $departDate,
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function criteriaWithDepartDate(array $criteria, string $departDate): array
    {
        $clone = $criteria;
        $clone['depart_date'] = $departDate;

        return $clone;
    }

    /**
     * @return array{available: bool, selected_date: string|null, dates: list<array<string, mixed>>}
     */
    private function emptyResponse(?string $selectedDate): array
    {
        return [
            'available' => false,
            'selected_date' => $selectedDate,
            'dates' => [],
        ];
    }
}
