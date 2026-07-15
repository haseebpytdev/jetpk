<?php

namespace App\Services\GroupTicketing;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\Suppliers\AlHaider\AlHaiderClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Group seat holds, supplier reserve/cancel, manual payment, and payment window expiry.
 */
class GroupReservationService
{
    public function __construct(
        private readonly AlHaiderClient $client,
        private readonly GroupBookingReferenceGenerator $referenceGenerator,
        private readonly GroupBookingRestrictionService $restrictionService,
        private readonly GroupTicketingCommunicationService $communicationService,
        private readonly GroupInventoryAvailabilityService $availabilityService,
    ) {}

    public function holdMinutes(): int
    {
        return max(1, (int) config('ota.group_booking_hold_minutes', 25));
    }

    /**
     * @param  list<array<string, mixed>>  $passengers
     * @param  array<string, mixed>  $contact
     */
    public function startDraft(GroupInventory $inventory, int $userId, int $seatCount, array $passengers, array $contact = []): GroupBooking
    {
        $seatCount = max(1, $seatCount);

        return DB::transaction(function () use ($inventory, $userId, $seatCount, $passengers, $contact): GroupBooking {
            $inventory = GroupInventory::query()->lockForUpdate()->findOrFail($inventory->id);

            if (! $inventory->hasAvailability($seatCount)) {
                throw new \RuntimeException('Not enough seats available for this group package.');
            }

            $total = (float) $inventory->price * $seatCount;

            $booking = GroupBooking::query()->create([
                'reference' => $this->referenceGenerator->generate(),
                'user_id' => $userId,
                'group_inventory_id' => $inventory->id,
                'status' => GroupBookingStatus::PendingPassengerDetails,
                'seat_count' => $seatCount,
                'total_amount' => $total,
                'currency' => $inventory->currency,
                'contact_name' => $contact['contact_name'] ?? null,
                'contact_email' => $contact['contact_email'] ?? null,
                'contact_phone' => $contact['contact_phone'] ?? null,
                'meta' => ['inventory_snapshot' => $inventory->snapshot],
            ]);

            foreach ($passengers as $index => $passenger) {
                $booking->passengers()->create([
                    'title' => $passenger['title'] ?? null,
                    'first_name' => $passenger['first_name'],
                    'last_name' => $passenger['last_name'],
                    'gender' => $passenger['gender'] ?? null,
                    'date_of_birth' => $passenger['date_of_birth'] ?? null,
                    'passport_number' => $passenger['passport_number'] ?? null,
                    'passport_issue_date' => $passenger['passport_issue_date'] ?? null,
                    'passport_expiry' => $passenger['passport_expiry'] ?? null,
                    'nationality' => $passenger['nationality'] ?? null,
                    'document_type' => $passenger['document_type'] ?? null,
                    'passenger_type' => $passenger['passenger_type'] ?? 'adult',
                    'sort_order' => $index,
                ]);
            }

            return $booking->fresh(['passengers', 'inventory']);
        });
    }

