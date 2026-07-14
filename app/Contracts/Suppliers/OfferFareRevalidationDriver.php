<?php

namespace App\Contracts\Suppliers;

use App\Models\Booking;

/**
 * Provider-specific fare revalidation before PNR creation (Phase B3).
 * Implementations must never log passport, DOB, payment payloads, or raw Authorization headers.
 */
interface OfferFareRevalidationDriver
{
    public function supports(string $supplierProvider): bool;

    /**
     * @return array{status: string, message?: string, price_changed?: bool}
     */
    public function revalidateBeforePnr(Booking $booking): array;
}
