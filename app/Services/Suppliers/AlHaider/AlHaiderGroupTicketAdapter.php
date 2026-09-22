<?php

namespace App\Services\Suppliers\AlHaider;

use App\Contracts\GroupTicketing\GroupTicketSupplierInterface;
use App\Data\UmrahGroupPackageData;
use App\Data\UmrahGroupSearchResultData;
use App\Enums\SupplierProvider;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Thin adapter over AlHaiderUmrahGroupService + AlHaiderClient for group ticketing.
 */
class AlHaiderGroupTicketAdapter implements GroupTicketSupplierInterface
{
    public function __construct(
        private readonly AlHaiderUmrahGroupService $groupService,
        private readonly AlHaiderClient $client,
    ) {}

    public function providerKey(): string
    {
        return 'alhaider';
    }

    public function connectionProviderKey(): string
    {
        return SupplierProvider::AlHaider->value;
    }

    public function publicIdPrefix(): string
    {
        return 'ALH-';
    }

    public function isEnabled(): bool
    {
        return (bool) config('suppliers.al_haider.enabled')
            && (bool) config('ota_client.modules.al_haider_group_ticketing', true);
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function supportsPrePaymentReservation(): bool
    {
        return $this->isConfigured() && (bool) config('suppliers.al_haider.booking_enabled');
    }

    public function supportsPostPaymentBooking(): bool
    {
        return false;
    }

    public function supportsCancel(): bool
    {
        return (bool) config('suppliers.al_haider.booking_enabled');
    }

    public function supportsBookingRetrieve(): bool
    {
        return false;
    }

    public function searchPackages(array $filters = [], bool $forceFresh = false): UmrahGroupSearchResultData
    {
        return $this->groupService->search($filters, $forceFresh);
    }

    public function getPackageDetail(string $publicOrRawId, bool $forceFresh = false): ?UmrahGroupPackageData
    {
        return $this->groupService->getPackageDetail($publicOrRawId, $forceFresh);
    }

    public function getAvailableSeats(string $rawPackageId): ?int
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->client->getAvailableSeats($rawPackageId);
            if (isset($response['seats']) && is_numeric($response['seats'])) {
                return (int) $response['seats'];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function createSupplierBooking(GroupBooking $booking, GroupInventory $inventory): array
    {
        return [
            'skipped' => true,
            'reason' => 'pre_payment_only',
        ];
    }

    public function retrieveSupplierBooking(string $supplierBookingId): array
    {
        return [
            'skipped' => true,
            'reason' => 'not_supported',
        ];
    }

    public function reserveHold(GroupBooking $booking, GroupInventory $inventory): array
    {
        $response = $this->client->reserveGroup($inventory->supplier_package_id, [
            'seats' => $booking->seat_count,
            'reference' => $booking->reference,
        ]);

        $supplierReservationId = trim((string) ($response['reservation_id'] ?? $response['id'] ?? ''));

        return [
            'supplier_reservation_id' => $supplierReservationId !== '' ? $supplierReservationId : null,
            'raw' => SensitiveDataRedactor::redactSupplierPayload($response),
        ];
    }

    public function cancelReservation(string $reservationId, array $payload = []): array
    {
        $response = $this->client->cancelReservation($reservationId, $payload);

        return [
            'raw' => SensitiveDataRedactor::redactSupplierPayload(is_array($response) ? $response : ['response' => $response]),
        ];
    }

    public function testConnection(): array
    {
        $probe = $this->client->probeAuthentication();

        return [
            'provider' => $this->providerKey(),
            'configured' => $this->isConfigured(),
            'enabled' => $this->isEnabled(),
            'http_status' => $probe['http_status'] ?? null,
            'reason_code' => $probe['reason_code'] ?? null,
            'token_obtained' => $probe['token_obtained'] ?? false,
        ];
    }
}
