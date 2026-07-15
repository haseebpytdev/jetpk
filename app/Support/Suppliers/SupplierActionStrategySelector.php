<?php

namespace App\Support\Suppliers;

use App\Models\Booking;

/**
 * Universal supplier strategy selector — exactly one automatic strategy per booking attempt.
 */
final class SupplierActionStrategySelector
{
    public function __construct(
        protected SupplierActionStrategyRegistry $registry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function selectForBooking(Booking $booking, string $provider, string $action): array
    {
        return $this->registry->adapterFor($provider, $action)->selectForBooking($booking);
    }
}
