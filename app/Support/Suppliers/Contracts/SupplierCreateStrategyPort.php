<?php

namespace App\Support\Suppliers\Contracts;

use App\Models\Booking;

/**
 * Supplier-specific create-strategy port (PNR create, order create, etc.).
 */
interface SupplierCreateStrategyPort
{
    public function provider(): string;

    public function action(): string;

    public function distributionChannel(): string;

    /**
     * @return list<string>
     */
    public function supportedStrategyCodes(): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function strategyDefinitions(): array;

    /**
     * @return array<string, mixed>
     */
    public function selectForBooking(Booking $booking): array;

    /**
     * @return array<string, mixed>
     */
    public function buildBookingSummary(Booking $booking): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function buildCandidateDigests(Booking $booking, ?array $selection = null): array;
}
