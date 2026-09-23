<?php

namespace App\Contracts\GroupTicketing;

use App\Data\UmrahGroupPackageData;
use App\Data\UmrahGroupSearchResultData;
use App\Models\GroupBooking;
use App\Models\GroupInventory;

interface GroupTicketSupplierInterface
{
    /** Inventory supplier slug stored on group_inventories.supplier. */
    public function providerKey(): string;

    /** SupplierConnection / SupplierProvider enum value. */
    public function connectionProviderKey(): string;

    public function publicIdPrefix(): string;

    public function isEnabled(): bool;

    public function isConfigured(): bool;

    public function supportsPrePaymentReservation(): bool;

    public function supportsPostPaymentBooking(): bool;

    public function supportsCancel(): bool;

    public function supportsBookingRetrieve(): bool;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function searchPackages(array $filters = [], bool $forceFresh = false): UmrahGroupSearchResultData;

    public function getPackageDetail(string $publicOrRawId, bool $forceFresh = false): ?UmrahGroupPackageData;

    public function getAvailableSeats(string $rawPackageId): ?int;

    /**
     * @return array{supplier_booking_id?: string, raw?: array<string, mixed>|null, skipped?: bool, reason?: string}
     */
    public function createSupplierBooking(GroupBooking $booking, GroupInventory $inventory): array;

    /**
     * @return array<string, mixed>
     */
    public function retrieveSupplierBooking(string $supplierBookingId): array;

    /**
     * @return array{supplier_reservation_id?: string, raw?: array<string, mixed>|null, skipped?: bool, reason?: string}
     */
    public function reserveHold(GroupBooking $booking, GroupInventory $inventory): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function cancelReservation(string $reservationId, array $payload = []): array;

    /**
     * Safe diagnostics — no secrets.
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array;
}
