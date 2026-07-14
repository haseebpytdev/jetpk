<?php

namespace App\Services\GroupTicketing;

use App\Enums\OtaNotificationEvent;
use App\Models\Agency;
use App\Models\GroupBooking;
use App\Models\GroupBookingUserRestriction;
use App\Models\User;
use App\Services\Communication\OtaNotificationService;
use Illuminate\Support\Facades\Log;

/**
 * Customer and admin notifications for group ticketing events.
 */
class GroupTicketingCommunicationService
{
    public function __construct(
        private readonly OtaNotificationService $notificationService,
    ) {}

    public function sendReservationCreated(GroupBooking $booking): void
    {
        try {
            $booking = $booking->fresh(['user', 'inventory']);
            $agency = $this->platformAgency();
            $expires = $booking->expires_at?->format('d M Y H:i') ?? '';

            $this->notificationService->send(
                agency: $agency,
                eventKey: OtaNotificationEvent::GroupBookingReservationCreated->value,
                payload: [
                    'group_booking_reference' => $booking->reference,
                    'expires_at' => $expires,
                    'total_amount' => number_format((float) $booking->total_amount, 0),
                    'currency' => $booking->currency,
                ],
                booking: null,
                actor: $booking->user,
                fallbackSubject: 'Group booking reserved — payment due within 25 minutes',
                fallbackBody: "Your group booking {$booking->reference} is reserved. Complete payment by {$expires}.",
                templateVariables: ['booking_reference' => $booking->reference],
                recipientContext: [
                    'notify_buckets' => ['platform_admin'],
                    'customer_email' => $booking->contact_email ?: $booking->user?->email,
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('group_ticketing.notify_reservation_created_failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function sendPaymentSubmitted(GroupBooking $booking): void
    {
        try {
            $booking = $booking->fresh(['user', 'inventory']);
            $agency = $this->platformAgency();

            $this->notificationService->send(
                agency: $agency,
                eventKey: OtaNotificationEvent::GroupBookingPaymentSubmitted->value,
                payload: [
                    'group_booking_reference' => $booking->reference,
                    'payment_method' => $booking->payment_method,
                    'payment_reference' => $booking->payment_reference,
                ],
                booking: null,
                actor: $booking->user,
                fallbackSubject: 'Group booking payment submitted — review required',
                fallbackBody: "Payment proof submitted for group booking {$booking->reference}. Please verify.",
                templateVariables: ['booking_reference' => $booking->reference],
                recipientContext: [
                    'notify_buckets' => ['platform_admin', 'finance'],
                    'customer_email' => $booking->contact_email ?: $booking->user?->email,
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('group_ticketing.notify_payment_submitted_failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function sendReleasedUnpaid(GroupBooking $booking): void
    {
        try {
            $booking = $booking->fresh(['user', 'inventory']);
            $agency = $this->platformAgency();

            $this->notificationService->send(
                agency: $agency,
                eventKey: OtaNotificationEvent::GroupBookingReleasedUnpaid->value,
                payload: [
                    'group_booking_reference' => $booking->reference,
                    'release_reason' => $booking->release_reason,
                ],
                booking: null,
                actor: $booking->user,
                fallbackSubject: 'Group booking released — payment not received',
                fallbackBody: "Your group booking {$booking->reference} was released because payment was not received in time.",
                templateVariables: ['booking_reference' => $booking->reference],
                recipientContext: [
                    'notify_buckets' => ['platform_admin'],
                    'customer_email' => $booking->contact_email ?: $booking->user?->email,
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('group_ticketing.notify_released_unpaid_failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function sendSupplierReleaseFailed(GroupBooking $booking): void
    {
        try {
            $booking = $booking->fresh(['user', 'inventory']);
            $agency = $this->platformAgency();

            $this->notificationService->send(
                agency: $agency,
                eventKey: OtaNotificationEvent::GroupBookingSupplierReleaseFailed->value,
                payload: [
                    'group_booking_reference' => $booking->reference,
                    'supplier_release_response' => $booking->supplier_release_response,
                ],
                booking: null,
                actor: null,
                fallbackSubject: 'URGENT: Group booking supplier release failed',
                fallbackBody: "Supplier release failed for group booking {$booking->reference}. Manual intervention required.",
                templateVariables: ['booking_reference' => $booking->reference],
                recipientContext: [
                    'notify_buckets' => ['platform_admin', 'operations_queue'],
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('group_ticketing.notify_supplier_release_failed', [
                'booking_id' => $booking->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function sendAccessRestricted(User $user, GroupBookingUserRestriction $restriction): void
    {
        try {
            $agency = $this->platformAgency();

            $this->notificationService->send(
                agency: $agency,
                eventKey: OtaNotificationEvent::GroupBookingAccessRestricted->value,
                payload: [
                    'unpaid_release_count' => $restriction->unpaid_release_count,
                ],
                booking: null,
                actor: $user,
                fallbackSubject: 'Group booking access restricted',
                fallbackBody: 'Your group booking access is temporarily restricted because 3 reservations expired without payment. Please contact support.',
                templateVariables: [],
                recipientContext: [
                    'notify_buckets' => ['platform_admin'],
                    'customer_email' => $user->email,
                ],
            );
        } catch (\Throwable $exception) {
            Log::warning('group_ticketing.notify_access_restricted_failed', [
                'user_id' => $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function platformAgency(): Agency
    {
        $slug = (string) config('ota.default_agency_slug', 'platform');

        return Agency::query()->where('slug', $slug)->first()
            ?? Agency::query()->firstOrFail();
    }
}
