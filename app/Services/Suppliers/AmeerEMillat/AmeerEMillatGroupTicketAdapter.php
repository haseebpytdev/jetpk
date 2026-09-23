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
        $booking->loadMissing(['passengers', 'user.currentAgency']);

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
            'agency_info' => $this->resolveAgencyInfo($booking, $groupId),
            'booking_details' => $this->mapBookingDetails($booking),
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
     * Map JetPakistan passengers to vendor booking_details[] (Postman contract).
     *
     * @return list<array<string, mixed>>
     */
    private function mapBookingDetails(GroupBooking $booking): array
    {
        $details = [];
        foreach ($booking->passengers as $passenger) {
            $details[] = [
                'surname' => (string) $passenger->last_name,
                'given_name' => (string) $passenger->first_name,
                'title' => $this->normalizeTitle($passenger->title, $passenger->passenger_type),
                'passport_no' => (string) ($passenger->passport_number ?? ''),
                'dob' => $passenger->date_of_birth?->format('Y-m-d'),
                'doe' => $passenger->passport_expiry?->format('Y-m-d'),
            ];
        }

        return $details;
    }

    /**
     * Documented agency_info shape from FSD Ameer-e-Millat Postman collection.
     *
     * @return array{
     *     group_id: int,
     *     agent_name: string,
     *     agency_name: string,
     *     email: string,
     *     mobile: string,
     *     adults: int,
     *     child: int,
     *     infant: int,
     *     agent_notes: ?string
     * }
     */
    private function resolveAgencyInfo(GroupBooking $booking, int $groupId): array
    {
        $user = $booking->user;
        $counts = $this->countPassengerTypes($booking);

        $agencyName = '';
        if ($user !== null) {
            $agency = $user->currentAgency;
            if ($agency !== null) {
                $agencyName = trim((string) ($agency->name ?? ''));
            }
            if ($agencyName === '' && method_exists($user, 'agentDisplayAgencyName')) {
                $agencyName = trim((string) $user->agentDisplayAgencyName());
            }
        }
        if ($agencyName === '') {
            $agencyName = 'JetPakistan';
        }

        $agentName = trim((string) ($booking->contact_name ?: ($user?->name ?? '')));
        if ($agentName === '') {
            $agentName = $agencyName;
        }

        $email = trim((string) ($booking->contact_email ?: ($user?->email ?? '')));
        $mobile = trim((string) ($booking->contact_phone ?: ($user?->phone ?? '')));

        return [
            'group_id' => $groupId,
            'agent_name' => $agentName,
            'agency_name' => $agencyName,
            'email' => $email,
            'mobile' => $mobile,
            'adults' => $counts['adults'],
            'child' => $counts['child'],
            'infant' => $counts['infant'],
            'agent_notes' => null,
        ];
    }

    /**
     * @return array{adults: int, child: int, infant: int}
     */
    private function countPassengerTypes(GroupBooking $booking): array
    {
        $adults = 0;
        $child = 0;
        $infant = 0;

        foreach ($booking->passengers as $passenger) {
            $type = strtolower(trim((string) ($passenger->passenger_type ?? 'adult')));
            $title = strtoupper(trim((string) ($passenger->title ?? '')));

            if ($type === 'infant' || $type === 'inf' || $title === 'INF') {
                $infant++;
            } elseif ($type === 'child' || $type === 'chd' || $title === 'CHD') {
                $child++;
            } else {
                $adults++;
            }
        }

        if ($adults + $child + $infant === 0) {
            $adults = max(1, (int) $booking->seat_count);
        }

        return [
            'adults' => $adults,
            'child' => $child,
            'infant' => $infant,
        ];
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
     * Prefer documented create response data.id (pre- or post-unwrap).
     *
     * @param  array<string, mixed>  $response
     */
    private function extractBookingId(array $response): string
    {
        $candidates = [
            $response['data']['id'] ?? null,
            $response['id'] ?? null,
            $response['data']['booking_id'] ?? null,
            $response['booking_id'] ?? null,
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