    public function createReservation(GroupBooking $booking): GroupBooking
    {
        $result = DB::transaction(function () use ($booking): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $inventory = GroupInventory::query()->lockForUpdate()->findOrFail($booking->group_inventory_id);

            if ($booking->status !== GroupBookingStatus::PendingPassengerDetails) {
                throw new \RuntimeException('Booking is not ready for reservation.');
            }

            $availability = $this->availabilityService->revalidate($inventory, (int) $booking->seat_count);
            $inventory = $availability['inventory'];

            if (! $availability['ok']) {
                $booking->update(['status' => GroupBookingStatus::Failed]);

                if ($availability['unavailable']) {
                    throw new \RuntimeException(GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE);
                }

                throw new \RuntimeException(
                    GroupInventoryAvailabilityService::insufficientSeatsMessage($availability['available_seats']),
                );
            }

            $supplierReservationId = null;
            $providerHoldStatus = 'unheld_manual_review';

            if ($this->client->isConfigured() && (bool) config('suppliers.al_haider.booking_enabled')) {
                try {
                    $response = $this->client->reserveGroup($inventory->supplier_package_id, [
                        'seats' => $booking->seat_count,
                        'reference' => $booking->reference,
                    ]);
                    $supplierReservationId = (string) ($response['reservation_id'] ?? $response['id'] ?? '');
                    if ($supplierReservationId !== '') {
                        $providerHoldStatus = 'provider_held';
                    }
                } catch (\Throwable $exception) {
                    Log::warning('group_ticketing.reserve_failed', [
                        'booking_id' => $booking->id,
                        'message' => $exception->getMessage(),
                    ]);
                    $booking->update(['status' => GroupBookingStatus::Failed]);
                    throw $exception;
                }
            } elseif ($availability['provider_confirmed'] ?? false) {
                $providerHoldStatus = 'provider_unheld_live_confirmed';
            }

            $inventory->increment('held_seats', $booking->seat_count);

            $now = now();
            $expiresAt = $now->copy()->addMinutes($this->holdMinutes());

            $booking->update([
                'status' => GroupBookingStatus::ReservedAwaitingPayment,
                'reservation_created_at' => $now,
                'expires_at' => $expiresAt,
                'supplier_reservation_id' => $supplierReservationId !== '' ? $supplierReservationId : null,
                'meta' => array_merge($booking->meta ?? [], [
                    'provider_hold_status' => $providerHoldStatus,
                ]),
            ]);

            return $booking->fresh(['passengers', 'inventory', 'user']);
        });

        $this->communicationService->sendReservationCreated($result);

