<?php

namespace App\Support\FlightSearch;

/**
 * Shared traveller-count validation copy and defensive normalisation for search/booking.
 */
final class TravellerCountRules
{
    public const INFANTS_EXCEED_ADULTS_MESSAGE = 'Infants cannot be more than adults. Please add one adult for each infant.';

    public static function infantsExceedAdults(int $adults, int $infants): bool
    {
        return $infants > max(0, $adults);
    }

    /**
     * Clamp infant count to adult count; when adults is 0, infants must be 0.
     */
    public static function clampInfants(int $adults, int $infants): int
    {
        $adults = max(0, $adults);
        if ($adults === 0) {
            return 0;
        }

        return min(max(0, $infants), $adults);
    }

    /**
     * @return array{adults: int, children: int, infants: int}
     */
    public static function normalizeCounts(int $adults, int $children, int $infants, bool $requireMinAdult = true): array
    {
        $adults = max(0, $adults);
        if ($requireMinAdult && $adults < 1) {
            $adults = 1;
        }

        return [
            'adults' => $adults,
            'children' => max(0, $children),
            'infants' => self::clampInfants($adults, $infants),
        ];
    }
}
