<?php

namespace App\Services\Suppliers\AmeerEMillat;

use App\Contracts\GroupTicketing\GroupTicketSupplierInterface;
use App\Data\UmrahGroupPackageData;
use App\Data\UmrahGroupSearchResultData;
use App\Enums\SupplierProvider;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\Log;

/**
 * Thin adapter over AmeerEMillatGroupService + AmeerEMillatClient for group ticketing.
 */
class AmeerEMillatGroupTicketAdapter implements GroupTicketSupplierInterface
{
    public function __construct(
        private readonly AmeerEMillatGroupService $groupService,
        private readonly AmeerEMillatClient $client,
    ) {}

    public function providerKey(): string
    {
        return 'ameer_e_millat';
    }

    public function connectionProviderKey(): string
    {
        return SupplierProvider::AmeerEMillat->value;
    }

    public function publicIdPrefix(): string
    {
        return 'AEM-';
    }

    public function isEnabled(): bool
    {
        return (bool) config('suppliers.ameer_e_millat.enabled')
            && (bool) config('ota_client.modules.ameer_e_millat_group_ticketing', true);
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function supportsPrePaymentReservation(): bool
    {
        return false;
    }

    public function supportsPostPaymentBooking(): bool
    {
        return $this->isConfigured() && (bool) config('suppliers.ameer_e_millat.booking_enabled');
    }

    public function supportsCancel(): bool
    {
        return false;
    }

    public function supportsBookingRetrieve(): bool
    {
        return true;
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
        $booking->loadMissing(['passengers', 'user']);

        $groupId = (int) $inventory->supplier_package_id;
        if ($groupId <= 0) {
            throw new AmeerEMillatProviderException(
                'invalid_group_id',
                422,
                'Invalid Ameer-e-Millat group package id.'
            );
        }

        $payload = [
            'group_id' => $groupId,
            'passengers' => $this->mapPassengers($booking),
            'agency_info' => $this->resolveAgencyInfo($booking),
        ];

        $createResponse = $this->client->createBooking($payload);
        $supplierBookingId = $this->extractBookingId($createResponse);

        $showResponse = null;
        if ($supplierBookingId !== '') {
            try {
                $showResponse = $this->client->showBooking($supplierBookingId);
                $supplierBookingId = $this->extractBookingId($showResponse) ?: $supplierBookingId;
            } catch (\Throwable $exception) {
                Log::warning('ameer_e_millat.show_booking_after_create_failed', [
                    'booking_id' => $booking->id,
                    'supplier_booking_id' => $supplierBookingId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'supplier_booking_id' => $supplierBookingId !== '' ? $supplierBookingId : null,
            'raw' => SensitiveDataRedactor::redactSupplierPayload([
                'create' => $createResponse,
                'show' => $showResponse,
            ]),
        ];
    }

    public function retrieveSupplierBooking(string $supplierBookingId): array
    {
        $response = $this->client->showBooking($supplierBookingId);

        return [
            'supplier_booking_id' => $this->extractBookingId($response) ?: $supplierBookingId,
            'raw' => SensitiveDataRedactor::redactSupplierPayload($response),
        ];
    }

    public function reserveHold(GroupBooking $booking, GroupInventory $inventory): array
    {
        return [
            'skipped' => true,
            'reason' => 'post_payment_only',
        ];
    }

    public function cancelReservation(string $reservationId, array $payload = []): array
    {
        return [
            'skipped' => true,
            'reason' => 'unsupported',
        ];
    }

    public function testConnection(): array
    {
        $probe = $this->client->probeUserProfile();

        return [
            'provider' => $this->providerKey(),
            'configured' => $this->isConfigured(),
            'enabled' => $this->isEnabled(),
            'http_status' => $probe['http_status'] ?? null,
            'reason_code' => $probe['reason_code'] ?? null,
            'profile_ok' => $probe['profile_ok'] ?? false,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapPassengers(GroupBooking $booking): array
    {
        $passengers = [];
        foreach ($booking->passengers as $passenger) {
            $passengers[] = [
                'title' => $this->normalizeTitle($passenger->title, $passenger->passenger_type),
                'given_name' => $passenger->first_name,
                'surname' => $passenger->last_name,
                'passport_no' => $passenger->passport_number,
                'dob' => $passenger->date_of_birth?->format('Y-m-d'),
                'doe' => $passenger->passport_expiry?->format('Y-m-d'),
            ];
        }

        return $passengers;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAgencyInfo(GroupBooking $booking): array
    {
        $user = $booking->user;

        return array_filter([
            'name' => $booking->contact_name ?: ($user?->name ?? null),
            'email' => $booking->contact_email ?: ($user?->email ?? null),
            'phone' => $booking->contact_phone ?: ($user?->phone ?? null),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function normalizeTitle(?string $title, ?string $passengerType): string
    {
        $normalized = strtoupper(trim((string) $title));
        $type = strtolower(trim((string) $passengerType));

        if (in_array($normalized, ['MR', 'MRS', 'MS', 'CHD', 'INF'], true)) {
            return $normalized;
        }

        if ($type === 'child' || $type === 'chd') {
            return 'CHD';
        }

        if ($type === 'infant' || $type === 'inf') {
            return 'INF';
        }

        return match ($normalized) {
            'MR.', 'MISTER' => 'MR',
            'MRS.', 'MISSUS' => 'MRS',
            'MS.', 'MISS' => 'MS',
            'MASTER', 'CHILD' => 'CHD',
            'INFANT', 'BABY' => 'INF',
            default => $normalized !== '' ? $normalized : 'MR',
        };
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractBookingId(array $response): string
    {
        $candidates = [
            $response['booking_id'] ?? null,
            $response['id'] ?? null,
            $response['data']['booking_id'] ?? null,
            $response['data']['id'] ?? null,
            $response['booking']['id'] ?? null,
            $response['booking']['booking_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $id = trim((string) $candidate);
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }
}
