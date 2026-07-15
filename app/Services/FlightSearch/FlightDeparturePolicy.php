<?php

namespace App\Services\FlightSearch;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class FlightDeparturePolicy
{
    public const SAME_DAY_LEAD_MINUTES = 600;

    public const SAME_DAY_LEAD_MESSAGE = 'Same-day flights must depart at least 10 hours from now. Please choose a later flight or another date.';

    /**
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $offers
     * @return array{0: list<array<string, mixed>>, 1: string|null}
     */
    public function filterOffersForLeadTime(array $criteria, array $offers): array
    {
        $warning = null;
        if (! $this->criteriaFirstDepartureIsToday($criteria)) {
            return [$offers, null];
        }

        $before = count($offers);
        $filtered = array_values(array_filter($offers, fn (array $o): bool => $this->offerMeetsSameDayLeadTime($o)));
        if ($before > 0 && $filtered === []) {
            $warning = self::SAME_DAY_LEAD_MESSAGE;
        }

        return [$filtered, $warning];
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function criteriaFirstDepartureIsToday(array $criteria): bool
    {
        $date = $this->firstSegmentDepartureDate($criteria);
        if ($date === '') {
            return false;
        }

        try {
            return Carbon::parse($date)->startOfDay()->equalTo(now()->startOfDay());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    public function offerMeetsLeadTimeForBooking(array $offer, array $criteria): bool
    {
        if (! $this->criteriaFirstDepartureIsToday($criteria)) {
            return true;
        }

        return $this->offerMeetsSameDayLeadTime($offer);
    }

    /**
     * @param  array<string, mixed>  $offer
     */
    public function offerMeetsSameDayLeadTime(array $offer): bool
    {
        $departAt = (string) ($offer['depart_at'] ?? $offer['departure_at'] ?? '');
        if ($departAt === '') {
            return false;
        }

        try {
            $departure = Carbon::parse($departAt);
        } catch (\Throwable) {
            return false;
        }

        $minimum = now()->addMinutes(self::SAME_DAY_LEAD_MINUTES);

        return $departure->greaterThanOrEqualTo($minimum);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    protected function firstSegmentDepartureDate(array $criteria): string
    {
        $segments = $criteria['segments'] ?? null;
        if (is_array($segments) && $segments !== []) {
            $first = $segments[0] ?? null;
            if (is_array($first)) {
                return (string) ($first['departure_date'] ?? '');
            }
        }

        return (string) ($criteria['depart_date'] ?? '');
    }

    public function minimumDepartTimeForSameDayDisplay(): CarbonInterface
    {
        return now()->addMinutes(self::SAME_DAY_LEAD_MINUTES);
    }
}
