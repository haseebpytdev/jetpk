<?php

namespace App\Services\Suppliers;

use App\Contracts\Suppliers\SupplierBookingInterface;
use App\Data\SupplierBookingResultData;
use App\Enums\BookingStatus;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupplierConnectionStatus;
use App\Enums\SupplierProvider;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Booking\BookingOperationalPrecheckService;
use App\Services\Booking\BookingService;
use App\Services\Communication\BookingCommunicationService;
use App\Services\Suppliers\BookingAdapters\AirBlueSupplierBookingAdapter;
use App\Services\Suppliers\BookingAdapters\AirlineDirectSupplierBookingAdapter;
use App\Services\Suppliers\BookingAdapters\DuffelSupplierBookingAdapter;
use App\Services\Suppliers\BookingAdapters\IatiSupplierBookingAdapter;
use App\Services\Suppliers\BookingAdapters\PiaNdcSupplierBookingAdapter;
use App\Services\Suppliers\BookingAdapters\SabreSupplierBookingAdapter;
use App\Support\Bookings\IatiReservationLifecycleService;
use App\Support\Bookings\IatiSupplierBookingEligibility;
use App\Support\Bookings\SabreOperationalPnrReadiness;
use App\Support\Bookings\SabrePnrCertificationSupport;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\Bookings\SupplierBookingPreflightGuard;
use App\Support\Platform\PlatformModuleEnforcer;
use App\Support\Security\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Routes paid bookings to the correct GDS/API for PNR creation.
 *
 * Provider selection is explicit: {@see resolveAdapter} dispatches by {@see SupplierProvider}
 * (e.g. Duffel → {@see DuffelSupplierBookingAdapter}, Sabre → {@see SabreSupplierBookingAdapter}).
 * There is no silent fallback to another supplier when the enum case is unsupported.
 */
class SupplierBookingService
{
    public function __construct(
        protected BookingService $bookingService,
        protected BookingOperationalPrecheckService $operationalPrecheckService,
        protected BookingCommunicationService $communicationService,
        protected SabreSupplierBookingAdapter $sabreAdapter,
        protected PiaNdcSupplierBookingAdapter $piaNdcAdapter,
        protected AirBlueSupplierBookingAdapter $airBlueAdapter,
        protected AirlineDirectSupplierBookingAdapter $airlineDirectAdapter,
        protected DuffelSupplierBookingAdapter $duffelAdapter,
        protected IatiSupplierBookingAdapter $iatiAdapter,
        protected PlatformModuleEnforcer $platformModuleEnforcer,
        protected SupplierBookingPreflightGuard $preflightGuard,
        protected SabrePnrCertificationSupport $sabrePnrCertificationSupport,
        protected SabreOperationalPnrReadiness $operationalPnrReadiness,
        protected IatiReservationLifecycleService $iatiReservationLifecycle,
        protected SupplierBookingAttemptGuard $attemptGuard,
    ) {}

    public function isBookingEligible(
        Booking $booking,
        bool $adminOverride = false,
        bool $allowControlledStaffPnr = false,
        string $attemptSource = 'system',
    ): bool {
        $meta = is_array($booking->meta) ? $booking->meta : [];

        if (IatiSupplierBookingEligibility::appliesTo($booking)) {
            return IatiSupplierBookingEligibility::isEligible($booking, $adminOverride);
        }

        if (($booking->payment_status ?? '') !== 'paid' && ! $adminOverride) {
            return false;
        }

        if (! in_array($booking->status, [BookingStatus::Paid, BookingStatus::PaymentPending, BookingStatus::TicketingPending], true)) {
            return false;
        }

        if (strtolower(trim((string) ($meta['supplier_provider'] ?? ''))) === SupplierProvider::Sabre->value) {
            return false;
        }
        if (($meta['defer_supplier_booking_to_manual_review'] ?? false) === true
            && ! $this->sabrePnrCertificationSupport->allowsControlledStaffPnrBypassDeferManualReview(
                $booking,
                $attemptSource,
                $allowControlledStaffPnr,
            )
            && ! $this->operationalPnrReadiness->bypassesLegacyDeferManualReview($booking)) {
            return false;
        }
        $hasValidationSnapshot = isset($meta['validated_offer_snapshot']) || isset($meta['normalized_offer_snapshot']);
        $validationStatus = (string) ($meta['offer_validation_status'] ?? '');
        $offerIsValid = in_array($validationStatus, ['valid', 'validated', 'ok', 'pass', 'fresh'], true)
            || ($validationStatus === '' && $hasValidationSnapshot);
        if (! $offerIsValid || ! $hasValidationSnapshot) {
            return false;
        }

        if (((string) ($booking->pnr ?? '')) !== '') {
            return false;
        }

        return $this->operationalPrecheckService->validatePassengerReadiness($booking) === [];
    }

