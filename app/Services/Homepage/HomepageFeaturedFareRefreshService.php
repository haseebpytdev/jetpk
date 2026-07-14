<?php

namespace App\Services\Homepage;

use App\Enums\HomepageFeaturedFareRefreshStatus;
use App\Models\Agency;
use App\Models\HomepageFeaturedFare;
use App\Services\FlightSearch\FlightSearchService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Refreshes homepage featured fare rows via shopping search only (no booking/PNR).
 */
class HomepageFeaturedFareRefreshService
{
    public function __construct(
        protected FlightSearchService $flightSearch,
    ) {}

    public function refreshAll(?Agency $agency = null): int
    {
        $query = HomepageFeaturedFare::query()->where('is_enabled', true);
        if ($agency !== null) {
            $query->where('agency_id', $agency->id);
        }

        $count = 0;
        foreach ($query->orderBy('sort_order')->cursor() as $fare) {
            $this->refreshOne($fare);
            $count++;
        }

        return $count;
    }

    public function refreshOne(HomepageFeaturedFare $fare): void
    {
        $fare->loadMissing('agency');
        $agency = $fare->agency ?? Agency::query()->find($fare->agency_id);
        $departureDate = $fare->departureDate();

        try {
            $offers = $this->flightSearch->search(
                $fare->searchCriteria(),
                $agency,
                'homepage_featured_fare',
            );

            $cheapest = $this->pickCheapestOffer($offers);
            if ($cheapest === null) {
                $fare->update([
                    'last_refreshed_at' => now(),
                    'last_status' => HomepageFeaturedFareRefreshStatus::NoResults,
                    'last_error_code' => 'no_results',
                    'last_error_message' => null,
                    'snapshot' => null,
                ]);

                return;
            }

            $fare->update([
                'last_refreshed_at' => now(),
                'last_status' => HomepageFeaturedFareRefreshStatus::Success,
                'last_error_code' => null,
                'last_error_message' => null,
                'snapshot' => $this->buildSnapshot($fare, $cheapest, $departureDate),
            ]);
        } catch (Throwable $e) {
            Log::warning('homepage_featured_fare.refresh_failed', [
                'fare_id' => $fare->id,
                'origin' => $fare->origin_code,
                'destination' => $fare->destination_code,
                'exception' => $e::class,
            ]);

            $fare->update([
                'last_refreshed_at' => now(),
                'last_status' => HomepageFeaturedFareRefreshStatus::Failed,
                'last_error_code' => 'search_failed',
                'last_error_message' => $this->safeErrorMessage($e),
                'snapshot' => null,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array<string, mixed>|null
     */
    protected function pickCheapestOffer(array $offers): ?array
    {
        if ($offers === []) {
            return null;
        }

        usort($offers, fn (array $a, array $b): int => $this->offerTotal($a) <=> $this->offerTotal($b));

        foreach ($offers as $offer) {
            if ($this->offerTotal($offer) > 0) {
                return $offer;
            }
        }

        return $offers[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    protected function offerTotal(array $offer): float
    {
        $total = (float) ($offer['final_customer_price'] ?? $offer['total'] ?? 0);

        return $total > 0 ? $total : 0.0;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function buildSnapshot(HomepageFeaturedFare $fare, array $offer, string $departureDate): array
    {
        $departureAt = (string) ($offer['departure_at'] ?? $offer['depart_at'] ?? '');
        $arrivalAt = (string) ($offer['arrival_at'] ?? $offer['arrive_at'] ?? '');
        $durationMinutes = (int) ($offer['duration_minutes'] ?? 0);
        if ($durationMinutes <= 0) {
            $durationMinutes = ((int) ($offer['duration_h'] ?? 0)) * 60 + ((int) ($offer['duration_m'] ?? 0));
        }

        return [
            'origin_code' => strtoupper($fare->origin_code),
            'destination_code' => strtoupper($fare->destination_code),
            'airline_name' => (string) ($offer['airline_name'] ?? ''),
            'airline_code' => (string) ($offer['airline_code'] ?? ''),
            'flight_number' => (string) ($offer['flight_number'] ?? ''),
            'departure_date' => $departureDate,
            'departure_time' => $this->extractTime($departureAt),
            'arrival_time' => $this->extractTime($arrivalAt),
            'duration' => $this->formatDuration($durationMinutes),
            'baggage_summary' => (string) ($offer['baggage'] ?? ''),
            'refundable_label' => ! empty($offer['refundable']) ? 'Refundable' : 'Non-refundable',
            'price_total' => $this->offerTotal($offer),
            'currency' => (string) ($offer['pricing_currency'] ?? $offer['currency'] ?? 'PKR'),
            'provider' => (string) ($offer['supplier_provider'] ?? ''),
            'retrieved_at' => now()->toIso8601String(),
        ];
    }

    protected function extractTime(string $dateTime): ?string
    {
        if ($dateTime === '') {
            return null;
        }

        try {
            return Carbon::parse($dateTime)->format('H:i');
        } catch (Throwable) {
            return null;
        }
    }

    protected function formatDuration(int $minutes): ?string
    {
        if ($minutes <= 0) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours > 0
            ? sprintf('%dh %02dm', $hours, $mins)
            : sprintf('%dm', $mins);
    }

    protected function safeErrorMessage(Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            $message = 'Flight search failed.';
        }

        return mb_substr($message, 0, 500);
    }
}
