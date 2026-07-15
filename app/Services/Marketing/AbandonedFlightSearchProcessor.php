<?php

namespace App\Services\Marketing;

use App\Models\Booking;
use App\Models\FlightSearchMarketingSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class AbandonedFlightSearchProcessor
{
    public const SKIP_MISSING_EMAIL = 'missing_email';

    public const SKIP_NO_TOP_OFFERS = 'no_top_offers';

    public const SKIP_EXPIRED = 'expired';

    public const SKIP_BOOKING_FOUND = 'booking_found';

    public const SKIP_DAILY_CAP = 'daily_cap_reached';

    public const SKIP_DUPLICATE_CRITERIA = 'duplicate_criteria_recent';

    public const SKIP_PROCESSING_ERROR = 'processing_error';

    /**
     * @return array{checked: int, ready: int, skipped: int, expired: int, errors: int}
     */
    public function process(?int $batchSize = null): array
    {
        $limit = max(1, $batchSize ?? (int) config('ota.abandoned_search_followup.batch_size', 50));

        $stats = [
            'checked' => 0,
            'ready' => 0,
            'skipped' => 0,
            'expired' => 0,
            'errors' => 0,
        ];

        $snapshots = FlightSearchMarketingSnapshot::query()
            ->where('status', FlightSearchMarketingSnapshot::STATUS_PENDING)
            ->where('send_after_at', '<=', now())
            ->orderBy('send_after_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($snapshots as $snapshot) {
            $stats['checked']++;

            try {
                $outcome = $this->processSnapshot($snapshot);
                $stats[$outcome]++;
            } catch (Throwable $e) {
                $stats['errors']++;
                Log::warning('abandoned_flight_search.processor_row_failed', [
                    'snapshot_id' => $snapshot->id,
                    'search_id' => $snapshot->search_id,
                    'exception' => $e::class,
                ]);
                report($e);

                if (! $snapshot->markFailed(self::SKIP_PROCESSING_ERROR)) {
                    Log::notice('abandoned_flight_search.processor_row_not_pending', [
                        'snapshot_id' => $snapshot->id,
                    ]);
                }
            }
        }

        return $stats;
    }

    /**
     * @return 'ready'|'skipped'|'expired'
     */
    protected function processSnapshot(FlightSearchMarketingSnapshot $snapshot): string
    {
        if ($snapshot->expires_at !== null && $snapshot->expires_at->lte(now())) {
            $snapshot->markExpired(self::SKIP_EXPIRED);

            return 'expired';
        }

        $email = strtolower(trim((string) $snapshot->recipient_email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $snapshot->markSkipped(self::SKIP_MISSING_EMAIL);

            return 'skipped';
        }

        $topOffers = $snapshot->top_offers;
        if (! is_array($topOffers) || $topOffers === []) {
            $snapshot->markSkipped(self::SKIP_NO_TOP_OFFERS);

            return 'skipped';
        }

        if ($this->hasSuppressingBooking($snapshot)) {
            $snapshot->markSkipped(self::SKIP_BOOKING_FOUND);

            return 'skipped';
        }

        if ($this->hasDuplicateCriteriaRecently($snapshot)) {
            $snapshot->markSkipped(self::SKIP_DUPLICATE_CRITERIA);

            return 'skipped';
        }

        if ($this->hasReachedDailyCap($snapshot)) {
            $snapshot->markSkipped(self::SKIP_DAILY_CAP);

            return 'skipped';
        }

        $snapshot->markReady();

        return 'ready';
    }

    protected function hasSuppressingBooking(FlightSearchMarketingSnapshot $snapshot): bool
    {
        $searchedAt = $snapshot->searched_at;
        if ($searchedAt === null) {
            return false;
        }

        $criteria = is_array($snapshot->criteria) ? $snapshot->criteria : [];
        $searchId = trim((string) $snapshot->search_id);

        if ($searchId !== '') {
            $exists = Booking::query()
                ->where('created_at', '>=', $searchedAt)
                ->where('created_at', '<=', now())
                ->where('meta->checkout_search_id', $searchId)
                ->exists();

            if ($exists) {
                return true;
            }
        }

        if ($snapshot->user_id !== null) {
            $bookings = Booking::query()
                ->where('customer_id', $snapshot->user_id)
                ->where('created_at', '>=', $searchedAt)
                ->where('created_at', '<=', now())
                ->get();

            foreach ($bookings as $booking) {
                if ($this->criteriaRoughlyMatches($criteria, $booking)) {
                    return true;
                }
            }
        }

        $email = strtolower(trim((string) $snapshot->recipient_email));
        if ($email !== '') {
            $bookings = Booking::query()
                ->where('created_at', '>=', $searchedAt)
                ->where('created_at', '<=', now())
                ->whereHas('contact', function ($query) use ($email): void {
                    $query->whereRaw('LOWER(TRIM(email)) = ?', [$email]);
                })
                ->get();

            foreach ($bookings as $booking) {
                if ($this->criteriaRoughlyMatches($criteria, $booking)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $snapshotCriteria
     */
    protected function criteriaRoughlyMatches(array $snapshotCriteria, Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $bookingCriteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        if ($bookingCriteria === []) {
            return false;
        }

        $pairs = [
            ['origin', 'origin'],
            ['destination', 'destination'],
            ['trip_type', 'trip_type'],
            ['depart_date', 'depart_date'],
        ];

        foreach ($pairs as [$snapKey, $bookKey]) {
            $snapVal = strtoupper(trim((string) ($snapshotCriteria[$snapKey] ?? '')));
            $bookVal = strtoupper(trim((string) ($bookingCriteria[$bookKey] ?? '')));
            if ($snapVal === '' || $bookVal === '') {
                continue;
            }
            if ($snapVal !== $bookVal) {
                return false;
            }
        }

        $snapTrip = (string) ($snapshotCriteria['trip_type'] ?? 'one_way');
        if ($snapTrip === 'round_trip') {
            $snapReturn = trim((string) ($snapshotCriteria['return_date'] ?? ''));
            $bookReturn = trim((string) ($bookingCriteria['return_date'] ?? ''));
            if ($snapReturn !== '' && $bookReturn !== '' && $snapReturn !== $bookReturn) {
                return false;
            }
        }

        $snapCabin = strtolower(trim((string) ($snapshotCriteria['cabin'] ?? '')));
        $bookCabin = strtolower(trim((string) ($bookingCriteria['cabin'] ?? '')));
        if ($snapCabin !== '' && $bookCabin !== '' && $snapCabin !== $bookCabin) {
            return false;
        }

        if ($snapTrip === 'multi_city') {
            return $this->multiCitySegmentsMatch(
                is_array($snapshotCriteria['segments'] ?? null) ? $snapshotCriteria['segments'] : [],
                is_array($bookingCriteria['segments'] ?? null) ? $bookingCriteria['segments'] : [],
            );
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $snapshotSegments
     * @param  list<array<string, mixed>>  $bookingSegments
     */
    protected function multiCitySegmentsMatch(array $snapshotSegments, array $bookingSegments): bool
    {
        if ($snapshotSegments === [] || $bookingSegments === []) {
            return false;
        }

        if (count($snapshotSegments) !== count($bookingSegments)) {
            return false;
        }

        foreach ($snapshotSegments as $idx => $snapSeg) {
            if (! is_array($snapSeg)) {
                return false;
            }
            $bookSeg = $bookingSegments[$idx] ?? null;
            if (! is_array($bookSeg)) {
                return false;
            }

            foreach (['origin', 'destination'] as $field) {
                $s = strtoupper(trim((string) ($snapSeg[$field] ?? '')));
                $b = strtoupper(trim((string) ($bookSeg[$field] ?? '')));
                if ($s !== '' && $b !== '' && $s !== $b) {
                    return false;
                }
            }

            $snapDate = (string) ($snapSeg['departure_date'] ?? '');
            $bookDate = (string) ($bookSeg['departure_date'] ?? '');
            if ($snapDate !== '' && $bookDate !== '' && $snapDate !== $bookDate) {
                return false;
            }
        }

        return true;
    }

    protected function hasReachedDailyCap(FlightSearchMarketingSnapshot $snapshot): bool
    {
        $cap = max(0, (int) config('ota.abandoned_search_followup.daily_cap', 2));
        if ($cap === 0) {
            return false;
        }

        $searchedAt = $snapshot->searched_at;
        if ($searchedAt === null) {
            return false;
        }

        $email = strtolower(trim((string) $snapshot->recipient_email));
        $day = Carbon::parse($searchedAt)->toDateString();

        $count = FlightSearchMarketingSnapshot::query()
            ->where('recipient_email', $email)
            ->whereKeyNot($snapshot->id)
            ->whereIn('status', [
                FlightSearchMarketingSnapshot::STATUS_READY,
                FlightSearchMarketingSnapshot::STATUS_SENT,
            ])
            ->whereDate('searched_at', $day)
            ->count();

        return $count >= $cap;
    }

    protected function hasDuplicateCriteriaRecently(FlightSearchMarketingSnapshot $snapshot): bool
    {
        $email = strtolower(trim((string) $snapshot->recipient_email));
        $fingerprint = trim((string) $snapshot->criteria_fingerprint);
        if ($email === '' || $fingerprint === '') {
            return false;
        }

        return FlightSearchMarketingSnapshot::query()
            ->where('recipient_email', $email)
            ->where('criteria_fingerprint', $fingerprint)
            ->whereKeyNot($snapshot->id)
            ->whereIn('status', [
                FlightSearchMarketingSnapshot::STATUS_READY,
                FlightSearchMarketingSnapshot::STATUS_SENT,
            ])
            ->where('searched_at', '>=', now()->subDay())
            ->exists();
    }
}
