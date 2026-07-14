<?php

namespace App\Services\Bookings;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingCancellationType;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\CommunicationLog;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Communication\BookingEmailPayloadFactory;
use App\Services\Communication\OtaNotificationService;
use App\Services\Suppliers\AirBlue\AirBlueCancelService;
use App\Services\Suppliers\Iati\IatiCancelService;
use App\Services\Suppliers\PiaNdc\PiaNdcCancelService;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelReadiness;
use App\Services\Suppliers\Sabre\Cancel\SabreGdsCancelService;
use App\Services\Suppliers\Sabre\SabreBookingCancelService;
use App\Services\Suppliers\Sabre\SabreBookingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BookingCancellationService
{
    public function __construct(
        protected OtaNotificationService $otaNotificationService,
        protected SabreBookingService $sabreBookingService,
        protected BookingEmailPayloadFactory $bookingEmailPayloadFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestCancellation(Booking $booking, ?User $actor, array $data): BookingCancellationRequest
    {
        return DB::transaction(function () use ($booking, $actor, $data): BookingCancellationRequest {
            $request = BookingCancellationRequest::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'requested_by' => $actor?->id,
                'request_source' => (string) ($data['request_source'] ?? ($actor === null ? 'guest' : $actor->account_type?->value ?? 'user')),
                'reason' => $data['reason'] ?? null,
                'status' => BookingCancellationStatus::Requested,
                'cancellation_type' => $data['cancellation_type'] ?? BookingCancellationType::BookingCancel->value,
                'meta' => $data['meta'] ?? null,
            ]);

            $booking->forceFill(['cancellation_status' => BookingCancellationStatus::Requested->value])->save();

            $this->writeAudit($booking, $actor, 'booking.cancellation_requested', [
                'cancellation_request_id' => $request->id,
                'request_source' => $request->request_source,
            ]);

            $this->writeCommunication($booking, $actor, 'cancellation_requested', [
                'cancellation_request_id' => $request->id,
                'request_source' => $request->request_source,
            ]);

            $this->notifyCancellation($booking, OtaNotificationEvent::CancellationRequested, [
                'cancellation_request_id' => $request->id,
                'request_source' => $request->request_source,
            ], $actor);

            return $request;
        });
    }

    public function approveCancellation(BookingCancellationRequest $request, User $actor): BookingCancellationRequest
    {
        return DB::transaction(function () use ($request, $actor): BookingCancellationRequest {
            if ($request->status !== BookingCancellationStatus::Requested) {
                throw new InvalidArgumentException('Only requested cancellations can be approved.');
            }

            $request->forceFill([
                'status' => BookingCancellationStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            $request->booking->forceFill(['cancellation_status' => BookingCancellationStatus::Approved->value])->save();

            $this->writeAudit($request->booking, $actor, 'booking.cancellation_approved', [
                'cancellation_request_id' => $request->id,
            ]);

            $this->notifyCancellation($request->booking, OtaNotificationEvent::CancellationStatusChanged, [
                'cancellation_request_id' => $request->id,
                'status' => 'approved',
            ], $actor);

            return $request->fresh();
        });
    }

    public function rejectCancellation(BookingCancellationRequest $request, User $actor, string $reason): BookingCancellationRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): BookingCancellationRequest {
            if (! in_array($request->status, [BookingCancellationStatus::Requested, BookingCancellationStatus::Approved], true)) {
                throw new InvalidArgumentException('Only requested/approved cancellations can be rejected.');
            }

            $request->forceFill([
                'status' => BookingCancellationStatus::Rejected,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $request->booking->forceFill(['cancellation_status' => BookingCancellationStatus::Rejected->value])->save();

            $this->writeAudit($request->booking, $actor, 'booking.cancellation_rejected', [
                'cancellation_request_id' => $request->id,
                'reason' => $reason,
            ]);

            $this->notifyCancellation($request->booking, OtaNotificationEvent::CancellationStatusChanged, [
                'cancellation_request_id' => $request->id,
                'status' => 'rejected',
            ], $actor);

            return $request->fresh();
        });
    }

    public function processCancellation(
        BookingCancellationRequest $request,
        User $actor,
        bool $adminStaffSupplierExecution = false,
        ?string $actorContextOverride = null,
    ): BookingCancellationRequest {
        return DB::transaction(function () use ($request, $actor, $adminStaffSupplierExecution, $actorContextOverride): BookingCancellationRequest {
            if (! in_array($request->status, [BookingCancellationStatus::Approved, BookingCancellationStatus::Requested], true)) {
                throw new InvalidArgumentException('Only requested/approved cancellations can be processed.');
            }

            $booking = $request->booking()->lockForUpdate()->firstOrFail();
            $isTicketed = $booking->tickets()->exists() || $booking->status === BookingStatus::Ticketed;

            $requestMeta = is_array($request->meta) ? $request->meta : [];
            $bookingMeta = is_array($booking->meta) ? $booking->meta : [];

            $sabreCancelOutcome = null;
            $supplierProvider = strtolower(trim((string) (data_get($bookingMeta, 'supplier_provider') ?? $booking->supplier ?? '')));
            $isSabreBooking = $supplierProvider === SupplierProvider::Sabre->value;
            $isIatiBooking = $supplierProvider === SupplierProvider::Iati->value;
            $isPiaNdcBooking = $supplierProvider === SupplierProvider::PiaNdc->value;
            $isAirBlueBooking = $supplierProvider === SupplierProvider::Airblue->value;
            $hasPnr = trim((string) ($booking->pnr ?? $booking->supplier_reference ?? '')) !== '';

            if ($isSabreBooking) {
                if ($request->status !== BookingCancellationStatus::Approved) {
                    return $this->storeSabreBlockedOutcome(
                        $request,
                        $booking,
                        $actor,
                        $requestMeta,
                        $bookingMeta,
                        $this->blockedSabreCancelOutcome(
                            'CANCELLATION_REQUEST_NOT_APPROVED',
                            'cancellation_request_not_approved',
                            'not_run',
                            'Supplier cancellation execution requires an approved cancellation request. Booking status was not changed.',
                        ),
                    );
                }

                if (! $hasPnr) {
                    return $this->storeSabreBlockedOutcome(
                        $request,
                        $booking,
                        $actor,
                        $requestMeta,
                        $bookingMeta,
                        $this->blockedSabreCancelOutcome(
                            'PNR_MISSING',
                            'pnr_missing',
                            'not_run',
                            'Supplier cancellation requires a PNR. Booking status was not changed.',
                        ),
                    );
                }

                if ($isTicketed) {
                    return $this->storeSabreBlockedOutcome(
                        $request,
                        $booking,
                        $actor,
                        $requestMeta,
                        $bookingMeta,
                        $this->blockedSabreCancelOutcome(
                            'TICKETED_REFUND_REQUIRED',
                            'ticketed_booking',
                            'ticketed_manual_review',
                            'Ticketed bookings require manual void or refund handling by our team.',
                            SabreBookingCancelService::CATEGORY_TICKETED_REFUND_REQUIRED,
                        ),
                    );
                }

                if (! $adminStaffSupplierExecution) {
                    return $this->storeSabreBlockedOutcome(
                        $request,
                        $booking,
                        $actor,
                        $requestMeta,
                        $bookingMeta,
                        $this->blockedSabreCancelOutcome(
                            'ADMIN_STAFF_EXECUTION_CONTEXT_REQUIRED',
                            'admin_staff_execution_context_required',
                            'not_run',
                            'Supplier cancellation execution is only available from admin/staff process actions. Booking status was not changed.',
                        ),
                    );
                }

                if (! (bool) config('suppliers.sabre.admin_cancel_live_call_enabled', false)) {
                    return $this->storeSabreBlockedOutcome(
                        $request,
                        $booking,
                        $actor,
                        $requestMeta,
                        $bookingMeta,
                        $this->blockedSabreCancelOutcome(
                            'LIVE_CANCEL_DISABLED',
                            'admin_staff_live_gate_disabled',
                            'not_run',
                            'Supplier cancellation execution is not enabled for admin/staff yet. Booking status was not changed.',
                            SabreBookingCancelService::CATEGORY_LIVE_CANCEL_DISABLED,
                        ),
                    );
                }

                $actorContext = strtolower(trim((string) $actorContextOverride));
                if (! $adminStaffSupplierExecution || ! in_array($actorContext, ['admin', 'staff'], true)) {
                    return $this->storeSabreBlockedOutcome(
                        $request,
                        $booking,
                        $actor,
                        $requestMeta,
                        $bookingMeta,
                        $this->blockedSabreCancelOutcome(
                            'NOT_ADMIN_STAFF_CONTEXT',
                            'not_admin_staff_context',
                            'not_run',
                            'Supplier cancellation execution requires an admin or staff actor context. Booking status was not changed.',
                            SabreBookingCancelService::CATEGORY_LIVE_CANCEL_DISABLED,
                        ),
                    );
                }

                $sabreCancelOutcome = app(SabreGdsCancelService::class)->cancelForBooking($booking, true, [
                    'admin_live_cancel_approved' => true,
                    'actor_context' => $actorContext,
                    'bypass_global_cancel_flags_for_admin' => true,
                    'actor' => $actor,
                ]);
                $requestMeta['sabre_cancel_outcome'] = $this->safeSabreCancelOutcome($sabreCancelOutcome);

                $category = is_array($sabreCancelOutcome) ? (string) ($sabreCancelOutcome['safe_summary_category'] ?? '') : '';
                $classification = is_array($sabreCancelOutcome) ? $this->sabreCancelClassification($sabreCancelOutcome) : '';
                $verified = ($sabreCancelOutcome['supplier_cancel_verified'] ?? false) === true
                    || in_array($classification, [
                        SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
                        SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
                    ], true);
                $safeSabreOutcome = is_array($sabreCancelOutcome)
                    ? $this->safeSabreCancelOutcome($sabreCancelOutcome)
                    : [
                        'classification' => $hasPnr ? 'SUPPLIER_CANCEL_NOT_ATTEMPTED' : 'PNR_MISSING',
                        'http_status' => null,
                        'cancel_payload_style' => null,
                        'cancelled_air_segments_removed' => false,
                        'post_cancel_segment_count' => null,
                        'ticket_numbers_present' => false,
                        'sabre_cancel_execution_attempted' => false,
                        'sabre_cancel_execution_blocked_reason' => 'supplier_cancel_not_attempted',
                        'sabre_cancel_precheck_status' => 'not_run',
                        'sabre_cancel_classification' => $hasPnr ? 'SUPPLIER_CANCEL_NOT_ATTEMPTED' : 'PNR_MISSING',
                    ];

                if ($verified) {
                    $from = $booking->status;
                    $booking->forceFill([
                        'status' => BookingStatus::Cancelled,
                        'cancellation_status' => BookingCancellationStatus::Processed->value,
                        'supplier_booking_status' => 'cancelled',
                        'cancelled_at' => now(),
                        'meta' => array_merge($bookingMeta, array_filter([
                            'sabre_cancel_outcome' => $safeSabreOutcome,
                            SabreGdsCancelReadiness::META_KEY => is_array($sabreCancelOutcome['sabre_gds_cancel'] ?? null)
                                ? $sabreCancelOutcome['sabre_gds_cancel']
                                : null,
                        ])),
                    ])->save();

                    $booking->statusLogs()->create([
                        'from_status' => $from?->value,
                        'to_status' => BookingStatus::Cancelled->value,
                        'user_id' => $actor->id,
                        'note' => 'Booking cancelled via supplier and cancellation workflow',
                        'context' => [
                            'cancellation_request_id' => $request->id,
                            'sabre_cancel_classification' => $classification,
                        ],
                    ]);

                    $this->writeCommunication($booking, $actor, 'booking_cancelled', [
                        'cancellation_request_id' => $request->id,
                        'sabre_cancel_verified' => true,
                    ]);
                } else {
                    $warning = $this->sabreCancelWarning($sabreCancelOutcome);
                    $requestMeta['manual_warning'] = $warning;
                    $requestMeta['sabre_cancel_manual_review'] = true;
                    $requestMeta['sabre_cancel_outcome'] = $safeSabreOutcome;
                    $bookingMeta['sabre_cancel_manual_review_required'] = true;
                    $bookingMeta['sabre_cancel_outcome'] = $safeSabreOutcome;
                    $booking->forceFill([
                        'meta' => $bookingMeta,
                    ])->save();

                    $this->writeCommunication($booking, $actor, 'sabre_cancel_manual_review', [
                        'cancellation_request_id' => $request->id,
                        'safe_summary_category' => $category,
                        'sabre_cancel_classification' => $safeSabreOutcome['classification'] ?? null,
                    ]);

                    $this->writeAudit($booking, $actor, 'booking.cancellation_supplier_blocked', [
                        'cancellation_request_id' => $request->id,
                        'safe_summary_category' => $category,
                        'sabre_cancel_execution_blocked_reason' => $safeSabreOutcome['sabre_cancel_execution_blocked_reason'] ?? null,
                        'sabre_cancel_precheck_status' => $safeSabreOutcome['sabre_cancel_precheck_status'] ?? null,
                        'sabre_cancel_classification' => $safeSabreOutcome['sabre_cancel_classification'] ?? null,
                    ]);

                    $request->forceFill(['meta' => $requestMeta])->save();

                    return $request->fresh();
                }
            } elseif ($isPiaNdcBooking && $adminStaffSupplierExecution && $request->status === BookingCancellationStatus::Approved) {
                $connection = SupplierConnection::query()->find((int) ($bookingMeta['supplier_connection_id'] ?? 0));
                if ($connection !== null) {
                    $piaOutcome = app(PiaNdcCancelService::class)->cancelForBooking($booking, $connection, $actor);
                    $requestMeta['pia_ndc_cancel_outcome'] = $piaOutcome;
                    $bookingMeta['pia_ndc_cancel_outcome'] = $piaOutcome;
                    $booking->forceFill(['meta' => $bookingMeta])->save();
                }
            } elseif ($isAirBlueBooking && $adminStaffSupplierExecution && $request->status === BookingCancellationStatus::Approved) {
                $connection = SupplierConnection::query()->find((int) ($bookingMeta['supplier_connection_id'] ?? 0));
                if ($connection !== null) {
                    $airBlueOutcome = app(AirBlueCancelService::class)->cancelForBooking($booking, $connection, $actor);
                    $requestMeta['airblue_cancel_outcome'] = $airBlueOutcome;
                    $bookingMeta['airblue_cancel_outcome'] = $airBlueOutcome;
                    $booking->forceFill(['meta' => $bookingMeta])->save();
                }
            } elseif ($isIatiBooking && $adminStaffSupplierExecution && $request->status === BookingCancellationStatus::Approved) {
                $connection = SupplierConnection::query()->find((int) ($bookingMeta['supplier_connection_id'] ?? 0));
                if ($connection !== null) {
                    $iatiOutcome = app(IatiCancelService::class)->cancelForBooking($booking, $connection, $actor);
                    $requestMeta['iati_cancel_outcome'] = $iatiOutcome;
                    $bookingMeta['iati_cancel_outcome'] = $iatiOutcome;
                    $booking->forceFill(['meta' => $bookingMeta])->save();
                }
            } elseif ($isTicketed) {
                $warning = 'Ticketed booking requires manual supplier void/refund handling until supplier API docs are reviewed.';
                $requestMeta['manual_warning'] = $warning;
                $bookingMeta['manual_void_refund_warning'] = $warning;
                $bookingMeta['manual_void_refund_review_required'] = true;
                $booking->forceFill([
                    'cancellation_status' => BookingCancellationStatus::Processed->value,
                    'meta' => $bookingMeta,
                ])->save();
            } else {
                $from = $booking->status;
                $booking->forceFill([
                    'status' => BookingStatus::Cancelled,
                    'cancellation_status' => BookingCancellationStatus::Processed->value,
                    'cancelled_at' => now(),
                ])->save();

                $booking->statusLogs()->create([
                    'from_status' => $from?->value,
                    'to_status' => BookingStatus::Cancelled->value,
                    'user_id' => $actor->id,
                    'note' => 'Booking cancelled via cancellation workflow',
                    'context' => ['cancellation_request_id' => $request->id],
                ]);

                $this->writeCommunication($booking, $actor, 'booking_cancelled', [
                    'cancellation_request_id' => $request->id,
                ]);
            }

            $request->forceFill([
                'status' => BookingCancellationStatus::Processed,
                'processed_by' => $actor->id,
                'processed_at' => now(),
                'meta' => $requestMeta,
            ])->save();

            $notifyPayload = [
                'cancellation_request_id' => $request->id,
                'status' => 'processed',
            ];
            if (is_array($sabreCancelOutcome)) {
                $notifyPayload['sabre_cancel_safe_summary_category'] = (string) ($sabreCancelOutcome['safe_summary_category'] ?? '');
                $notifyPayload['sabre_cancel_verified'] = in_array($this->sabreCancelClassification($sabreCancelOutcome), [
                    SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED,
                    SabreBookingCancelService::CLASSIFICATION_CANCEL_CONFIRMED_AIR_SEGMENTS_REMOVED,
                ], true);
            }

            $this->writeAudit($booking, $actor, 'booking.cancellation_processed', [
                'cancellation_request_id' => $request->id,
                'ticketed' => $isTicketed,
                'sabre_cancel_safe_summary_category' => $notifyPayload['sabre_cancel_safe_summary_category'] ?? null,
                'sabre_cancel_verified' => $notifyPayload['sabre_cancel_verified'] ?? null,
            ]);

            $this->notifyCancellation($booking, OtaNotificationEvent::CancellationStatusChanged, $notifyPayload, $actor);

            return $request->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $outcome
     */
    protected function sabreCancelClassification(array $outcome): string
    {
        $classification = data_get($outcome, 'post_cancel_verification.classification');
        if (is_string($classification) && trim($classification) !== '') {
            return trim($classification);
        }

        $classification = $outcome['classification'] ?? null;

        return is_string($classification) ? trim($classification) : '';
    }

    /**
     * @param  array<string, mixed>  $outcome
     * @return array<string, mixed>
     */
    protected function safeSabreCancelOutcome(array $outcome): array
    {
        $post = is_array($outcome['post_cancel_verification'] ?? null) ? $outcome['post_cancel_verification'] : [];
        $probe = is_array($outcome['cancel_probe'] ?? null) ? $outcome['cancel_probe'] : [];
        $classification = $this->sabreCancelClassification($outcome);
        if ($classification === '' && is_string($outcome['classification'] ?? null)) {
            $classification = (string) $outcome['classification'];
        }
        if ($classification === '' && is_string($outcome['status'] ?? null)) {
            $classification = (string) $outcome['status'];
        }
        if ($classification === '') {
            $classification = (string) ($outcome['safe_summary_category'] ?? 'UNKNOWN');
        }
        $blockedReason = $outcome['sabre_cancel_execution_blocked_reason'] ?? $outcome['blocked_reason'] ?? $outcome['status'] ?? null;
        $precheckStatus = $outcome['sabre_cancel_precheck_status'] ?? $outcome['precheck_status'] ?? $outcome['status'] ?? null;

        return [
            'classification' => $classification,
            'http_status' => $post['http_status'] ?? ($probe['http_status'] ?? null),
            'cancel_payload_style' => $outcome['payload_style'] ?? null,
            'cancelled_air_segments_removed' => (bool) ($post['cancel_air_segments_removed'] ?? false),
            'post_cancel_segment_count' => isset($post['post_cancel_segment_count']) && is_numeric($post['post_cancel_segment_count'])
                ? (int) $post['post_cancel_segment_count']
                : null,
            'ticket_numbers_present' => (bool) ($post['ticket_numbers_present'] ?? false),
            'cancelled_at' => ($outcome['success'] ?? false) === true ? now()->toIso8601String() : null,
            'sabre_cancel_execution_attempted' => (bool) ($outcome['live_call_attempted'] ?? false),
            'sabre_cancel_execution_blocked_reason' => is_string($blockedReason) ? $blockedReason : null,
            'sabre_cancel_precheck_status' => is_string($precheckStatus) ? $precheckStatus : null,
            'sabre_cancel_classification' => $classification,
        ];
    }

    /**
     * @param  array<string, mixed>  $requestMeta
     * @param  array<string, mixed>  $bookingMeta
     * @param  array<string, mixed>  $outcome
     */
    protected function storeSabreBlockedOutcome(
        BookingCancellationRequest $request,
        Booking $booking,
        User $actor,
        array $requestMeta,
        array $bookingMeta,
        array $outcome,
    ): BookingCancellationRequest {
        $safeOutcome = $this->safeSabreCancelOutcome($outcome);
        $warning = (string) ($outcome['message'] ?? 'Cancellation was not confirmed by supplier. Booking status was not changed.');

        $requestMeta['manual_warning'] = $warning;
        $requestMeta['sabre_cancel_manual_review'] = true;
        $requestMeta['sabre_cancel_outcome'] = $safeOutcome;
        $bookingMeta['sabre_cancel_manual_review_required'] = true;
        $bookingMeta['sabre_cancel_outcome'] = $safeOutcome;

        $booking->forceFill(['meta' => $bookingMeta])->save();
        $request->forceFill(['meta' => $requestMeta])->save();

        $this->writeCommunication($booking, $actor, 'sabre_cancel_manual_review', [
            'cancellation_request_id' => $request->id,
            'safe_summary_category' => $outcome['safe_summary_category'] ?? null,
            'sabre_cancel_classification' => $safeOutcome['sabre_cancel_classification'] ?? null,
        ]);
        $this->writeAudit($booking, $actor, 'booking.cancellation_supplier_blocked', [
            'cancellation_request_id' => $request->id,
            'safe_summary_category' => $outcome['safe_summary_category'] ?? null,
            'sabre_cancel_execution_attempted' => $safeOutcome['sabre_cancel_execution_attempted'] ?? false,
            'sabre_cancel_execution_blocked_reason' => $safeOutcome['sabre_cancel_execution_blocked_reason'] ?? null,
            'sabre_cancel_precheck_status' => $safeOutcome['sabre_cancel_precheck_status'] ?? null,
            'sabre_cancel_classification' => $safeOutcome['sabre_cancel_classification'] ?? null,
        ]);

        return $request->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function blockedSabreCancelOutcome(
        string $classification,
        string $blockedReason,
        string $precheckStatus,
        string $message,
        string $category = SabreBookingCancelService::CATEGORY_CANCEL_NOT_ELIGIBLE,
    ): array {
        return [
            'success' => false,
            'status' => $precheckStatus,
            'safe_summary_category' => $category,
            'message' => $message,
            'live_call_attempted' => false,
            'supplier_cancel_verified' => false,
            'sabre_cancel_execution_attempted' => false,
            'sabre_cancel_execution_blocked_reason' => $blockedReason,
            'sabre_cancel_precheck_status' => $precheckStatus,
            'sabre_cancel_classification' => $classification,
            'classification' => $classification,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $outcome
     */
    protected function sabreCancelWarning(?array $outcome): string
    {
        $status = is_array($outcome) ? (string) ($outcome['status'] ?? '') : '';
        $classification = is_array($outcome) ? $this->sabreCancelClassification($outcome) : '';

        if ($status === 'no_active_air_segments') {
            return 'Supplier booking has no active air segments to cancel.';
        }

        if ($classification === SabreBookingCancelService::CLASSIFICATION_HTTP_200_BUT_STILL_ACTIVE) {
            return 'Sabre accepted the cancellation request, but active air segments are still present. Booking status was not changed.';
        }

        return is_array($outcome) && is_string($outcome['message'] ?? null) && trim($outcome['message']) !== ''
            ? trim($outcome['message'])
            : 'Cancellation was not confirmed by supplier. Booking status was not changed.';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function notifyCancellation(Booking $booking, OtaNotificationEvent $event, array $payload, ?User $actor = null): void
    {
        $booking->loadMissing('agency');
        $agency = $booking->agency;
        if ($agency === null) {
            return;
        }

        $status = is_string($payload['status'] ?? null) ? $payload['status'] : null;
        $cancellationRequestId = (int) ($payload['cancellation_request_id'] ?? 0);
        $cancellationStatus = $status
            ?? (string) ($booking->cancellation_status ?? $booking->status->value);

        $emailContext = [
            'booking_reference' => $booking->reference_code,
            'booking_id' => $booking->id,
            'cancellation_request_id' => $cancellationRequestId > 0 ? $cancellationRequestId : null,
            'cancellation_status' => $cancellationStatus,
        ];

        $customerNotificationType = $event === OtaNotificationEvent::CancellationRequested
            ? 'cancellation_requested_customer'
            : 'cancellation_status_customer';

        if (! $this->cancellationCustomerNotificationAlreadyLogged(
            $booking,
            $event->value,
            $customerNotificationType,
            $cancellationRequestId,
            $cancellationStatus,
        )) {
            $this->otaNotificationService->send(
                agency: $agency,
                eventKey: $event->value,
                booking: $booking,
                actor: $actor,
                payload: array_merge($emailContext, $payload, [
                    'universal_email' => $event === OtaNotificationEvent::CancellationRequested
                        ? $this->bookingEmailPayloadFactory->customerCancellationRequested($booking)
                        : $this->bookingEmailPayloadFactory->customerCancellationUpdate($booking, $status),
                    'routing_note' => 'Customer cancellation notification; internal and B2B notifications are sent separately with role-safe copy.',
                ]),
                fallbackSubject: 'Cancellation update: '.$booking->reference_code,
                fallbackBody: 'There is a cancellation update for booking '.$booking->reference_code.'.',
                templateVariables: ['booking_reference' => (string) $booking->reference_code],
                recipientContext: [
                    'notify_buckets' => ['booking_customer'],
                ],
            );
        }

        $this->otaNotificationService->send(
            agency: $agency,
            eventKey: $event->value,
            booking: $booking,
            actor: $actor,
            payload: array_merge($emailContext, $payload, [
                'universal_email' => $event === OtaNotificationEvent::CancellationRequested
                    ? $this->bookingEmailPayloadFactory->cancellationRequested($booking)
                    : $this->bookingEmailPayloadFactory->adminCancellationAlert($booking),
                'routing_note' => 'Internal cancellation notification; customer notification is sent separately with customer-safe copy.',
            ]),
            fallbackSubject: 'Cancellation update: '.$booking->reference_code,
            fallbackBody: 'There is a cancellation update for booking '.$booking->reference_code.'.',
            templateVariables: ['booking_reference' => (string) $booking->reference_code],
            recipientContext: [
                'notify_buckets' => $event === OtaNotificationEvent::CancellationRequested
                    ? ['assigned_staff', 'operations_queue', 'platform_admin']
                    : ['platform_admin', 'assigned_staff'],
                'deduplicated_buckets' => ['customer_party', 'booking_customer'],
            ],
        );

        if (! $this->cancellationB2bNotificationAlreadyLogged(
            $booking,
            $event->value,
            $cancellationRequestId,
            $cancellationStatus,
        )) {
            $this->otaNotificationService->send(
                agency: $agency,
                eventKey: $event->value,
                booking: $booking,
                actor: $actor,
                payload: array_merge($emailContext, $payload, [
                    'universal_email' => $this->bookingEmailPayloadFactory->agencyCancellationUpdate($booking, $status),
                    'routing_note' => 'Agency/agent cancellation update; skipped safely when no agency or agent recipient resolves.',
                ]),
                fallbackSubject: 'Cancellation update for booking '.$booking->reference_code,
                fallbackBody: 'There is a cancellation update for booking '.$booking->reference_code.'.',
                templateVariables: ['booking_reference' => (string) $booking->reference_code],
                recipientContext: [
                    'notify_buckets' => ['booking_agent', 'agency_admin', 'agent_staff_creator'],
                    'deduplicated_buckets' => ['customer_party', 'booking_customer'],
                    'agent_staff_creator_email' => $actor?->isAgentStaff() ? $actor->email : null,
                    'agent_staff_creator_user_id' => $actor?->isAgentStaff() ? $actor->id : null,
                ],
            );
        }
    }

    protected function cancellationCustomerNotificationAlreadyLogged(
        Booking $booking,
        string $eventKey,
        string $notificationType,
        int $cancellationRequestId,
        string $cancellationStatus,
    ): bool {
        $query = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', $eventKey)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->where('meta->notification_type', $notificationType);

        if ($cancellationRequestId > 0) {
            $query->where('meta->payload->cancellation_request_id', $cancellationRequestId);
        }

        if ($cancellationStatus !== '') {
            $query->where('meta->payload->cancellation_status', $cancellationStatus);
        }

        return $query->exists();
    }

    protected function cancellationB2bNotificationAlreadyLogged(
        Booking $booking,
        string $eventKey,
        int $cancellationRequestId,
        string $cancellationStatus,
    ): bool {
        $query = CommunicationLog::query()
            ->where('booking_id', $booking->id)
            ->where('event', $eventKey)
            ->whereIn('status', ['queued', 'sent', 'sending'])
            ->where('meta->notification_type', 'cancellation_update_b2b');

        if ($cancellationRequestId > 0) {
            $query->where('meta->payload->cancellation_request_id', $cancellationRequestId);
        }

        if ($cancellationStatus !== '') {
            $query->where('meta->payload->cancellation_status', $cancellationStatus);
        }

        return $query->exists();
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    protected function writeAudit(Booking $booking, ?User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => $newValues,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function writeCommunication(Booking $booking, ?User $actor, string $event, array $meta = []): void
    {
        CommunicationLog::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'user_id' => $booking->customer_id,
            'channel' => 'system',
            'event' => $event,
            'recipient_name' => $booking->contact?->name ?? $booking->customer?->name,
            'recipient_email' => $booking->contact?->email ?? $booking->customer?->email,
            'status' => 'logged',
            'meta' => array_merge($meta, [
                'actor_id' => $actor?->id,
            ]),
            'sent_at' => now(),
        ]);
    }
}
