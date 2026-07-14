<?php

namespace App\Support\GroupTicketing;

/**
 * Fail-closed live provider availability rules for public group ticketing.
 */
class GroupTicketingLivePolicy
{
    public const PUBLIC_SEARCH_UNAVAILABLE_MESSAGE = 'Live group availability is temporarily unavailable. Please try again.';

    public const BOOKING_BLOCKED_MESSAGE = 'This group seat is no longer available. Please search again.';

    public static function requireLiveProviderForPublicResults(): bool
    {
        return (bool) config('ota.group_ticketing.require_live_provider_for_public_results', true);
    }

    public static function allowStalePublicResults(): bool
    {
        return (bool) config('ota.group_ticketing.allow_stale_public_results', false);
    }

    public static function requireLiveProviderForReservation(): bool
    {
        return (bool) config('ota.group_ticketing.require_live_provider_for_reservation', true);
    }

    public static function blockBookingWhenProviderUnavailable(): bool
    {
        return (bool) config('ota.group_ticketing.block_booking_when_provider_unavailable', true);
    }

    public static function publicResultsMustBeProviderConfirmed(): bool
    {
        return self::requireLiveProviderForPublicResults() && ! self::allowStalePublicResults();
    }
}
