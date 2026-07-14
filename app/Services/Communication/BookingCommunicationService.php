<?php

namespace App\Services\Communication;

use App\Enums\AccountType;
use App\Enums\BookingCommunicationEvent;
use App\Enums\BookingDocumentStatus;
use App\Enums\BookingDocumentType;
use App\Enums\OtaNotificationEvent;
use App\Mail\BookingUniversalNotification;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Models\CommunicationLog;
use App\Models\User;
use App\Services\Customer\GuestBookingAccessService;
use App\Support\Bookings\SabreHostErrorClassifier;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BookingCommunicationService
{
    private const SUPPLIER_CONNECTION_AUTH_NOTIFICATION_TYPE = 'supplier_connection_auth_failed';

    /** @var list<string> */
    private const SUPPLIER_CONNECTION_AUTH_ERROR_CODES = [
        'sabre_token_failed',
        'sabre_auth_failed',
        'sabre_booking_forbidden',
        'invalid_client',
        'invalid_grant',
        'missing_credentials',
    ];

    private const SUPPLIER_CONNECTION_AUTH_ALERT_COOLDOWN_MINUTES = 60;

    public function __construct(
        protected GuestBookingAccessService $guestAccessService,
        protected AgencyCommunicationSettingsService $agencyCommunicationSettingsService,
        protected OtaNotificationService $otaNotificationService,
        protected BookingEmailPayloadFactory $bookingEmailPayloadFactory,
    ) {}

    public function sendBookingRequestReceived(Booking $booking, ?User $actor = null): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'customer', 'fareBreakdown']);

        if (! $this->customerEmailAlreadySent($booking, BookingCommunicationEvent::BookingRequestReceived->value)) {
            $guestToken = $this->guestAccessService->createTokenForBooking(
                $booking,
                $booking->contact?->email,
                $booking->contact?->phone
            );

            $this->sendEmailForBooking(
                $booking,
                BookingCommunicationEvent::BookingRequestReceived,
                fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->bookingReceived($b)),
                [
                    'guest_lookup_token_created' => true,
                    'guest_lookup_expires_at' => now()->addMinutes((int) config('ota.guest_lookup_token_minutes', 30))->toIso8601String(),
                    'token_not_emailed' => true,
                    'token_length' => strlen($guestToken),
                ]
            );
        }

        if (! $this->operationalNotificationAlreadyLogged($booking, OtaNotificationEvent::BookingRequestReceived->value, 'admin_new_booking_alert')) {
            $amountContext = $this->bookingEmailAmountContext($booking);
            $adminUrl = $this->safeAdminBookingUrl($booking);
            $adminBody = $this->buildAdminNewBookingFallbackBody($booking, $adminUrl);

            $this->notifyOperational($booking, OtaNotificationEvent::BookingRequestReceived, [
                'booking_reference' => $booking->reference_code,
                'route' => (string) ($booking->route ?? ''),
                'travel_date' => optional($booking->travel_date)->toDateString(),
                'passenger_count' => $booking->passengers->count(),
                'passenger_summary' => $this->passengerSummary($booking),
                'customer_name' => $this->contactDisplayName($booking),
                'customer_email' => (string) ($booking->contact?->email ?? $booking->customer?->email ?? ''),
                'customer_phone' => (string) ($booking->contact?->phone ?? ''),
                'amount' => $amountContext['amount'],
                'currency' => $amountContext['currency'],
                'estimated_selected_fare' => $amountContext['estimated_selected_fare'],
                'selected_fare_family_label' => $amountContext['selected_fare_family_label'],
                'status' => $booking->status->value,
                'admin_booking_url' => $adminUrl,
            ], null, 'New customer booking — '.$booking->reference_code, $adminBody);
        }

        if (! $this->bookingCreatedByAgencySideActor($booking, $actor)) {
            return;
        }

        if ($this->operationalNotificationAlreadyLogged($booking, OtaNotificationEvent::BookingRequestReceived->value, 'booking_created_b2b')) {
            return;
        }

        $amountContext = $this->bookingEmailAmountContext($booking);
        $b2bBuckets = $this->bookingCreatedB2bRecipientBuckets($booking, $actor);
        $routingPolicy = $this->bookingCreatedByAgentStaff($booking, $actor) ? 'A2_agent_staff' : 'A1_agency_or_agent_admin';

        $this->notifyOperational($booking, OtaNotificationEvent::BookingRequestReceived, [
            'booking_reference' => $booking->reference_code,
            'route' => (string) ($booking->route ?? ''),
            'travel_date' => optional($booking->travel_date)->toDateString(),
            'trip_type' => (string) (data_get($booking->meta, 'itinerary_overview.trip_type_label') ?? ''),
            'passenger_count' => $booking->passengers->count(),
            'amount' => $amountContext['amount'],
            'currency' => $amountContext['currency'],
            'estimated_selected_fare' => $amountContext['estimated_selected_fare'],
            'selected_fare_family_label' => $amountContext['selected_fare_family_label'],
            'status' => $booking->status->value,
            'universal_email' => $this->bookingEmailPayloadFactory->b2bBookingCreated($booking),
            'routing_note' => 'B2B booking-created notification for agency/agent recipients; customer booking request received email remains the only customer-facing path.',
            'routing_policy' => $routingPolicy,
        ], $actor, 'New agency booking created '.$booking->reference_code, 'New agency booking '.$booking->reference_code.' has been created.', [
            'notify_buckets' => $b2bBuckets,
            'deduplicated_buckets' => ['customer_party', 'booking_customer'],
            'booking_creator_user_id' => $this->resolveBookingCreatorUserId($booking, $actor),
            'booking_creator_role' => $this->resolveBookingCreatorRole($booking, $actor),
            'booking_creator_source' => $this->resolveBookingCreatorSource($booking, $actor),
        ]);
    }

    /**
     * Send itinerary/ticket email when a PDF itinerary exists and/or ticket records exist.
     *
     * @return array{sent: bool, message: string, skipped?: bool}
     */
    public function sendItineraryReady(
        Booking $booking,
        ?BookingDocument $document = null,
        ?User $actor = null,
        ?string $note = null,
        bool $forceManual = false,
    ): array {
        try {
            $booking = $booking->fresh(['agency.agencySetting', 'contact', 'passengers', 'customer', 'fareBreakdown', 'tickets']);

            $document = $document ?? $this->resolveLatestItineraryDocument($booking);
            $attachmentPath = $this->resolveItineraryAttachmentPath($document);
            $hasPdf = $attachmentPath !== null;
            $hasTickets = $booking->tickets->isNotEmpty();

            if (! $hasPdf && ! $hasTickets) {
                return [
                    'sent' => false,
                    'message' => 'No ticket itinerary PDF or ticket records are available for this booking.',
                ];
            }

            if (! $forceManual && $document !== null && $this->itineraryEmailAlreadySentForDocument($booking, (int) $document->id)) {
                return [
                    'sent' => false,
                    'skipped' => true,
                    'message' => 'Itinerary email was already sent for this PDF (duplicate prevention is limited to the same document; use manual send to resend).',
                ];
            }

            if ($hasPdf) {
                $sent = $this->dispatchItineraryReadyEmail($booking, $attachmentPath, $note, $document, $actor);

                return [
                    'sent' => $sent,
                    'message' => $sent
                        ? 'Ticket itinerary email sent with PDF attachment.'
                        : 'Ticket itinerary email could not be sent (see communication log).',
                ];
            }

            $this->sendEmailForBooking(
                $booking,
                BookingCommunicationEvent::TicketIssued,
                fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->ticketIssued($b)),
                ['source' => 'itinerary_ready_tickets_only', 'manual' => $forceManual],
            );

            return [
                'sent' => true,
                'message' => 'Ticket details email sent (no itinerary PDF attached).',
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'sent' => false,
                'message' => 'Itinerary email could not be sent. The booking was not affected.',
            ];
        }
    }

    public function sendBookingConfirmed(Booking $booking): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer']);
        $this->sendEmailForBooking(
            $booking,
            BookingCommunicationEvent::BookingConfirmed,
            fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->statusChanged($b, 'confirmed'))
        );
        $this->notifyOperational($booking, OtaNotificationEvent::BookingConfirmed, [
            'booking_reference' => $booking->reference_code,
        ]);
    }

    public function sendPaymentSubmitted(BookingPayment $payment): void
    {
        $payment = $payment->fresh(['booking.agency.agencySetting', 'booking.agent.user']);
        $booking = $payment->booking;
        $this->logSystemEvent($booking, BookingCommunicationEvent::PaymentSubmitted->value, [
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
        ]);
        $this->notifyOperational($booking, OtaNotificationEvent::PaymentProofSubmitted, [
            'booking_reference' => $booking->reference_code,
            'booking_payment_id' => $payment->id,
            'payment_id' => $payment->id,
            'payment_event' => 'payment_proof_submitted',
            'payment_status' => $payment->status->value,
            'amount' => (float) $payment->amount,
            'routing_note' => 'Internal platform staff payment proof alert; customer and B2B agency notifications are sent separately when applicable.',
        ], null, null, null, [
            'notify_buckets' => ['finance', 'assigned_staff', 'operations_queue', 'platform_admin'],
        ]);

        $this->notifyAgencyPaymentB2b(
            $booking,
            $payment,
            OtaNotificationEvent::PaymentProofSubmitted,
            'payment_proof_submitted_b2b',
            $this->bookingEmailPayloadFactory->agencyPaymentProofSubmitted($booking, $payment),
            'payment_proof_submitted',
        );
    }

    public function sendPaymentRecorded(BookingPayment $payment, ?User $actor = null): void
    {
        $booking = $payment->booking()->firstOrFail();
        $this->logSystemEvent($booking, BookingCommunicationEvent::PaymentSubmitted->value, [
            'payment_id' => $payment->id,
            'amount' => (float) $payment->amount,
            'recorded_manually' => true,
        ]);
        $this->notifyOperational($booking, OtaNotificationEvent::PaymentRecorded, [
            'booking_reference' => $booking->reference_code,
            'booking_payment_id' => $payment->id,
            'payment_id' => $payment->id,
            'payment_event' => 'payment_recorded',
            'payment_status' => $payment->status->value,
            'amount' => (float) $payment->amount,
            'routing_note' => 'Internal finance/admin payment recorded alert only; no B2B agency payment-recorded route in this phase.',
        ], $actor);
    }

    public function sendPaymentVerified(BookingPayment $payment): void
    {
        $payment = $payment->fresh(['booking.agency.agencySetting', 'booking.contact', 'booking.customer', 'booking.agent.user']);
        $booking = $payment->booking;
        $this->sendEmailForBooking(
            $booking,
            BookingCommunicationEvent::PaymentVerified,
            fn (): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->paymentVerified($payment))
        );
        $this->notifyOperational($booking, OtaNotificationEvent::PaymentVerified, [
            'booking_reference' => $booking->reference_code,
            'booking_payment_id' => $payment->id,
            'payment_event' => 'payment_verified',
            'payment_status' => $payment->status->value,
            'amount' => (float) $payment->amount,
            'routing_note' => 'Internal platform staff payment verified alert; customer direct email remains the only customer-facing path.',
        ], null, null, null, [
            'notify_buckets' => ['platform_admin', 'assigned_staff'],
        ]);

        $this->notifyAgencyPaymentB2b(
            $booking,
            $payment,
            OtaNotificationEvent::PaymentVerified,
            'payment_verified_b2b',
            $this->bookingEmailPayloadFactory->agencyPaymentVerified($booking, $payment),
            'payment_verified',
        );
    }

    public function sendPaymentRejected(BookingPayment $payment): void
    {
        $payment = $payment->fresh(['booking.agency.agencySetting', 'booking.contact', 'booking.customer', 'booking.agent.user']);
        $booking = $payment->booking;
        $this->sendEmailForBooking(
            $booking,
            BookingCommunicationEvent::PaymentRejected,
            fn (): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->paymentRejected($payment))
        );
        $this->notifyOperational($booking, OtaNotificationEvent::PaymentRejected, [
            'booking_reference' => $booking->reference_code,
            'booking_payment_id' => $payment->id,
            'payment_event' => 'payment_rejected',
            'payment_status' => $payment->status->value,
            'amount' => (float) $payment->amount,
            'routing_note' => 'Internal platform staff payment rejected alert; customer direct email remains the only customer-facing path.',
        ], null, null, null, [
            'notify_buckets' => ['platform_admin', 'assigned_staff'],
        ]);

        $this->notifyAgencyPaymentB2b(
            $booking,
            $payment,
            OtaNotificationEvent::PaymentRejected,
            'payment_rejected_b2b',
            $this->bookingEmailPayloadFactory->agencyPaymentRejected($booking, $payment),
            'payment_rejected',
        );
    }

    public function sendSupplierBookingCreated(Booking $booking): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'agent.user']);
        $this->logSystemEvent($booking, BookingCommunicationEvent::SupplierBookingCreated->value, [
            'supplier_booking_status' => $booking->supplier_booking_status,
            'pnr' => $booking->pnr,
        ]);
        $this->notifyOperational($booking, OtaNotificationEvent::SupplierBookingCreated, [
            'booking_reference' => $booking->reference_code,
            'supplier_booking_status' => $booking->supplier_booking_status,
            'pnr' => $booking->pnr,
            'routing_note' => 'Supplier booking created notification for the booking party.',
        ], null, null, null, [
            'notify_buckets' => ['customer_party'],
        ]);
        $this->notifyOperational($booking, OtaNotificationEvent::SupplierBookingCreated, [
            'booking_reference' => $booking->reference_code,
            'supplier_booking_status' => $booking->supplier_booking_status,
            'pnr' => $booking->pnr,
            'routing_note' => 'Internal ticketing queue/action review may be required when status is pending_ticketing.',
        ], null, null, null, [
            'notify_buckets' => ['assigned_staff', 'operations_queue', 'platform_admin'],
            'deduplicated_buckets' => ['customer_party'],
        ]);
    }

    public function sendTicketIssued(Booking $booking): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'tickets', 'agent.user']);
        $this->sendEmailForBooking(
            $booking,
            BookingCommunicationEvent::TicketIssued,
            fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->ticketIssued($b))
        );
        $this->notifyTicketIssuedOperationalOnly($booking);
    }

    public function notifyTicketIssuedOperationalOnly(Booking $booking): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'tickets', 'agent.user']);
        $this->notifyOperational($booking, OtaNotificationEvent::TicketIssued, [
            'booking_reference' => $booking->reference_code,
            'tickets_count' => $booking->tickets()->count(),
            'routing_note' => 'Internal ticket issued notification.',
        ], null, null, null, [
            'notify_buckets' => ['platform_admin', 'assigned_staff'],
        ]);

        $this->notifyOperational($booking, OtaNotificationEvent::TicketIssued, [
            'booking_reference' => $booking->reference_code,
            'tickets_count' => $booking->tickets()->count(),
            'universal_email' => $this->bookingEmailPayloadFactory->b2bTicketIssued($booking),
            'routing_note' => 'B2B ticket issued notification for agency/agent recipients.',
        ], null, 'Ticket issued for booking '.$booking->reference_code, 'Ticketing has been completed for booking '.$booking->reference_code.'.', [
            'notify_buckets' => ['booking_agent', 'agency_admin', 'agent_staff_creator'],
        ]);
    }

    public function logSystemEvent(Booking $booking, string $event, array $meta = []): CommunicationLog
    {
        return CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->customer_id,
            'channel' => 'system',
            'event' => $event,
            'status' => 'sent',
            'meta' => $meta,
            'sent_at' => now(),
        ]);
    }

    public function sendBookingStatusChanged(Booking $booking, string $statusLabel): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer']);
        $event = ($booking->status->value === 'cancelled')
            ? BookingCommunicationEvent::BookingCancelled
            : BookingCommunicationEvent::BookingStatusChanged;
        if ($this->isManualReviewStatus($booking, $statusLabel)) {
            $event = BookingCommunicationEvent::CustomerManualReviewRequired;
        }
        $payloadFactory = $event === BookingCommunicationEvent::CustomerManualReviewRequired
            ? fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->customerManualReviewRequired($b))
            : ($event === BookingCommunicationEvent::BookingCancelled
            ? fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->customerCancellationUpdate($b, $statusLabel))
            : fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->statusChanged($b, $statusLabel)));

        $this->sendEmailForBooking(
            $booking,
            $event,
            $payloadFactory,
            [
                'status_label' => $statusLabel,
                'recipient_bucket' => 'booking_customer',
            ]
        );
        $this->notifyOperational($booking, OtaNotificationEvent::BookingStatusChanged, [
            'booking_reference' => $booking->reference_code,
            'status_label' => $statusLabel,
        ]);

        if ($this->isManualReviewStatus($booking, $statusLabel)) {
            $this->notifyManualReviewRequired($booking, null, $this->manualReviewReasonFromStatusLabel($statusLabel));
        }
    }

    public function notifyFareUpdateRequiresAcceptance(Booking $booking): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'agent.user']);
        if ($this->operationalNotificationAlreadyLogged($booking, OtaNotificationEvent::BookingFareUpdatedRequiresAcceptance->value)) {
            return;
        }

        $this->notifyOperational($booking, OtaNotificationEvent::BookingFareUpdatedRequiresAcceptance, [
            'message' => 'An airline fare update requires your confirmation before this booking can continue.',
        ]);
    }

    public function notifyUpdatedFareAccepted(Booking $booking): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'agent.user']);

        $this->notifyOperational($booking, OtaNotificationEvent::BookingUpdatedFareAccepted, [
            'message' => 'The updated fare was accepted. You may continue with this booking.',
        ]);
    }

    public function notifyManualReviewRequired(Booking $booking, ?User $actor = null, string $reason = 'staff_review'): void
    {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'agent.user']);

        if (! $this->customerEmailAlreadySent($booking, BookingCommunicationEvent::CustomerManualReviewRequired->value)) {
            $this->sendEmailForBooking(
                $booking,
                BookingCommunicationEvent::CustomerManualReviewRequired,
                fn (Booking $b): Mailable => new BookingUniversalNotification($this->bookingEmailPayloadFactory->customerManualReviewRequired($b)),
                [
                    'status_label' => 'manual_review',
                    'manual_review_reason' => $reason,
                    'recipient_bucket' => 'booking_customer',
                ]
            );
        }

        if (! $this->operationalNotificationAlreadyLogged($booking, OtaNotificationEvent::BookingManualReviewRequired->value, 'staff_review_required')) {
            $this->notifyOperational($booking, OtaNotificationEvent::BookingManualReviewRequired, [
                'reason' => $reason,
                'staff_review_reason' => $reason,
                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
                'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                'message' => 'This booking requires staff review before it can proceed.',
                'universal_email' => $this->bookingEmailPayloadFactory->staffReviewRequired($booking, $reason),
                'routing_note' => 'Internal staff review required alert; customer and B2B manual-review notifications are sent separately.',
            ], $actor, 'Staff review required: '.$booking->reference_code, 'Booking '.$booking->reference_code.' requires staff review before it can proceed.', [
                'notify_buckets' => ['assigned_staff', 'operations_queue', 'platform_admin'],
                'deduplicated_buckets' => [
                    'customer_party',
                    'booking_customer',
                    'booking_agent',
                    'agency_admin',
                    'agent_staff_creator',
                ],
            ]);
        }

        if (! $this->operationalNotificationAlreadyLogged($booking, OtaNotificationEvent::BookingManualReviewRequired->value, 'booking_manual_review_b2b')) {
            $this->notifyOperational($booking, OtaNotificationEvent::BookingManualReviewRequired, [
                'reason' => $reason,
                'message' => 'This agency booking requires review before it can proceed.',
                'universal_email' => $this->bookingEmailPayloadFactory->agencyManualReviewRequired($booking, $reason),
                'routing_note' => 'B2B manual-review notification; skipped safely when agent staff context is unavailable.',
            ], $actor, 'Booking review update: '.$booking->reference_code, 'Booking '.$booking->reference_code.' requires review before it can proceed.', [
                'notify_buckets' => ['booking_agent', 'agency_admin', 'agent_staff_creator'],
                'deduplicated_buckets' => ['customer_party', 'booking_customer'],
            ]);
        }
    }

    /**
     * Internal-only supplier failure alert for platform staff buckets.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function notifySupplierFailure(
        Booking $booking,
        string $failureType,
        ?User $actor = null,
        ?array $context = null,
    ): void {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'agent.user', 'assignedStaff']);
        $context = is_array($context) ? $context : [];
        $failureType = $this->normalizeSupplierFailureType($failureType, $context);
        $event = $this->supplierFailureEvent($failureType);

        if ($this->internalFailureNotificationAlreadyLogged($booking, $event->value, $failureType, $context)) {
            return;
        }

        $safeContext = $this->safeSupplierFailureContext($booking, $context);

        $this->notifyOperational($booking, $event, [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_code,
            'failure_type' => $failureType,
            'failure_reason' => $safeContext['failure_reason'] ?? null,
            'failure_classification' => $safeContext['failure_classification'] ?? null,
            'supplier_booking_status' => $safeContext['supplier_booking_status'] ?? null,
            'ticketing_status' => $safeContext['ticketing_status'] ?? null,
            'supplier_connection_id' => $safeContext['supplier_connection_id'] ?? null,
            'supplier_name' => $safeContext['supplier_name'] ?? null,
            'supplier_attempt_id' => $safeContext['supplier_attempt_id'] ?? null,
            'universal_email' => $this->bookingEmailPayloadFactory->supplierFailureAlert($booking, $failureType, $safeContext),
            'routing_note' => 'Internal supplier failure alert; customer and B2B paths are not used for raw supplier failure payloads.',
        ], $actor, $this->supplierFailureSubject($failureType, $booking), $this->supplierFailureFallbackBody($failureType, $booking), [
            'notify_buckets' => ['assigned_staff', 'operations_queue', 'platform_admin'],
            'deduplicated_buckets' => [
                'customer_party',
                'booking_customer',
                'booking_agent',
                'agency_admin',
                'agent_staff_creator',
            ],
        ]);

        if ($this->supplierFailureIndicatesConnectionAuthIssue($safeContext)) {
            $this->notifySupplierConnectionAuthFailure($booking, $safeContext, $actor);
        }
    }

    /**
     * Internal-only ticketing failure alert for platform staff buckets.
     *
     * @param  array<string, mixed>|null  $context
     */
    public function notifyTicketingFailure(
        Booking $booking,
        string $failureType,
        ?User $actor = null,
        ?array $context = null,
    ): void {
        $booking = $booking->fresh(['agency.agencySetting', 'contact', 'customer', 'agent.user', 'assignedStaff']);
        $context = is_array($context) ? $context : [];
        $failureType = $this->normalizeTicketingFailureType($failureType);
        $event = $this->ticketingFailureEvent($failureType);

        if ($this->internalFailureNotificationAlreadyLogged($booking, $event->value, $failureType, $context)) {
            return;
        }

        $safeContext = $this->safeTicketingFailureContext($booking, $context);
        $universalPayload = $failureType === OtaNotificationEvent::TicketingNotSupported->value
            ? $this->bookingEmailPayloadFactory->ticketingNotSupportedAlert($booking, $safeContext)
            : $this->bookingEmailPayloadFactory->ticketingFailureAlert($booking, $failureType, $safeContext);

        $this->notifyOperational($booking, $event, [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_code,
            'failure_type' => $failureType,
            'failure_reason' => $safeContext['failure_reason'] ?? null,
            'failure_classification' => $safeContext['failure_classification'] ?? null,
            'supplier_booking_status' => $safeContext['supplier_booking_status'] ?? null,
            'ticketing_status' => $safeContext['ticketing_status'] ?? null,
            'supplier_connection_id' => $safeContext['supplier_connection_id'] ?? null,
            'supplier_name' => $safeContext['supplier_name'] ?? null,
            'ticketing_attempt_id' => $safeContext['ticketing_attempt_id'] ?? null,
            'universal_email' => $universalPayload,
            'routing_note' => 'Internal ticketing failure alert; customer and B2B paths are not used for raw ticketing failure payloads.',
        ], $actor, $this->ticketingFailureSubject($failureType, $booking), $this->ticketingFailureFallbackBody($failureType, $booking), [
            'notify_buckets' => ['assigned_staff', 'operations_queue', 'platform_admin'],
            'deduplicated_buckets' => [
                'customer_party',
                'booking_customer',
                'booking_agent',
                'agency_admin',
                'agent_staff_creator',
            ],
        ]);

        if ($this->supplierFailureIndicatesConnectionAuthIssue($safeContext)) {
            $this->notifySupplierConnectionAuthFailure($booking, $safeContext, $actor);
        }
    }

    public function sendStaffAssigned(Booking $booking, ?User $assignee): void
    {
        $this->logSystemEvent($booking, BookingCommunicationEvent::StaffAssigned->value, [
            'assigned_staff_id' => $assignee?->id,
            'assigned_staff_name' => $assignee?->name,
        ]);
        $this->notifyOperational($booking, OtaNotificationEvent::BookingAssigned, [
            'booking_reference' => $booking->reference_code,
            'assigned_staff_id' => $assignee?->id,
            'assigned_staff_name' => $assignee?->name,
        ]);
    }

    /**
     * @param  callable(Booking): Mailable  $mailableFactory
     * @param  array<string, mixed>  $meta
     */
    protected function sendEmailForBooking(
        Booking $booking,
        BookingCommunicationEvent $event,
        callable $mailableFactory,
        array $meta = [],
    ): void {
        $settings = $this->agencyCommunicationSettingsService->getOrCreateSettings($booking->agency);
        $recipient = $this->resolveRecipient($booking);
        $renderedTemplate = $this->agencyCommunicationSettingsService->renderTemplate(
            $booking->agency,
            $event->value,
            'email',
            [
                'agency_name' => (string) ($booking->agency?->agencySetting?->display_name ?? $booking->agency?->name ?? config('app.name')),
                'booking_reference' => (string) $booking->reference_code,
                'passenger_name' => (string) ($recipient['name'] ?? 'Passenger'),
            ]
        );
        $log = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $recipient['user_id'],
            'channel' => 'email',
            'event' => $event->value,
            'recipient_name' => $recipient['name'],
            'recipient_email' => $recipient['email'],
            'recipient_phone' => $recipient['phone'],
            'status' => 'queued',
            'provider' => config('mail.default'),
            'meta' => array_merge($meta, [
                'used_template' => $renderedTemplate['used_template'],
                'notification_type' => $event->value,
                'recipient_type' => 'customer',
                'attempts' => 1,
                'universal_mailable' => true,
            ]),
        ]);

        if (! $settings->email_enabled) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'Email notifications are disabled for this agency.',
            ])->save();
            $this->logWhatsappFutureProviderEvent($booking, $event->value, $settings->notification_rules ?? []);

            return;
        }

        if ($renderedTemplate['used_template'] && ! $renderedTemplate['is_enabled']) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'Email template is disabled for this event.',
            ])->save();
            $this->logWhatsappFutureProviderEvent($booking, $event->value, $settings->notification_rules ?? []);

            return;
        }

        if ($recipient['email'] === null) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'Recipient email is missing.',
            ])->save();
            $this->logWhatsappFutureProviderEvent($booking, $event->value, $settings->notification_rules ?? []);

            return;
        }

        try {
            $mailable = $mailableFactory($booking);
            $subject = $mailable->envelope()->subject;

            if ($this->isImmediateMailer()) {
                Mail::to($recipient['email'])->send($mailable);
                $log->forceFill([
                    'status' => 'sent',
                    'subject' => $renderedTemplate['subject'] ?: $subject,
                    'message' => $renderedTemplate['body'] ?: null,
                    'sent_at' => now(),
                ])->save();
                $this->logWhatsappFutureProviderEvent($booking, $event->value, $settings->notification_rules ?? []);

                return;
            }

            Mail::to($recipient['email'])->queue($mailable);
            $log->forceFill([
                'status' => 'queued',
                'subject' => $renderedTemplate['subject'] ?: $subject,
                'message' => $renderedTemplate['body'] ?: null,
            ])->save();
            $this->logWhatsappFutureProviderEvent($booking, $event->value, $settings->notification_rules ?? []);
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();
            $this->logWhatsappFutureProviderEvent($booking, $event->value, $settings->notification_rules ?? []);
        }
    }

    protected function logWhatsappFutureProviderEvent(Booking $booking, string $event, array $rules): void
    {
        if (! (($rules['whatsapp'] ?? false) === true)) {
            return;
        }

        CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->customer_id,
            'channel' => 'whatsapp',
            'event' => $event,
            'status' => 'queued_for_future_provider',
            'provider' => 'not_configured_yet',
            'meta' => ['note' => 'WhatsApp sending is not enabled yet by product design.'],
        ]);
    }

    /**
     * @return array{name: string|null, email: string|null, phone: string|null, user_id: int|null}
     */
    protected function resolveRecipient(Booking $booking): array
    {
        $booking->loadMissing(['contact', 'customer']);

        return [
            'name' => $booking->contact?->meta['name']
                ?? $booking->customer?->name
                ?? trim((string) optional($booking->passengers->first())->first_name.' '.optional($booking->passengers->first())->last_name)
                ?: null,
            'email' => $booking->contact?->email ?? $booking->customer?->email,
            'phone' => $booking->contact?->phone,
            'user_id' => $booking->customer_id,
        ];
    }

    protected function isImmediateMailer(): bool
    {
        return in_array((string) config('mail.default'), ['log', 'array', 'local'], true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function notifyOperational(
        Booking $booking,
        OtaNotificationEvent $event,
        array $payload,
        ?User $actor = null,
        ?string $fallbackSubject = null,
        ?string $fallbackBody = null,
        array $recipientContext = [],
    ): void {
        $universalPayload = is_array($payload['universal_email'] ?? null)
            ? $payload['universal_email']
            : $this->operationalUniversalPayload($booking, $event);
        $safePayload = $this->operationalPayload($booking, array_merge($payload, [
            'universal_email' => $universalPayload,
        ]));

        try {
            $this->otaNotificationService->send(
                agency: $booking->agency()->firstOrFail(),
                eventKey: $event->value,
                booking: $booking,
                actor: $actor ?? $booking->customer,
                payload: $safePayload,
                fallbackSubject: $fallbackSubject ?? ('OTA Notification: '.str_replace('_', ' ', $event->value)),
                fallbackBody: $fallbackBody ?? ('A new '.$event->value.' event was recorded for booking '.$booking->reference_code.'.'),
                templateVariables: $this->operationalTemplateVariables($booking, $event, $universalPayload, $safePayload),
                recipientContext: $this->bookingRecipientContext($booking, $actor, $recipientContext),
            );
        } catch (Throwable $e) {
            Log::warning('booking.operational_notification_failed', [
                'booking_id' => $booking->id,
                'booking_reference' => (string) ($booking->reference_code ?? ''),
                'event' => $event->value,
                'class' => self::class,
                'method' => 'notifyOperational',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $universalPayload
     * @param  array<string, mixed>  $safePayload
     * @return array<string, scalar|null>
     */
    protected function operationalTemplateVariables(
        Booking $booking,
        OtaNotificationEvent $event,
        array $universalPayload,
        array $safePayload,
    ): array {
        $scalarPayload = array_filter(
            $safePayload,
            fn ($value): bool => is_scalar($value) || $value === null,
        );

        return array_merge([
            'booking_reference' => (string) $booking->reference_code,
            'notification_type' => $universalPayload['type'] ?? $event->value,
            'booking_status' => $booking->status->value,
            'status' => $booking->status->value,
            'currency' => (string) ($booking->currency ?? $booking->fareBreakdown?->currency ?? 'PKR'),
            'customer_phone' => (string) ($booking->contact?->phone ?? ''),
            'passenger_summary' => $this->passengerSummary($booking),
            'admin_booking_url' => $this->safeAdminBookingUrl($booking),
        ], $this->flattenUniversalTemplateVariables($universalPayload), $scalarPayload);
    }

    protected function operationalUniversalPayload(Booking $booking, OtaNotificationEvent $event): array
    {
        return match ($event) {
            OtaNotificationEvent::BookingRequestReceived => $this->bookingEmailPayloadFactory->adminNewBookingAlert($booking),
            OtaNotificationEvent::SupplierBookingCreated => $this->bookingEmailPayloadFactory->pnrCreated($booking),
            OtaNotificationEvent::TicketIssued => $this->bookingEmailPayloadFactory->ticketIssued($booking),
            OtaNotificationEvent::BookingManualReviewRequired => $this->bookingEmailPayloadFactory->staffReviewRequired($booking),
            default => $this->bookingEmailPayloadFactory->adminNewBookingAlert($booking),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, scalar|null>
     */
    protected function flattenUniversalTemplateVariables(array $payload): array
    {
        $booking = is_array($payload['booking'] ?? null) ? $payload['booking'] : [];
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $selectedFareFamily = is_array($payload['selected_fare_family'] ?? null) ? $payload['selected_fare_family'] : [];

        $staffReviewReason = $payload['staff_review_reason'] ?? null;

        return [
            'notification_title' => $payload['title'] ?? null,
            'notification_status' => $payload['status_label'] ?? null,
            'customer_name' => $contact['name'] ?? $payload['greeting_name'] ?? null,
            'passenger_name' => $contact['name'] ?? $payload['greeting_name'] ?? null,
            'route' => $booking['route'] ?? null,
            'trip_type' => $booking['trip_type'] ?? null,
            'travel_date' => $booking['travel_date'] ?? null,
            'current_status' => $booking['current_status'] ?? null,
            'pnr' => $booking['pnr'] ?? null,
            'amount' => $payment['estimated_selected_fare'] ?? $payment['total'] ?? $payment['payment_amount'] ?? null,
            'estimated_selected_fare' => $payment['estimated_selected_fare'] ?? $selectedFareFamily['estimated_fare_display'] ?? null,
            'selected_fare_family_label' => $selectedFareFamily['fare_family_label'] ?? null,
            'payment_status' => $payment['payment_status_label'] ?? $payment['status'] ?? null,
            'review_reason' => is_scalar($staffReviewReason) ? (string) $staffReviewReason : null,
            'staff_review_reason' => is_scalar($staffReviewReason) ? (string) $staffReviewReason : null,
            'supplier_status' => is_scalar($payload['supplier_booking_status'] ?? null)
                ? (string) $payload['supplier_booking_status']
                : null,
            'supplier_booking_status' => is_scalar($payload['supplier_booking_status'] ?? null)
                ? (string) $payload['supplier_booking_status']
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function operationalPayload(Booking $booking, array $payload): array
    {
        return array_merge([
            'booking_reference' => $booking->reference_code,
        ], $payload);
    }

    protected function bookingRecipientContext(Booking $booking, ?User $actor, array $context): array
    {
        $context['booking_creator_user_id'] ??= $this->resolveBookingCreatorUserId($booking, $actor);
        $context['booking_creator_role'] ??= $this->resolveBookingCreatorRole($booking, $actor);
        $context['booking_creator_source'] ??= $this->resolveBookingCreatorSource($booking, $actor);

        if ($actor?->isAgentStaff()) {
            $context['agent_staff_creator_email'] ??= $actor->email;
            $context['agent_staff_creator_user_id'] ??= $actor->id;
            $context['agent_staff_creator_source'] ??= 'direct_actor';

            return $context;
        }

        $creatorContext = $this->bookingCreatorContext($booking);
        $storedAgentStaffCreatorId = (int) ($creatorContext['agent_staff_creator_user_id'] ?? 0);
        if ($storedAgentStaffCreatorId > 0) {
            $context['agent_staff_creator_user_id'] ??= $storedAgentStaffCreatorId;
            $context['agent_staff_creator_source'] ??= 'booking_creator_context';
        } elseif (in_array('agent_staff_creator', $context['notify_buckets'] ?? [], true)) {
            $context['agent_staff_creator_source'] ??= 'missing';
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingCreatorContext(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = $meta['creator_context'] ?? [];

        return is_array($context) ? $context : [];
    }

    protected function bookingCreatedByAgencySideActor(Booking $booking, ?User $actor): bool
    {
        if ($actor !== null && $this->isAgencySideBookingActor($actor)) {
            return true;
        }

        $role = $this->bookingCreatorRole($booking);

        return $role !== null && in_array($role, [
            AccountType::Agent->value,
            AccountType::AgentStaff->value,
            AccountType::AgencyAdmin->value,
        ], true);
    }

    protected function bookingCreatedByAgentStaff(Booking $booking, ?User $actor): bool
    {
        if ($actor?->isAgentStaff()) {
            return true;
        }

        if ($this->bookingCreatorRole($booking) === AccountType::AgentStaff->value) {
            return true;
        }

        return (int) ($this->bookingCreatorContext($booking)['agent_staff_creator_user_id'] ?? 0) > 0;
    }

    protected function bookingCreatedByAgentOrAgencyAdmin(Booking $booking, ?User $actor): bool
    {
        if ($actor !== null && ($actor->isAgent() || $actor->isAgencyAdmin())) {
            return true;
        }

        $role = $this->bookingCreatorRole($booking);

        return in_array($role, [
            AccountType::Agent->value,
            AccountType::AgencyAdmin->value,
        ], true);
    }

    /**
     * @return list<string>
     */
    protected function bookingCreatedB2bRecipientBuckets(Booking $booking, ?User $actor): array
    {
        $buckets = ['booking_agent', 'agency_admin'];

        if ($this->bookingCreatedByAgentStaff($booking, $actor)) {
            $buckets[] = 'agent_staff_creator';
        }

        return $buckets;
    }

    protected function isAgencySideBookingActor(User $actor): bool
    {
        return $actor->isAgent() || $actor->isAgentStaff() || $actor->isAgencyAdmin();
    }

    protected function resolveBookingCreatorUserId(Booking $booking, ?User $actor): ?int
    {
        if ($actor !== null && $this->isAgencySideBookingActor($actor)) {
            return $actor->id;
        }

        return $this->bookingCreatorUserId($booking);
    }

    protected function resolveBookingCreatorRole(Booking $booking, ?User $actor): ?string
    {
        if ($actor !== null && $this->isAgencySideBookingActor($actor)) {
            return $actor->account_type instanceof AccountType
                ? $actor->account_type->value
                : (string) $actor->account_type;
        }

        return $this->bookingCreatorRole($booking);
    }

    protected function resolveBookingCreatorSource(Booking $booking, ?User $actor): ?string
    {
        if ($actor !== null && $this->isAgencySideBookingActor($actor)) {
            return 'direct_actor';
        }

        $source = $this->bookingCreatorContext($booking)['creator_source'] ?? null;

        return is_string($source) && trim($source) !== '' ? $source : null;
    }

    protected function bookingCreatorUserId(Booking $booking): ?int
    {
        $value = $this->bookingCreatorContext($booking)['creator_user_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    protected function bookingCreatorRole(Booking $booking): ?string
    {
        $value = $this->bookingCreatorContext($booking)['creator_role'] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    protected function operationalNotificationAlreadyLogged(Booking $booking, string $eventKey, ?string $notificationType = null): bool
    {
        $query = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', $eventKey)
            ->whereIn('status', ['queued', 'sent', 'sending']);

        if ($notificationType !== null) {
            $query->where('meta->notification_type', $notificationType);
        }

        return $query->exists();
    }

    protected function bookingHasAgencyPaymentB2bContext(Booking $booking, ?User $actor = null): bool
    {
        if ($this->bookingCreatedByAgencySideActor($booking, $actor)) {
            return true;
        }

        $booking->loadMissing('agent');

        return (int) ($booking->agent_id ?? 0) > 0;
    }

    /**
     * @return list<string>
     */
    protected function paymentB2bRecipientBuckets(Booking $booking, ?User $actor = null): array
    {
        $buckets = ['booking_agent', 'agency_admin'];

        if ($this->bookingCreatedByAgentStaff($booking, $actor)) {
            $buckets[] = 'agent_staff_creator';
        }

        return $buckets;
    }

    protected function paymentB2bNotificationAlreadyLogged(
        Booking $booking,
        string $eventKey,
        string $notificationType,
        int $bookingPaymentId,
    ): bool {
        if ($bookingPaymentId <= 0) {
            return false;
        }

        return CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', $eventKey)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->where('meta->notification_type', $notificationType)
            ->where('meta->payload->booking_payment_id', $bookingPaymentId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $universalPayload
     */
    protected function notifyAgencyPaymentB2b(
        Booking $booking,
        BookingPayment $payment,
        OtaNotificationEvent $event,
        string $notificationType,
        array $universalPayload,
        string $paymentEvent,
        ?User $actor = null,
    ): void {
        if (! $this->bookingHasAgencyPaymentB2bContext($booking, $actor)) {
            return;
        }

        if ($this->paymentB2bNotificationAlreadyLogged($booking, $event->value, $notificationType, $payment->id)) {
            return;
        }

        $this->notifyOperational($booking, $event, [
            'booking_reference' => $booking->reference_code,
            'booking_id' => $booking->id,
            'booking_payment_id' => $payment->id,
            'payment_event' => $paymentEvent,
            'payment_status' => $payment->status->value,
            'amount' => (float) $payment->amount,
            'universal_email' => $universalPayload,
            'routing_note' => 'B2B agency payment notification; customer payment emails remain on separate direct path.',
        ], $actor, 'Agency payment update: '.$booking->reference_code, 'There is a payment update for agency booking '.$booking->reference_code.'.', [
            'notify_buckets' => $this->paymentB2bRecipientBuckets($booking, $actor),
            'deduplicated_buckets' => ['customer_party', 'booking_customer'],
        ]);
    }

    protected function customerEmailAlreadySent(Booking $booking, string $eventKey): bool
    {
        return CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('channel', 'email')
            ->where('event', $eventKey)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->exists();
    }

    protected function isManualReviewStatus(Booking $booking, string $statusLabel): bool
    {
        $label = strtolower(str_replace([' ', '-'], '_', trim($statusLabel)));

        return $booking->status->value === 'fare_review'
            || in_array($label, [
                'needs_review',
                'staff_review_required',
                'certified_route_not_available',
                'manual_review',
                'supplier_manual_review',
            ], true);
    }

    protected function manualReviewReasonFromStatusLabel(string $statusLabel): string
    {
        $label = strtolower(str_replace([' ', '-'], '_', trim($statusLabel)));

        return $label !== '' ? $label : 'staff_review';
    }

    protected function itineraryEmailAlreadySentForDocument(Booking $booking, int $documentId): bool
    {
        return CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('channel', 'email')
            ->where('event', 'itinerary_ready')
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->where('meta->document_id', $documentId)
            ->exists();
    }

    protected function dispatchItineraryReadyEmail(
        Booking $booking,
        string $attachmentPath,
        ?string $note,
        ?BookingDocument $document,
        ?User $actor,
    ): bool {
        $settings = $this->agencyCommunicationSettingsService->getOrCreateSettings($booking->agency);
        $recipient = $this->resolveRecipient($booking);
        $log = CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $recipient['user_id'],
            'channel' => 'email',
            'event' => 'itinerary_ready',
            'recipient_name' => $recipient['name'],
            'recipient_email' => $recipient['email'],
            'recipient_phone' => $recipient['phone'],
            'status' => 'queued',
            'provider' => config('mail.default'),
            'meta' => [
                'document_id' => $document?->id,
                'has_attachment' => true,
                'triggered_by_user_id' => $actor?->id,
                'staff_note' => $note !== null && $note !== '' ? true : false,
                'notification_type' => 'itinerary_ready',
                'recipient_type' => 'customer',
                'attempts' => 1,
                'universal_mailable' => true,
            ],
        ]);

        if (! $settings->email_enabled) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'Email notifications are disabled for this agency.',
            ])->save();

            return false;
        }

        if ($recipient['email'] === null) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'Recipient email is missing.',
            ])->save();

            return false;
        }

        try {
            $mailable = new BookingUniversalNotification(
                $this->bookingEmailPayloadFactory->itineraryReady($booking, $note, true),
                [
                    'disk' => 'local',
                    'path' => $attachmentPath,
                    'name' => 'ticket-itinerary-'.$booking->reference_code.'.pdf',
                ]
            );
            $subject = $mailable->envelope()->subject;

            if ($this->isImmediateMailer()) {
                Mail::to($recipient['email'])->send($mailable);
                $log->forceFill([
                    'status' => 'sent',
                    'subject' => $subject,
                    'sent_at' => now(),
                ])->save();

                return true;
            }

            Mail::to($recipient['email'])->queue($mailable);
            $log->forceFill([
                'status' => 'queued',
                'subject' => $subject,
            ])->save();

            return true;
        } catch (Throwable $e) {
            $log->forceFill([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ])->save();

            return false;
        }
    }

    protected function resolveLatestItineraryDocument(Booking $booking): ?BookingDocument
    {
        $booking->loadMissing('documents');

        return $booking->documents
            ->filter(fn (BookingDocument $doc): bool => $doc->document_type === BookingDocumentType::TicketItinerary
                && $doc->status === BookingDocumentStatus::Generated)
            ->sortByDesc('id')
            ->first();
    }

    protected function resolveItineraryAttachmentPath(?BookingDocument $document): ?string
    {
        if ($document === null) {
            return null;
        }

        $path = trim((string) ($document->file_path ?? ''));
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return $path;
    }

    protected function contactDisplayName(Booking $booking): string
    {
        $booking->loadMissing(['contact', 'customer', 'passengers']);

        return (string) ($booking->contact?->meta['name']
            ?? $booking->customer?->name
            ?? trim((string) optional($booking->passengers->first())->first_name.' '.optional($booking->passengers->first())->last_name)
            ?: 'Customer');
    }

    protected function passengerSummary(Booking $booking): string
    {
        $booking->loadMissing('passengers');
        $lines = $booking->passengers->take(5)->map(function ($p): string {
            return trim((string) ($p->title ?? '').' '.(string) $p->first_name.' '.(string) $p->last_name);
        })->filter()->values();

        if ($lines->isEmpty()) {
            return '';
        }

        $summary = $lines->implode('; ');
        if ($booking->passengers->count() > 5) {
            $summary .= '; …';
        }

        return $summary;
    }

    protected function safeAdminBookingUrl(Booking $booking): ?string
    {
        if (! Route::has('admin.bookings.show')) {
            return null;
        }

        try {
            return route('admin.bookings.show', $booking, absolute: true);
        } catch (Throwable) {
            return null;
        }
    }

    protected function buildAdminNewBookingFallbackBody(Booking $booking, ?string $adminUrl): string
    {
        $amountContext = $this->bookingEmailAmountContext($booking);
        if ($amountContext['estimated_selected_fare'] !== null) {
            $amountLine = '';
            if ($amountContext['selected_fare_family_label'] !== null) {
                $amountLine .= 'Selected fare family: '.$amountContext['selected_fare_family_label']."\n";
            }
            $amountLine .= 'Estimated selected fare: '.$amountContext['estimated_selected_fare']."\n";
            if ($amountContext['base_fare_total'] !== null) {
                $amountLine .= 'Base fare (search): '.$amountContext['currency'].' '.number_format($amountContext['base_fare_total'], 2)."\n";
            }
        } elseif ($amountContext['amount'] !== null) {
            $amountLine = 'Amount: '.$amountContext['currency'].' '.number_format((float) $amountContext['amount'], 2)."\n";
        } else {
            $amountLine = '';
        }

        $body = "New booking request received.\n\n"
            .'Reference: '.$booking->reference_code."\n"
            .'Customer: '.$this->contactDisplayName($booking)."\n"
            .'Email: '.($booking->contact?->email ?? $booking->customer?->email ?? 'N/A')."\n"
            .'Phone: '.($booking->contact?->phone ?? 'N/A')."\n"
            .'Route: '.($booking->route ?? 'N/A')."\n"
            .'Travel date: '.(optional($booking->travel_date)->toDateString() ?? 'N/A')."\n"
            .$amountLine
            .'Status: '.$booking->status->value."\n"
            .'Passengers: '.$this->passengerSummary($booking)."\n";

        if ($adminUrl !== null) {
            $body .= "\nOpen booking: ".$adminUrl."\n";
        }

        return $body;
    }

    /**
     * @return array{
     *     amount: float|null,
     *     currency: string,
     *     estimated_selected_fare: string|null,
     *     selected_fare_family_label: string|null,
     *     base_fare_total: float|null
     * }
     */
    protected function bookingEmailAmountContext(Booking $booking): array
    {
        $fare = $booking->fareBreakdown;
        $currency = (string) ($booking->currency ?? 'PKR');
        $baseTotal = $fare !== null ? (float) ($fare->total ?? 0) : 0.0;
        $section = $this->bookingEmailPayloadFactory->selectedFareFamilySection($booking);

        if ($section !== null) {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $intent = is_array($meta['selected_fare_family_option'] ?? null)
                ? $meta['selected_fare_family_option']
                : [];
            $displayedPrice = isset($intent['displayed_price']) && is_numeric($intent['displayed_price'])
                ? (float) $intent['displayed_price']
                : null;

            return [
                'amount' => $displayedPrice,
                'currency' => $currency,
                'estimated_selected_fare' => isset($section['estimated_fare_display'])
                    ? (string) $section['estimated_fare_display']
                    : null,
                'selected_fare_family_label' => isset($section['fare_family_label'])
                    ? (string) $section['fare_family_label']
                    : null,
                'base_fare_total' => $baseTotal > 0 ? $baseTotal : null,
            ];
        }

        return [
            'amount' => $baseTotal > 0 ? $baseTotal : null,
            'currency' => $currency,
            'estimated_selected_fare' => null,
            'selected_fare_family_label' => null,
            'base_fare_total' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    /**
     * @param  array<string, mixed>  $safeContext
     */
    protected function notifySupplierConnectionAuthFailure(
        Booking $booking,
        array $safeContext,
        ?User $actor = null,
    ): void {
        $connectionId = (int) ($safeContext['supplier_connection_id'] ?? 0);

        if ($this->supplierConnectionAuthFailureRecentlyNotified($booking, $connectionId)) {
            return;
        }

        $this->notifyOperational($booking, OtaNotificationEvent::SupplierBookingFailed, [
            'booking_id' => $booking->id,
            'booking_reference' => $booking->reference_code,
            'failure_type' => self::SUPPLIER_CONNECTION_AUTH_NOTIFICATION_TYPE,
            'failure_reason' => $safeContext['failure_reason'] ?? null,
            'failure_classification' => $safeContext['failure_classification'] ?? null,
            'supplier_booking_status' => $safeContext['supplier_booking_status'] ?? null,
            'ticketing_status' => $safeContext['ticketing_status'] ?? null,
            'supplier_connection_id' => $connectionId > 0 ? $connectionId : null,
            'supplier_name' => $safeContext['supplier_name'] ?? null,
            'supplier_attempt_id' => $safeContext['supplier_attempt_id'] ?? null,
            'ticketing_attempt_id' => $safeContext['ticketing_attempt_id'] ?? null,
            'universal_email' => $this->bookingEmailPayloadFactory->supplierConnectionAuthFailureAlert($booking, $safeContext),
            'routing_note' => 'Platform-admin-only supplier credential/auth/link alert; separate from generic S3 supplier failure routing.',
        ], $actor, 'Supplier connection credential/auth alert: '.$booking->reference_code, 'A supplier connection credential, authentication, or link failure signal requires platform-admin review for booking '.$booking->reference_code.'.', [
            'notify_buckets' => ['platform_admin'],
            'deduplicated_buckets' => [
                'customer_party',
                'booking_customer',
                'booking_agent',
                'agency_admin',
                'agent_staff_creator',
                'assigned_staff',
                'operations_queue',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $safeContext
     */
    protected function supplierFailureIndicatesConnectionAuthIssue(array $safeContext): bool
    {
        $errorCode = strtolower(trim((string) ($safeContext['error_code'] ?? '')));
        foreach (self::SUPPLIER_CONNECTION_AUTH_ERROR_CODES as $code) {
            if ($errorCode === $code || ($errorCode !== '' && str_contains($errorCode, $code))) {
                return true;
            }
        }

        foreach ([
            (string) ($safeContext['failure_classification'] ?? ''),
            (string) ($safeContext['host_classification_reason'] ?? ''),
        ] as $classification) {
            if (strtolower(trim($classification)) === SabreHostErrorClassifier::REASON_ENTITLEMENT_OR_SECURITY) {
                return true;
            }
        }

        return false;
    }

    protected function supplierConnectionAuthFailureRecentlyNotified(Booking $booking, int $connectionId): bool
    {
        $query = CommunicationLog::query()
            ->where('agency_id', $booking->agency_id)
            ->where('meta->notification_type', self::SUPPLIER_CONNECTION_AUTH_NOTIFICATION_TYPE)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->where('created_at', '>=', now()->subMinutes(self::SUPPLIER_CONNECTION_AUTH_ALERT_COOLDOWN_MINUTES));

        if ($connectionId > 0) {
            $query->where('meta->payload->supplier_connection_id', $connectionId);
        } else {
            $query->where('booking_id', $booking->id);
        }

        return $query->exists();
    }

    protected function internalFailureNotificationAlreadyLogged(
        Booking $booking,
        string $eventKey,
        string $notificationType,
        array $context = [],
    ): bool {
        $query = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', $eventKey)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->where('meta->notification_type', $notificationType);

        $supplierAttemptId = (int) ($context['supplier_attempt_id'] ?? 0);
        if ($supplierAttemptId > 0) {
            $query->where('meta->payload->supplier_attempt_id', $supplierAttemptId);
        }

        $ticketingAttemptId = (int) ($context['ticketing_attempt_id'] ?? 0);
        if ($ticketingAttemptId > 0) {
            $query->where('meta->payload->ticketing_attempt_id', $ticketingAttemptId);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function normalizeSupplierFailureType(string $failureType, array $context): string
    {
        $failureType = strtolower(trim($failureType));
        if ($failureType !== OtaNotificationEvent::SupplierBookingFailed->value) {
            return $failureType;
        }

        $errorCode = strtolower(trim((string) ($context['error_code'] ?? '')));

        return match (true) {
            str_contains($errorCode, 'readiness'),
            str_contains($errorCode, 'revalidation'),
            str_contains($errorCode, 'payload_validation') => OtaNotificationEvent::SupplierReadinessFailed->value,
            str_contains($errorCode, 'search') => OtaNotificationEvent::SupplierSearchFailed->value,
            str_contains($errorCode, 'order'),
            str_contains($errorCode, 'trip_orders') => OtaNotificationEvent::SupplierOrderFailed->value,
            default => OtaNotificationEvent::SupplierBookingFailed->value,
        };
    }

    protected function normalizeTicketingFailureType(string $failureType): string
    {
        $failureType = strtolower(trim($failureType));

        return in_array($failureType, [
            OtaNotificationEvent::TicketingFailed->value,
            OtaNotificationEvent::TicketingNotSupported->value,
        ], true)
            ? $failureType
            : OtaNotificationEvent::TicketingFailed->value;
    }

    protected function supplierFailureEvent(string $failureType): OtaNotificationEvent
    {
        return match ($failureType) {
            OtaNotificationEvent::SupplierReadinessFailed->value => OtaNotificationEvent::SupplierReadinessFailed,
            OtaNotificationEvent::SupplierSearchFailed->value => OtaNotificationEvent::SupplierSearchFailed,
            OtaNotificationEvent::SupplierOrderFailed->value => OtaNotificationEvent::SupplierOrderFailed,
            default => OtaNotificationEvent::SupplierBookingFailed,
        };
    }

    protected function ticketingFailureEvent(string $failureType): OtaNotificationEvent
    {
        return $failureType === OtaNotificationEvent::TicketingNotSupported->value
            ? OtaNotificationEvent::TicketingNotSupported
            : OtaNotificationEvent::TicketingFailed;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function safeSupplierFailureContext(Booking $booking, array $context): array
    {
        $hostClassification = $this->bookingHostClassification($booking);

        return [
            'failure_reason' => SensitiveDataRedactor::sanitizeErrorMessage(
                is_string($context['failure_reason'] ?? $context['error_message'] ?? null)
                    ? (string) ($context['failure_reason'] ?? $context['error_message'])
                    : null
            ),
            'failure_classification' => $this->safeFailureClassificationValue($context, $hostClassification),
            'host_classification_reason' => $hostClassification['safe_reason_code'] ?? null,
            'host_classification_summary' => $hostClassification['safe_summary'] ?? null,
            'error_code' => isset($context['error_code']) ? (string) $context['error_code'] : null,
            'supplier_booking_status' => (string) ($context['supplier_booking_status'] ?? $booking->supplier_booking_status ?? ''),
            'ticketing_status' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? ''),
            'supplier_connection_id' => isset($context['supplier_connection_id']) ? (int) $context['supplier_connection_id'] : null,
            'supplier_name' => trim((string) ($context['provider'] ?? $context['supplier_name'] ?? $booking->supplier ?? '')),
            'supplier_attempt_id' => isset($context['supplier_attempt_id']) ? (int) $context['supplier_attempt_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function safeTicketingFailureContext(Booking $booking, array $context): array
    {
        $hostClassification = $this->bookingHostClassification($booking);

        return [
            'failure_reason' => SensitiveDataRedactor::sanitizeErrorMessage(
                is_string($context['failure_reason'] ?? $context['error_message'] ?? null)
                    ? (string) ($context['failure_reason'] ?? $context['error_message'])
                    : null
            ),
            'failure_classification' => $this->safeFailureClassificationValue($context, $hostClassification),
            'host_classification_reason' => $hostClassification['safe_reason_code'] ?? null,
            'host_classification_summary' => $hostClassification['safe_summary'] ?? null,
            'error_code' => isset($context['error_code']) ? (string) $context['error_code'] : null,
            'supplier_booking_status' => (string) ($context['supplier_booking_status'] ?? $booking->supplier_booking_status ?? ''),
            'ticketing_status' => (string) ($context['ticketing_status'] ?? $booking->ticketing_status ?? ''),
            'supplier_connection_id' => isset($context['supplier_connection_id']) ? (int) $context['supplier_connection_id'] : null,
            'supplier_name' => trim((string) ($context['provider'] ?? $context['supplier_name'] ?? $booking->supplier ?? '')),
            'ticketing_attempt_id' => isset($context['ticketing_attempt_id']) ? (int) $context['ticketing_attempt_id'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $hostClassification
     */
    protected function safeFailureClassificationValue(array $context, array $hostClassification): ?string
    {
        $explicit = trim((string) ($context['failure_classification'] ?? ''));
        if ($explicit !== '') {
            return Str::limit($explicit, 120);
        }

        $hostReason = trim((string) ($hostClassification['safe_reason_code'] ?? ''));
        if ($hostReason !== '') {
            return Str::limit($hostReason, 120);
        }

        $errorCode = trim((string) ($context['error_code'] ?? ''));

        return $errorCode !== '' ? Str::limit($errorCode, 120) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function bookingHostClassification(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $outcome = $meta['sabre_checkout_outcome'] ?? null;
        if (! is_array($outcome)) {
            return [];
        }

        $classification = $outcome['sabre_host_classification'] ?? null;

        return is_array($classification) ? $classification : [];
    }

    protected function supplierFailureSubject(string $failureType, Booking $booking): string
    {
        return match ($failureType) {
            OtaNotificationEvent::SupplierReadinessFailed->value => 'Supplier readiness failed: '.$booking->reference_code,
            OtaNotificationEvent::SupplierSearchFailed->value => 'Supplier search failed: '.$booking->reference_code,
            OtaNotificationEvent::SupplierOrderFailed->value => 'Supplier order failed: '.$booking->reference_code,
            default => 'Supplier booking failed: '.$booking->reference_code,
        };
    }

    protected function supplierFailureFallbackBody(string $failureType, Booking $booking): string
    {
        return match ($failureType) {
            OtaNotificationEvent::SupplierReadinessFailed->value => 'Supplier readiness failed for booking '.$booking->reference_code.'.',
            OtaNotificationEvent::SupplierSearchFailed->value => 'Supplier search failed for booking '.$booking->reference_code.'.',
            OtaNotificationEvent::SupplierOrderFailed->value => 'Supplier order failed for booking '.$booking->reference_code.'.',
            default => 'Supplier booking failed for booking '.$booking->reference_code.'.',
        };
    }

    protected function ticketingFailureSubject(string $failureType, Booking $booking): string
    {
        return $failureType === OtaNotificationEvent::TicketingNotSupported->value
            ? 'Ticketing not supported: '.$booking->reference_code
            : 'Ticketing failed: '.$booking->reference_code;
    }

    protected function ticketingFailureFallbackBody(string $failureType, Booking $booking): string
    {
        return $failureType === OtaNotificationEvent::TicketingNotSupported->value
            ? 'Automated ticketing is not supported for booking '.$booking->reference_code.'.'
            : 'Ticketing failed for booking '.$booking->reference_code.'.';
    }
}
