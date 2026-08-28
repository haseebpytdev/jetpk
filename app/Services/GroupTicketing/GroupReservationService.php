<?php

namespace App\Services\GroupTicketing;

use App\Enums\GroupBookingStatus;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Models\User;
use App\Services\Suppliers\AlHaider\AlHaiderClient;
use App\Services\Suppliers\AlHaider\AlHaiderGroupBookingPayloadBuilder;
use App\Services\Suppliers\AlHaider\AlHaiderGroupBookingPayloadException;
use App\Services\Suppliers\AlHaider\AlHaiderProviderException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Group seat holds, supplier reserve/cancel, manual payment, and payment window expiry.
 *
 * Supplier release atomicity: when supplier_reservation_id exists, local held_seats
 * decrement and Released status happen only after confirmed supplier cancel success.
 */
class GroupReservationService
{
    public function __construct(
        private readonly AlHaiderClient $client,
        private readonly GroupBookingReferenceGenerator $referenceGenerator,
        private readonly GroupBookingRestrictionService $restrictionService,
        private readonly GroupTicketingCommunicationService $communicationService,
        private readonly GroupInventoryAvailabilityService $availabilityService,
        private readonly AlHaiderGroupBookingPayloadBuilder $alHaiderBookingPayloadBuilder,
        private readonly GroupFinalCheckoutDecisionService $finalCheckoutDecisionService,
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
                'meta' => [
                    'inventory_snapshot' => $inventory->snapshot,
                    'checkout_mode' => 'local_checkout_intent',
                    'quoted_unit_price' => round((float) $inventory->price, 2),
                    'supplier_reservation_id' => null,
                    'availability_notice' => 'Availability and fare will be confirmed before payment.',
                ],
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

    public function createReservation(GroupBooking $booking, bool $acceptFareChange = false): GroupBooking
    {
        $result = DB::transaction(function () use ($booking, $acceptFareChange): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $inventory = GroupInventory::query()->lockForUpdate()->findOrFail($booking->group_inventory_id);

            if ($booking->supplier_reservation_id) {
                return $booking->fresh(['passengers', 'inventory', 'user']);
            }

            if ($booking->status !== GroupBookingStatus::PendingPassengerDetails) {
                throw new \RuntimeException('Booking is not ready for reservation.');
            }

            $availability = $this->availabilityService->revalidate($inventory, (int) $booking->seat_count);
            $inventory = $availability['inventory'];

            $quotedUnitPrice = round((float) (($booking->meta['quoted_unit_price'] ?? null) ?: $inventory->price), 2);
            $freshUnitPrice = round((float) $inventory->price, 2);

            if ($availability['unavailable']) {
                $booking->update(['status' => GroupBookingStatus::Failed]);

                if ((int) $availability['available_seats'] <= 0) {
                    throw new GroupFinalCheckoutBlockedException(
                        $this->finalCheckoutDecisionService->decide(
                            (int) $booking->seat_count,
                            0,
                            $quotedUnitPrice,
                            $freshUnitPrice,
                            (string) ($inventory->currency ?: 'PKR'),
                        ),
                    );
                }

                throw new \RuntimeException(GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE);
            }

            $decision = $this->finalCheckoutDecisionService->decide(
                (int) $booking->seat_count,
                (int) $availability['available_seats'],
                $quotedUnitPrice,
                $freshUnitPrice,
                (string) ($inventory->currency ?: 'PKR'),
                acceptFareChange: $acceptFareChange || (bool) ($booking->meta['accept_fare_change'] ?? false),
            );

            if ($decision['decision'] !== GroupFinalCheckoutDecisionService::DECISION_OK) {
                throw new GroupFinalCheckoutBlockedException($decision);
            }

            $supplierReservationId = null;
            $providerHoldStatus = 'local_checkout_intent';

            $mayCallSupplier = ! $inventory->isManualLocal()
                && $this->client->isConfigured()
                && (bool) config('suppliers.al_haider.booking_enabled');

            if ($mayCallSupplier) {
                try {
                    $payload = $this->alHaiderBookingPayloadBuilder->build($booking, $inventory);
                    $response = $this->client->reserveGroup(
                        $inventory->supplier_package_id,
                        $payload,
                    );
                    $supplierReservationId = (string) ($response['reservation_id'] ?? $response['id'] ?? '');
                    if ($supplierReservationId !== '') {
                        $providerHoldStatus = 'provider_held';
                    }
                } catch (AlHaiderGroupBookingPayloadException $exception) {
                    Log::warning('group_ticketing.reserve_payload_invalid', [
                        'booking_id' => $booking->id,
                        'error_code' => $exception->errorCode,
                    ]);
                    // Leave PendingPassengerDetails so the customer can correct details.
                    // Do not mark Failed inside this transaction (would roll back anyway).
                    throw new \RuntimeException($exception->getMessage(), 0, $exception);
                } catch (\Throwable $exception) {
                    Log::warning('group_ticketing.reserve_failed', [
                        'booking_id' => $booking->id,
                        'message' => $exception->getMessage(),
                    ]);
                    $booking->update(['status' => GroupBookingStatus::Failed]);
                    throw $exception;
                }
            } elseif ($inventory->isManualLocal()) {
                $providerHoldStatus = 'manual_local_held';
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
                    'checkout_mode' => $supplierReservationId !== ''
                        ? 'supplier_held'
                        : ($inventory->isManualLocal() ? 'manual_local_checkout' : 'local_checkout_intent'),
                    'qa_group_source' => $inventory->isManualLocal() ? 'MANUAL_LOCAL' : ($booking->meta['qa_group_source'] ?? null),
                    'quoted_unit_price' => $freshUnitPrice,
                    'availability_notice' => $supplierReservationId !== ''
                        ? 'Seats are held with the supplier pending payment.'
                        : 'Availability and fare will be confirmed before payment.',
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
        $previousStatus = $booking->status;

        $result = DB::transaction(function () use ($booking, $reason, $force): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->isReleased()) {
                return $booking;
            }

            if (! $force) {
                // Do not auto-retry SupplierReleaseFailed (no rapid retry).
                if ($booking->status === GroupBookingStatus::SupplierReleaseFailed) {
                    return $booking;
                }

                if ($booking->payment_submitted_at !== null) {
                    return $booking;
                }

                if (! $booking->isReleasable()) {
                    return $booking;
                }
            } else {
                $forceAllowed = $booking->isReleasable()
                    || $booking->status === GroupBookingStatus::SupplierReleaseFailed
                    || $booking->status === GroupBookingStatus::ManualPaymentPendingReview
                    || $booking->manual_payment_status === 'rejected';

                if (! $forceAllowed) {
                    return $booking;
                }
            }

            $supplierReservationId = trim((string) ($booking->supplier_reservation_id ?? ''));

            if ($supplierReservationId !== '') {
                return $this->releaseWithSupplierReservation($booking, $supplierReservationId, $reason);
            }

            return $this->finalizeLocalRelease($booking, $reason, null, false);
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

        if ($result->status === GroupBookingStatus::SupplierReleaseFailed
            && $previousStatus !== GroupBookingStatus::SupplierReleaseFailed) {
            $this->communicationService->sendSupplierReleaseFailed($result);
        }

        return $result;
    }

    /**
     * Manually retry supplier cancel when cancel gate is enabled (admin-triggered only).
     */
    public function retrySupplierRelease(GroupBooking $booking): GroupBooking
    {
        if ($booking->status !== GroupBookingStatus::SupplierReleaseFailed) {
            throw new \RuntimeException('Booking is not awaiting supplier release reconciliation.');
        }

        if (trim((string) ($booking->supplier_reservation_id ?? '')) === '') {
            throw new \RuntimeException('No supplier reservation is attached for reconciliation.');
        }

        if (! (bool) config('suppliers.al_haider.cancel_enabled')) {
            throw new \RuntimeException('Supplier cancel is not enabled. Use manual supplier cancel reconciliation instead.');
        }

        return $this->releaseUnpaidBooking(
            $booking,
            $booking->release_reason ?: 'supplier_release_retry',
            force: true,
        );
    }

    /**
     * Local-only reconcile after owner confirms the supplier reservation was cancelled outside the API.
     * Does not call Al-Haider.
     */
    public function reconcileAfterManualSupplierCancel(GroupBooking $booking, string $note = 'owner_manual_supplier_cancel'): GroupBooking
    {
        return DB::transaction(function () use ($booking, $note): GroupBooking {
            $booking = GroupBooking::query()->lockForUpdate()->findOrFail($booking->id);

            if ($booking->isReleased()) {
                return $booking;
            }

            if (trim((string) ($booking->supplier_reservation_id ?? '')) === '') {
                throw new \RuntimeException('Booking has no supplier reservation to reconcile.');
            }

            if (! in_array($booking->status, [
                GroupBookingStatus::SupplierReleaseFailed,
                GroupBookingStatus::ReservedAwaitingPayment,
                GroupBookingStatus::PaymentPending,
                GroupBookingStatus::ManualPaymentPendingReview,
            ], true)) {
                throw new \RuntimeException('Booking is not eligible for manual supplier-cancel reconciliation.');
            }

            $heldBefore = (int) (GroupInventory::query()->find($booking->group_inventory_id)?->held_seats ?? 0);

            $released = $this->finalizeLocalRelease($booking, $note, 'manual_supplier_cancel_confirmed', true);

            $released->update([
                'meta' => array_merge($released->meta ?? [], [
                    'reconciliation_state' => 'manual_supplier_cancel_reconciled',
                    'manual_supplier_cancel_reconciled_at' => now()->toIso8601String(),
                    'manual_supplier_cancel_note' => $note,
                    'held_seats_before_manual_reconcile' => $heldBefore,
                ]),
            ]);

            return $released->fresh(['passengers', 'inventory', 'user']);
        });
    }

    /** @deprecated Use releaseUnpaidBooking() */
    public function releaseExpired(GroupBooking $booking): GroupBooking
    {
        return $this->releaseUnpaidBooking($booking, 'unpaid_timeout');
    }

    private function releaseWithSupplierReservation(
        GroupBooking $booking,
        string $supplierReservationId,
        string $reason,
    ): GroupBooking {
        $booking->update(['supplier_release_attempted_at' => now()]);

        if (! $this->client->isConfigured()) {
            return $this->markSupplierReleaseFailed($booking, $reason, 'supplier_not_configured', 'Supplier client is not configured.');
        }

        if (! (bool) config('suppliers.al_haider.cancel_enabled')) {
            return $this->markSupplierReleaseFailed($booking, $reason, 'cancel_disabled', 'Supplier cancel is not enabled.');
        }

        try {
            $response = $this->client->cancelReservation($supplierReservationId, [
                'reference' => $booking->reference,
            ]);
            $supplierResponse = is_string($response) ? $response : json_encode($response);
        } catch (AlHaiderProviderException $exception) {
            Log::warning('group_ticketing.cancel_failed', [
                'booking_id' => $booking->id,
                'http_status' => $exception->httpStatus,
                'error_code' => $exception->errorCode,
            ]);

            return $this->markSupplierReleaseFailed(
                $booking,
                $reason,
                $exception->errorCode !== '' ? $exception->errorCode : 'supplier_cancel_failed',
                'Supplier cancel failed (HTTP '.$exception->httpStatus.').',
            );
        } catch (\Throwable $exception) {
            Log::warning('group_ticketing.cancel_failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->markSupplierReleaseFailed(
                $booking,
                $reason,
                'supplier_cancel_failed',
                'Supplier cancel failed.',
            );
        }

        return $this->finalizeLocalRelease($booking, $reason, $supplierResponse, true);
    }

    private function markSupplierReleaseFailed(
        GroupBooking $booking,
        string $reason,
        string $errorClass,
        string $safeResponse,
    ): GroupBooking {
        // CRITICAL: do not decrement held_seats; keep supplier_reservation_id attached.
        $booking->update([
            'status' => GroupBookingStatus::SupplierReleaseFailed,
            'release_reason' => $reason,
            'released_at' => null,
            'supplier_released_at' => null,
            'supplier_release_failed_at' => now(),
            'supplier_release_response' => $safeResponse,
            'expires_at' => null,
            'meta' => array_merge($booking->meta ?? [], [
                'reconciliation_state' => 'pending_supplier_release',
                'supplier_release_error_class' => $errorClass,
            ]),
        ]);

        return $booking->fresh(['passengers', 'inventory', 'user']);
    }

    private function finalizeLocalRelease(
        GroupBooking $booking,
        string $reason,
        ?string $supplierResponse,
        bool $supplierReleased,
    ): GroupBooking {
        $inventory = GroupInventory::query()->lockForUpdate()->find($booking->group_inventory_id);
        if ($inventory !== null) {
            $releaseSeats = min($booking->seat_count, $inventory->held_seats);
            if ($releaseSeats > 0) {
                $inventory->decrement('held_seats', $releaseSeats);
            }
        }

        $now = now();
        $booking->update([
            'status' => GroupBookingStatus::Released,
            'released_at' => $now,
            'release_reason' => $reason,
            'supplier_release_response' => $supplierResponse,
            'supplier_release_failed_at' => null,
            'supplier_released_at' => $supplierReleased ? $now : $booking->supplier_released_at,
            'expires_at' => null,
            'meta' => array_merge($booking->meta ?? [], [
                'reconciliation_state' => $supplierReleased ? 'supplier_released' : 'local_released',
            ]),
        ]);

        return $booking->fresh(['passengers', 'inventory', 'user']);
    }
}
