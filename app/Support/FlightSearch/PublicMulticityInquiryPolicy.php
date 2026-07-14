<?php

namespace App\Support\FlightSearch;

/**
 * Public multi-city search is inquiry-only until Sabre multi-city PNR create is certified.
 */
final class PublicMulticityInquiryPolicy
{
    public const BLOCK_REASON = 'multicity_plan_only_not_certified';

    public const INQUIRY_NOTICE = 'Multi-city booking requires staff confirmation.';

    /**
     * @param  array<string, mixed>  $criteria
     */
    public static function isMulticitySearch(array $criteria): bool
    {
        return (string) ($criteria['trip_type'] ?? '') === 'multi_city';
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<string, mixed>|null  $offer
     */
    public static function blocksAutomaticCheckout(array $criteria, ?array $offer = null): bool
    {
        if (self::isMulticitySearch($criteria)) {
            return true;
        }

        return is_array($offer) && ($offer['multicity_inquiry_only'] ?? false) === true;
    }
}
