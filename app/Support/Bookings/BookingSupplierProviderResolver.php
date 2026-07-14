<?php

namespace App\Support\Bookings;

use App\Enums\SupplierProvider;
use App\Models\Booking;

/**
 * Stable supplier identity + scope for booking confirmation, review, and admin surfaces.
 *
 * @deprecated Prefer {@see SupplierLifecycleContextResolver} / {@see SupplierLifecycleRouter} for lifecycle routing.
 */
final class BookingSupplierProviderResolver
{
    public const SCOPE_SABRE_GDS = SupplierLifecycleContextResolver::HANDLER_SABRE_GDS;

    public const SCOPE_SABRE_NDC = SupplierLifecycleContextResolver::HANDLER_SABRE_NDC;

    public const SCOPE_PIA_NDC = SupplierLifecycleContextResolver::HANDLER_PIA_NDC;

    public const SCOPE_AIRBLUE = SupplierLifecycleContextResolver::HANDLER_AIRBLUE;

    public const SCOPE_AIRSIAL = SupplierLifecycleContextResolver::HANDLER_AIRSIAL;

    public const SCOPE_DUFFEL = SupplierLifecycleContextResolver::HANDLER_DUFFEL;

    public const SCOPE_IATI = SupplierLifecycleContextResolver::HANDLER_IATI;

    public const SCOPE_GROUP = SupplierLifecycleContextResolver::HANDLER_GROUP;

    public const SCOPE_OTHER = SupplierLifecycleContextResolver::HANDLER_OTHER;

    /**
     * @return array<string, mixed>
     */
    public static function meta(Booking $booking): array
    {
        return app(SupplierLifecycleContextResolver::class)->meta($booking);
    }

    public static function provider(Booking $booking): string
    {
        return app(SupplierLifecycleContextResolver::class)->resolve($booking)['supplier_provider'];
    }

    public static function scope(Booking $booking): string
    {
        return app(SupplierLifecycleContextResolver::class)->resolve($booking)['handler_key'];
    }

    public static function isSabre(Booking $booking): bool
    {
        return self::provider($booking) === SupplierProvider::Sabre->value;
    }

    public static function isPiaNdc(Booking $booking): bool
    {
        return self::provider($booking) === SupplierProvider::PiaNdc->value;
    }

    public static function isScope(string $scope, Booking $booking): bool
    {
        return self::scope($booking) === $scope;
    }
}