        return $result;
    }

    public function markPaymentPending(GroupBooking $booking): GroupBooking
    {
        if ($booking->isExpired() && $booking->isReleasable()) {
            throw new \RuntimeException('Reservation has expired.');
        }

        if (! in_array($booking->status, [
            GroupBookingStatus::ReservedAwaitingPayment,
            GroupBookingStatus::PaymentPending,
            GroupBookingStatus::ManualPaymentPendingReview,
        ], true)) {
            return $booking;
        }

        if ($booking->status === GroupBookingStatus::ReservedAwaitingPayment) {
            $booking->update(['status' => GroupBookingStatus::PaymentPending]);
        }

        return $booking->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitManualPayment(GroupBooking $booking, array $data): GroupBooking
    {
        return DB::transaction(function () use ($booking, $data): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if (! in_array($booking->status, [
                GroupBookingStatus::ReservedAwaitingPayment,
                GroupBookingStatus::PaymentPending,
            ], true)) {
                throw new \RuntimeException('Booking is not awaiting payment.');
            }

            if ($booking->isExpired()) {
                throw new \RuntimeException('Reservation has expired.');
            }

            $booking->update([
                'status' => GroupBookingStatus::ManualPaymentPendingReview,
                'payment_submitted_at' => now(),
                'payment_method' => $data['payment_method'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_proof_path' => $data['payment_proof_path'] ?? null,
                'manual_payment_status' => 'pending_review',
            ]);

            $fresh = $booking->fresh(['passengers', 'inventory', 'user']);
            $this->communicationService->sendPaymentSubmitted($fresh);

            return $fresh;
        });
    }

    public function verifyPayment(GroupBooking $booking, User $admin): GroupBooking
    {
        return DB::transaction(function () use ($booking, $admin): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $inventory = GroupInventory::query()->lockForUpdate()->findOrFail($booking->group_inventory_id);

            if ($booking->status !== GroupBookingStatus::ManualPaymentPendingReview) {
                throw new \RuntimeException('Booking payment is not pending review.');
            }

            $inventory->decrement('held_seats', min($booking->seat_count, $inventory->held_seats));
            $inventory->increment('sold_seats', $booking->seat_count);

            $booking->update([
                'status' => GroupBookingStatus::Confirmed,
                'manual_payment_status' => 'verified',
                'admin_payment_verified_at' => now(),
                'admin_payment_verified_by' => $admin->id,
                'expires_at' => null,
            ]);

            return $booking->fresh(['passengers', 'inventory', 'user']);
        });
    }

    public function rejectPayment(GroupBooking $booking, User $admin, ?string $note = null): GroupBooking
    {
        return DB::transaction(function () use ($booking, $admin, $note): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->status !== GroupBookingStatus::ManualPaymentPendingReview) {
                throw new \RuntimeException('Booking payment is not pending review.');
            }

            $booking->update([
                'manual_payment_status' => 'rejected',
                'admin_payment_verified_at' => now(),
                'admin_payment_verified_by' => $admin->id,
                'meta' => array_merge($booking->meta ?? [], ['payment_rejection_note' => $note]),
            ]);

            return $this->releaseUnpaidBooking($booking->fresh(), 'payment_rejected', force: true);
        });
    }

    public function releaseUnpaidBooking(GroupBooking $booking, string $reason = 'unpaid_timeout', bool $force = false): GroupBooking
    {
        $result = DB::transaction(function () use ($booking, $reason, $force): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->isReleased()) {
                return $booking;
            }

            if (! $force && $booking->payment_submitted_at !== null) {
                return $booking;
            }

            if (! $force && ! $booking->isReleasable()) {
                return $booking;
            }

            $inventory = GroupInventory::query()->lockForUpdate()->find($booking->group_inventory_id);
            if ($inventory !== null) {
                $releaseSeats = min($booking->seat_count, $inventory->held_seats);
                if ($releaseSeats > 0) {
                    $inventory->decrement('held_seats', $releaseSeats);
                }
            }

            $supplierFailed = false;
            $supplierResponse = null;

            if ($booking->supplier_reservation_id && $this->client->isConfigured() && (bool) config('suppliers.al_haider.booking_enabled')) {
                $booking->update(['supplier_release_attempted_at' => now()]);
                try {
                    $response = $this->client->cancelReservation($booking->supplier_reservation_id, [
                        'reference' => $booking->reference,
                    ]);
                    $supplierResponse = is_string($response) ? $response : json_encode($response);
                    $booking->supplier_released_at = now();
                } catch (\Throwable $exception) {
                    $supplierFailed = true;
                    $supplierResponse = $exception->getMessage();
                    Log::warning('group_ticketing.cancel_failed', [
                        'booking_id' => $booking->id,
                        'message' => $exception->getMessage(),
                    ]);
                }
            }

            $status = $supplierFailed
                ? GroupBookingStatus::SupplierReleaseFailed
                : GroupBookingStatus::Released;

            $booking->update([
                'status' => $status,
                'released_at' => now(),
                'release_reason' => $reason,
                'supplier_release_response' => $supplierResponse,
                'supplier_release_failed_at' => $supplierFailed ? now() : null,
                'expires_at' => null,
            ]);

            return $booking->fresh(['passengers', 'inventory', 'user']);
        });

        if ($result->released_at !== null && $result->release_reason === 'unpaid_timeout') {
            $user = $result->user;
            if ($user !== null) {
                $restriction = $this->restrictionService->recordUnpaidRelease($user, $result);
                if ($restriction->isBlocked()) {
                    $this->communicationService->sendAccessRestricted($user, $restriction);
                }
            }
            $this->communicationService->sendReleasedUnpaid($result);
        }

        if ($result->status === GroupBookingStatus::SupplierReleaseFailed) {
            $this->communicationService->sendSupplierReleaseFailed($result);
        }

        return $result;
    }

    /** @deprecated Use releaseUnpaidBooking() */
    public function releaseExpired(GroupBooking $booking): GroupBooking
    {
        return $this->releaseUnpaidBooking($booking, 'unpaid_timeout');
    }
}