    public function markManualPnr(Booking $booking, User $actor, string $pnr, ?string $supplierReference = null, ?string $note = null): SupplierBooking
    {
        $manualBlock = $this->preflightGuard->assertManualPnrAllowed($booking);
        if ($manualBlock !== null) {
            throw new InvalidArgumentException($manualBlock);
        }

        $booking->loadMissing(['latestSupplierBooking']);
        $provider = (string) (($booking->meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? 'manual'));
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        $blocked = $this->platformModuleEnforcer->supplierBookingBlockedMessage(
            $provider !== '' && $provider !== 'manual' ? strtolower($provider) : null,
            false,
            $distributionChannel,
        );
        if ($blocked !== null) {
            throw new InvalidArgumentException($blocked);
        }

        $reference = trim((string) ($supplierReference ?? '')) !== '' ? trim((string) $supplierReference) : null;

        return DB::transaction(function () use ($booking, $actor, $pnr, $provider, $reference, $note): SupplierBooking {
            $normalizedPnr = strtoupper(trim($pnr));
            $this->preflightGuard->recordManualPnrAttempt($booking, $actor, $provider, $normalizedPnr, $reference, 'manual');

            $supplierBooking = SupplierBooking::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $booking->latestSupplierBooking?->supplier_connection_id,
                'provider' => $provider !== '' ? $provider : 'manual',
                'supplier_api_booking_id' => $reference,
                'supplier_reference' => $reference,
                'pnr' => $normalizedPnr,
                'status' => 'pending_ticketing',
                'raw_summary' => SensitiveDataRedactor::redact([
                    'source' => 'manual_pnr',
                    'entered_by' => $actor->id,
                    'entered_at' => now()->toIso8601String(),
                    'note_present' => $note !== null && trim((string) $note) !== '',
                ]),
                'created_by' => $actor->id,
                'created_at_supplier' => now(),
            ]);

            $meta = is_array($booking->meta) ? $booking->meta : [];
            $trimmedNote = $note !== null ? trim((string) $note) : '';
            $meta['manual_pnr'] = [
                'source' => 'manual',
                'entered_by' => $actor->id,
                'entered_at' => now()->toIso8601String(),
                'pnr' => $normalizedPnr,
                'supplier_reference' => $reference,
                'note' => $trimmedNote !== '' ? $trimmedNote : null,
            ];

            $booking->forceFill([
                'supplier_booking_status' => 'pending_ticketing',
                'supplier_api_booking_id' => $reference,
                'supplier_reference' => $reference,
                'pnr' => $normalizedPnr,
                'supplier_booking_created_at' => now(),
                'meta' => $meta,
            ])->save();

            Log::notice('supplier_booking.manual_pnr_attached', [
                'booking_id' => $booking->id,
                'provider' => $provider,
                'action' => 'mark_manual_pnr',
                'source' => 'manual',
            ]);

            $auditValues = [
                'supplier_booking_id' => $supplierBooking->id,
                'provider' => $supplierBooking->provider,
                'supplier_reference' => $supplierBooking->supplier_reference,
                'pnr' => $supplierBooking->pnr,
            ];
            if ($trimmedNote !== '') {
                $auditValues['note'] = $trimmedNote;
            }
            $this->writeAudit($booking, $actor, 'booking.manual_pnr_marked', $auditValues);

            return $supplierBooking;
        });
    }

    public function createSupplierBooking(
        Booking $booking,
        User $actor,
        bool $adminOverride = false,
        bool $explicitRetry = false,
        string $attemptSource = 'system',
    ): SupplierBookingResultData {
        $preflight = $this->preflightGuard->preflightAutomatedCreate($booking, $actor, $attemptSource, $explicitRetry);
        if ($preflight !== null) {
            return $preflight;
        }

        if (! $this->isBookingEligible($booking, $adminOverride)) {
            $precheckErrors = $this->operationalPrecheckService->validatePassengerReadiness($booking);

            $provider = strtolower(trim((string) (($booking->meta ?? [])['supplier_provider'] ?? $booking->supplier ?? 'unknown')));

            return new SupplierBookingResultData(
                success: false,
                status: 'skipped',
                provider: $provider !== '' ? $provider : 'unknown',
                error_code: 'booking_not_eligible',
                error_message: $precheckErrors !== []
                    ? 'Booking is not eligible for supplier booking: '.$precheckErrors[0]
                    : 'Booking is not eligible for supplier booking.',
                warnings: $precheckErrors,
                safe_summary: ['source' => $attemptSource, 'reason' => 'booking_not_eligible'],
            );
        }

        $meta = $booking->meta ?? [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $connection = $this->resolveConnection($booking, $provider, $meta['supplier_connection_id'] ?? null);
        if ($connection === null) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $provider !== '' ? $provider : 'unknown',
                error_code: 'supplier_connection_missing',
                error_message: 'Supplier connection is not configured.',
            );
        }

        if ($provider !== '' && $provider !== $connection->provider->value) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $provider,
                error_code: 'supplier_identity_mismatch',
                error_message: 'Booking supplier_provider does not match the resolved supplier connection.',
            );
        }

        $distributionChannel = $this->platformModuleEnforcer->distributionChannelFromBookingMeta($meta);
        $moduleBlock = $this->platformModuleEnforcer->supplierBookingBlockedMessage(
            $provider !== '' ? $provider : null,
            false,
            $distributionChannel,
        );
        if ($moduleBlock !== null) {
            return new SupplierBookingResultData(
                success: false,
                status: 'failed',
                provider: $provider !== '' ? $provider : (string) $connection->provider->value,
                error_code: 'platform_module_disabled',
                error_message: $moduleBlock,
            );
        }

        $this->attemptGuard->releaseStaleAttempts($booking, $provider !== '' ? $provider : $connection->provider->value);
        $attemptBlock = $this->attemptGuard->assertRetryAllowed(
            $booking,
            $provider !== '' ? $provider : $connection->provider->value,
        );
        if ($attemptBlock['blocked']) {
            return $this->blockedSupplierBookingResult(
                $booking,
                $actor,
                $connection,
                $provider !== '' ? $provider : $connection->provider->value,
                (string) ($attemptBlock['reason_code'] ?? 'supplier_booking_in_progress'),
                (string) ($attemptBlock['error_message'] ?? 'Supplier booking already in progress.'),
                $attemptSource,
                $this->attemptGuard->blockedSafeSummary($attemptBlock),
            );
        }

        if (IatiSupplierBookingEligibility::appliesTo($booking)) {
            $lifecycleGate = $this->iatiReservationLifecycle->assertSupplierBookAllowed($booking, $adminOverride);
            if (! ($lifecycleGate['allowed'] ?? false)) {
                return $this->blockedSupplierBookingResult(
                    $booking,
                    $actor,
                    $connection,
                    SupplierProvider::Iati->value,
                    (string) ($lifecycleGate['error_code'] ?? 'lifecycle_blocked'),
                    (string) ($lifecycleGate['error_message'] ?? 'IATI supplier booking is not allowed for this reservation state.'),
                    $attemptSource,
                    is_array($lifecycleGate['safe_summary'] ?? null) ? $lifecycleGate['safe_summary'] : [],
                );
            }
        }

        $lock = $this->attemptGuard->acquireLock($booking, $provider !== '' ? $provider : $connection->provider->value);
        if ($lock === null) {
            $lockBlock = $this->attemptGuard->assertRetryAllowed($booking, $provider !== '' ? $provider : $connection->provider->value);

            return $this->blockedSupplierBookingResult(
                $booking,
                $actor,
                $connection,
                $provider !== '' ? $provider : $connection->provider->value,
                (string) ($lockBlock['reason_code'] ?? 'supplier_booking_lock_active'),
                'Supplier booking already in progress.',
                $attemptSource,
                $this->attemptGuard->blockedSafeSummary($lockBlock),
            );
        }

        try {
            $attempt = SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => $connection->provider->value,
                'action' => 'create_pnr',
                'status' => 'processing',
                'attempted_by' => $actor->id,
                'attempted_at' => now(),
                'safe_summary' => SensitiveDataRedactor::redact([
                    'source' => $attemptSource,
                    'action' => 'create_pnr',
                ]),
            ]);
            $this->attemptGuard->setInFlightAttemptId($attempt->id);

            $result = $this->resolveAdapter($connection->provider)->createSupplierBooking($booking, $connection, $actor);

            return DB::transaction(function () use ($booking, $actor, $attempt, $connection, $result): SupplierBookingResultData {
                if (! $result->success) {
                    $attempt->forceFill([
                        'status' => $result->status === 'not_supported' ? 'failed' : 'failed',
                        'safe_summary' => SensitiveDataRedactor::redact($result->safe_summary),
                        'response_payload' => SensitiveDataRedactor::redact($result->response_payload),
                        'error_code' => $result->error_code,
                        'error_message' => $result->error_message ?: ($result->warnings[0] ?? 'Supplier booking failed.'),
                        'completed_at' => now(),
                    ])->save();

                    $this->writeAudit($booking, $actor, 'booking.supplier_booking_failed', [
                        'attempt_id' => $attempt->id,
                        'status' => $result->status,
                        'provider' => $result->provider,
                    ]);
                    if ($result->status === 'manual_review') {
                        $this->communicationService->notifyManualReviewRequired(
                            $booking,
                            $actor,
                            'supplier_manual_review',
                        );
                    } else {
                        $this->communicationService->notifySupplierFailure(
                            $booking,
                            OtaNotificationEvent::SupplierBookingFailed->value,
                            $actor,
                            [
                                'supplier_attempt_id' => $attempt->id,
                                'supplier_connection_id' => $connection->id,
                                'provider' => $result->provider,
                                'error_code' => $result->error_code,
                                'failure_reason' => $result->error_message ?: ($result->warnings[0] ?? 'Supplier booking failed.'),
                                'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
                                'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                            ],
                        );
                    }

                    return $result;
                }

                SupplierBooking::query()->create([
                    'agency_id' => $booking->agency_id,
                    'booking_id' => $booking->id,
                    'supplier_connection_id' => $connection->id,
                    'provider' => $result->provider,
                    'supplier_api_booking_id' => $result->supplier_reference,
                    'supplier_reference' => $result->supplier_reference,
                    'pnr' => $result->pnr,
                    'status' => 'pending_ticketing',
                    'raw_summary' => SensitiveDataRedactor::redact($result->safe_summary),
                    'created_by' => $actor->id,
                    'created_at_supplier' => now(),
                ]);

                $attempt->forceFill([
                    'status' => 'success',
                    'request_payload' => SensitiveDataRedactor::redact($result->request_payload),
                    'response_payload' => SensitiveDataRedactor::redact($result->response_payload),
                    'safe_summary' => SensitiveDataRedactor::redact($result->safe_summary),
                    'supplier_reference' => $result->supplier_reference,
                    'completed_at' => now(),
                ])->save();

                $booking->forceFill([
                    'supplier_booking_status' => 'pending_ticketing',
                    'supplier_api_booking_id' => $result->supplier_reference,
                    'supplier_reference' => $result->supplier_reference,
                    'pnr' => $result->pnr,
                    'supplier_booking_created_at' => now(),
                ])->save();

                $this->writeAudit($booking, $actor, 'booking.supplier_booking_created', [
                    'attempt_id' => $attempt->id,
                    'provider' => $result->provider,
                    'supplier_reference' => $result->supplier_reference,
                    'pnr' => $result->pnr,
                ]);

                $this->communicationService->sendSupplierBookingCreated($booking->fresh());

                return $result;
            });
        } finally {
            $this->attemptGuard->setInFlightAttemptId(null);
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    protected function blockedSupplierBookingResult(
        Booking $booking,
        User $actor,
        SupplierConnection $connection,
        string $provider,
        string $errorCode,
        string $errorMessage,
        string $attemptSource,
        array $safeSummary = [],
    ): SupplierBookingResultData {
        $attempt = SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => $provider,
            'action' => 'create_pnr',
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => SensitiveDataRedactor::redact(array_merge([
                'source' => $attemptSource,
                'action' => 'create_pnr',
            ], $safeSummary)),
        ]);

        $this->writeAudit($booking, $actor, 'booking.supplier_booking_failed', [
            'attempt_id' => $attempt->id,
            'status' => 'failed',
            'provider' => $provider,
            'error_code' => $errorCode,
        ]);

        try {
            $this->communicationService->notifySupplierFailure(
                $booking,
                OtaNotificationEvent::SupplierBookingFailed->value,
                $actor,
                [
                    'supplier_attempt_id' => $attempt->id,
                    'supplier_connection_id' => $connection->id,
                    'provider' => $provider,
                    'error_code' => $errorCode,
                    'failure_reason' => $errorMessage,
                    'supplier_booking_status' => (string) ($booking->supplier_booking_status ?? ''),
                    'ticketing_status' => (string) ($booking->ticketing_status ?? ''),
                ],
            );
        } catch (\Throwable) {
            // Non-critical notification path.
        }

        return new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: $provider,
            error_code: $errorCode,
            error_message: $errorMessage,
            safe_summary: SensitiveDataRedactor::redact(array_merge([
                'source' => $attemptSource,
            ], $safeSummary)),
        );
    }

    protected function resolveConnection(Booking $booking, string $provider, mixed $connectionId): ?SupplierConnection
    {
        $query = SupplierConnection::query()
            ->where('agency_id', $booking->agency_id)
            ->where(function ($q): void {
                $q->where('is_active', true)->orWhere('status', SupplierConnectionStatus::Active->value);
            });

        if ($connectionId !== null) {
            return $query->where('id', (int) $connectionId)->first();
        }

        return $query->where('provider', $provider)->orderBy('id')->first();
    }

    protected function resolveAdapter(SupplierProvider $provider): SupplierBookingInterface
    {
        return match ($provider) {
            SupplierProvider::Sabre => $this->sabreAdapter,
            SupplierProvider::PiaNdc => $this->piaNdcAdapter,
            SupplierProvider::Airblue => $this->airBlueAdapter,
            SupplierProvider::AirlineDirect => $this->airlineDirectAdapter,
            SupplierProvider::Duffel => $this->duffelAdapter,
            SupplierProvider::Iati => $this->iatiAdapter,
            default => throw new InvalidArgumentException(
                'Automated supplier booking is not implemented for provider: '.$provider->value
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $newValues
     */
    protected function writeAudit(Booking $booking, User $actor, string $action, array $newValues): void
    {
        AuditLog::query()->create([
            'agency_id' => $booking->agency_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Booking::class,
            'auditable_id' => $booking->id,
            'properties' => [
                'old_values' => [],
                'new_values' => $newValues,
            ],
        ]);
    }
}
