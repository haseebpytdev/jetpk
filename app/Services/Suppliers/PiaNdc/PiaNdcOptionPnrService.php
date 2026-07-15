<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\BookingStatus;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Bookings\PiaNdcBookingProviderContextResolver;
use App\Support\Bookings\PiaNdcFareFamilyPolicy;
use App\Support\Security\SensitiveDataRedactor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * PIA NDC option PNR creation for unpaid bookings (R12G admin, R12H public auto).
 *
 * Payment verification is required for ticketing, not for Hitit DoOrderCreate option PNRs.
 */
class PiaNdcOptionPnrService
{
    public const CREATE_CONFIRM_PHRASE = 'CREATE_PIA_OPTION_PNR';

    public const AUTO_FAILURE_CUSTOMER_NOTICE = 'Airline reservation could not be created. Fare is no longer available. Please select another fare.';

    private const MANUAL_ACTION = 'create_option_pnr';

    private const AUTO_ACTION = 'auto_create_option_pnr';

    /** @var list<string> */
    private const CREATE_ACTIONS = [self::MANUAL_ACTION, self::AUTO_ACTION];

    private const ACTIVE_SUPPLIER_BOOKING_STATUSES = [
        'created',
        'opened',
        'confirmed',
        'pending_ticketing',
        'pending_payment_or_ticketing',
        'option_pnr_created',
    ];

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcBookingProviderContextResolver $providerContextResolver,
    ) {}

    public function canCreateForBooking(Booking $booking): bool
    {
        return $this->evaluateCreateEligibility($booking)['eligible'];
    }

    /**
     * @return array{eligible: bool, reason: string}
     */
    public function evaluateCreateEligibility(Booking $booking): array
    {
        return $this->evaluateOptionPnrEligibility($booking, manual: true);
    }

    public function canAutoCreateForPublicBooking(Booking $booking): bool
    {
        return $this->evaluateOptionPnrEligibility($booking, manual: false)['eligible'];
    }

    /**
     * Automatic public-browser option PNR creation. Never throws; local booking stays active on failure.
     *
     * @return array{success: bool, summary: array<string, mixed>, customer_notice: ?string}
     */
    public function autoCreateOptionPnrForPublicBooking(Booking $booking): array
    {
        try {
            $eligibility = $this->evaluateOptionPnrEligibility($booking, manual: false);
            if (! $eligibility['eligible']) {
                return [
                    'success' => false,
                    'summary' => ['skipped' => true, 'reason' => $eligibility['reason']],
                    'customer_notice' => null,
                ];
            }

            $result = $this->executeOptionPnrCreate(
                booking: $booking,
                action: self::AUTO_ACTION,
                actor: null,
                source: 'public_booking_auto',
                requestContext: 'pia-ndc:public-auto-create-option-pnr',
            );

            if (($result['success'] ?? false) === true) {
                return [
                    'success' => true,
                    'summary' => is_array($result['summary'] ?? null) ? $result['summary'] : [],
                    'customer_notice' => null,
                ];
            }

            $this->persistAutoFailureMeta(
                $booking,
                (string) ($result['error_code'] ?? 'order_create_failed'),
                (string) ($result['error_message'] ?? 'PIA NDC option PNR creation did not succeed.'),
            );

            return [
                'success' => false,
                'summary' => is_array($result['summary'] ?? null) ? $result['summary'] : [],
                'customer_notice' => null,
            ];
        } catch (PiaNdcException $exception) {
            $this->persistAutoFailureMeta($booking, $exception->normalizedCode, $exception->safeMessage);

            return [
                'success' => false,
                'summary' => ['error_code' => $exception->normalizedCode],
                'customer_notice' => null,
            ];
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.auto_option_pnr.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);
            $this->persistAutoFailureMeta($booking, 'unexpected', 'PIA NDC option PNR creation failed.');

            return [
                'success' => false,
                'summary' => ['error_code' => 'unexpected'],
                'customer_notice' => null,
            ];
        }
    }

    /**
     * @return array{success: bool, summary: array<string, mixed>}
     */
    public function createOptionPnrForBooking(
        Booking $booking,
        User $actor,
        string $confirmPhrase,
        string $reason,
    ): array {
        $eligibility = $this->evaluateOptionPnrEligibility($booking, manual: true);
        if (! $eligibility['eligible']) {
            throw new PiaNdcValidationException('create_not_allowed', 422, $eligibility['reason']);
        }

        if ($confirmPhrase !== self::CREATE_CONFIRM_PHRASE) {
            throw new PiaNdcValidationException(
                'missing_confirmation',
                422,
                'Confirmation phrase must be exactly '.self::CREATE_CONFIRM_PHRASE.'.',
            );
        }

        $operatorReason = trim($reason);
        if ($operatorReason === '') {
            throw new PiaNdcValidationException('missing_operator_reason', 422, 'Operator reason is required.');
        }

        $result = $this->executeOptionPnrCreate(
            booking: $booking,
            action: self::MANUAL_ACTION,
            actor: $actor,
            source: 'admin_manual',
            requestContext: 'pia-ndc:admin-create-option-pnr',
            operatorReason: $operatorReason,
        );

        if (($result['success'] ?? false) !== true) {
            throw new PiaNdcValidationException(
                'order_create_failed',
                422,
                (string) ($result['error_message'] ?? 'PIA NDC option PNR creation did not succeed.'),
            );
        }

        return [
            'success' => true,
            'summary' => is_array($result['summary'] ?? null) ? $result['summary'] : [],
        ];
    }

    /**
     * @return array{eligible: bool, reason: string}
     */
    private function evaluateOptionPnrEligibility(Booking $booking, bool $manual): array
    {
        if (! $this->isPiaNdcBooking($booking)) {
            return ['eligible' => false, 'reason' => 'This booking is not a PIA NDC supplier booking.'];
        }

        if ($manual) {
            return [
                'eligible' => false,
                'reason' => 'PIA NDC option PNR is created automatically when the customer submits the booking request.',
            ];
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return ['eligible' => false, 'reason' => 'Cancelled bookings cannot receive a supplier option PNR.'];
        }

        if ($this->isTicketed($booking)) {
            return ['eligible' => false, 'reason' => 'Ticketing has already completed for this booking.'];
        }

        if (trim((string) ($booking->pnr ?? '')) !== '' || trim((string) ($booking->supplier_reference ?? '')) !== '') {
            return ['eligible' => false, 'reason' => 'PNR or supplier reference already exists for this booking.'];
        }

        if ($this->hasActiveSupplierBooking($booking)) {
            return ['eligible' => false, 'reason' => 'An active PIA NDC supplier booking already exists.'];
        }

        if ($this->hasSuccessfulCreateAttempt($booking)) {
            return ['eligible' => false, 'reason' => 'A successful PIA NDC option PNR attempt already exists for this booking.'];
        }

        if ($this->hasPendingCreateAttempt($booking)) {
            return ['eligible' => false, 'reason' => 'A PIA NDC option PNR attempt is already in progress.'];
        }

        if (! $this->providerContextResolver->hasResolvableContext($booking)) {
            return ['eligible' => false, 'reason' => 'PIA NDC offer context is missing from this booking.'];
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        if ($this->resolveBookingConnection($booking, $meta) === null) {
            return ['eligible' => false, 'reason' => 'No active PIA NDC supplier connection is available for this booking.'];
        }

        $passengerBlock = $this->passengerReadinessMessage($booking);
        if ($passengerBlock !== null) {
            return ['eligible' => false, 'reason' => $passengerBlock];
        }

        if (! PiaNdcFareFamilyPolicy::selectedIntentMatchesValidatedSnapshot($booking)) {
            return [
                'eligible' => false,
                'reason' => 'Selected fare family does not match the validated PIA NDC provider offer context.',
            ];
        }

        return ['eligible' => true, 'reason' => ''];
    }

    /**
     * @return array{
     *     success: bool,
     *     summary: array<string, mixed>,
     *     error_code?: string,
     *     error_message?: string
     * }
     */
    private function executeOptionPnrCreate(
        Booking $booking,
        string $action,
        ?User $actor,
        string $source,
        string $requestContext,
        ?string $operatorReason = null,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connection = $this->resolveBookingConnection($booking, $meta);
        if ($connection === null) {
            throw new PiaNdcValidationException('missing_connection', 422, 'PIA NDC supplier connection not found for this booking.');
        }

        if (! $connection->is_active) {
            throw new PiaNdcValidationException('connection_inactive', 422, 'Supplier connection is not active.');
        }

        $resolved = $this->providerContextResolver->resolve($booking);
        $providerContext = $resolved['context'];
        if ($providerContext === []) {
            throw new PiaNdcValidationException(
                'missing_provider_context',
                422,
                'PIA NDC offer context is missing from this booking.',
            );
        }

        $this->assertOfferNotExpired($providerContext);
        $this->assertSelectedBrandAlignsWithProviderContext($booking, $providerContext);

        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);
        $correlationId = Str::uuid()->toString();
        $attempt = $this->createPendingAttempt(
            booking: $booking,
            connection: $connection,
            action: $action,
            actor: $actor,
            source: $source,
            operatorReason: $operatorReason,
            contextSource: $resolved['source'],
            providerContext: $providerContext,
        );

        $diagnosticPath = null;
        $requestSummary = null;
        $responseSummary = null;

        try {
            $config = $this->configResolver->resolve($connection);
            $passengers = $this->xmlBuilder->buildPassengersFromBooking($booking);
            $contact = $this->xmlBuilder->buildContactFromBooking($booking);
            $requestXml = $this->xmlBuilder->buildOrderCreateRequest($config, $providerContext, $passengers, $contact);
            $sanitizedRequest = $this->client->sanitizeXmlForDiagnostics($requestXml);
            $requestSummary = $this->buildSanitizedRequestSummary($sanitizedRequest, $providerContext);

            $callContext = [
                'request_context' => $requestContext,
                'booking_id' => $booking->id,
                'correlation_id' => $correlationId,
            ];
            if ($actor !== null) {
                $callContext['user_id'] = $actor->id;
            }

            $parsedResponse = $this->client->call($connection, 'order_create', $requestXml, $callContext);
            $httpStatus = isset($parsedResponse['http_status']) ? (int) $parsedResponse['http_status'] : null;

            $normalized = $this->normalizer->normalizeBookingResponse($parsedResponse, $providerContext);
            $providerErrorCode = trim((string) ($parsedResponse['errors'][0]['code'] ?? ''));
            $providerErrorMessage = trim((string) ($parsedResponse['errors'][0]['message'] ?? ''));
            $orderId = trim((string) ($normalized['provider_booking_reference'] ?? $normalized['pnr'] ?? ''));
            $success = $providerErrorCode === '' && $orderId !== '';

            $sanitizedResponse = is_string($parsedResponse['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                : null;
            $responseSummary = $this->buildSanitizedResponseSummary($sanitizedResponse, $httpStatus, $parsedResponse);

            $safeSummary = $this->buildOptionPnrDiagnosticSummary(
                booking: $booking,
                connection: $connection,
                resolved: $resolved,
                providerContext: $providerContext,
                action: $action,
                source: $source,
                correlationId: $correlationId,
                httpStatus: $httpStatus,
                providerErrorCode: $providerErrorCode !== '' ? $providerErrorCode : null,
                providerErrorMessage: $providerErrorMessage !== '' ? $providerErrorMessage : null,
                success: $success,
            );

            if ($action === self::AUTO_ACTION) {
                $diagnosticPath = $this->saveAutoOptionPnrDiagnosticFiles(
                    $connection->id,
                    $correlationId,
                    $safeSummary,
                    $requestSummary,
                    $responseSummary,
                );
                $safeSummary['diagnostic_path'] = $diagnosticPath;
            }

            if ($success) {
                $this->persistSuccess($booking, $connection, $actor, $normalized, $safeSummary, $source, $operatorReason);
                $this->finalizeAttempt(
                    $attempt,
                    'success',
                    $safeSummary,
                    $this->payloadForAttempt($action, $requestSummary, $sanitizedRequest),
                    $this->payloadForAttempt($action, $responseSummary, $sanitizedResponse),
                    $orderId,
                    null,
                    null,
                );

                return [
                    'success' => true,
                    'summary' => $safeSummary,
                ];
            }

            $errorMessage = $providerErrorMessage !== ''
                ? $providerErrorMessage
                : 'PIA NDC option PNR creation did not succeed.';
            $this->finalizeAttempt(
                $attempt,
                'failed',
                $safeSummary,
                $this->payloadForAttempt($action, $requestSummary, $sanitizedRequest),
                $this->payloadForAttempt($action, $responseSummary, $sanitizedResponse),
                null,
                $providerErrorCode !== '' ? $providerErrorCode : 'order_create_failed',
                $errorMessage,
            );

            return [
                'success' => false,
                'summary' => $safeSummary,
                'error_code' => $providerErrorCode !== '' ? $providerErrorCode : 'order_create_failed',
                'error_message' => $errorMessage,
            ];
        } catch (PiaNdcException $exception) {
            $httpStatus = isset($exception->context['http_status']) ? (int) $exception->context['http_status'] : null;
            if ($responseSummary === null && is_string($exception->context['response_xml'] ?? null)) {
                $sanitizedErrorResponse = $this->client->sanitizeXmlForDiagnostics($exception->context['response_xml']);
                $responseSummary = $this->buildSanitizedResponseSummary($sanitizedErrorResponse, $httpStatus, null);
            }
            $safeSummary = $this->buildOptionPnrDiagnosticSummary(
                booking: $booking,
                connection: $connection,
                resolved: $resolved,
                providerContext: $providerContext,
                action: $action,
                source: $source,
                correlationId: $correlationId,
                httpStatus: $httpStatus,
                providerErrorCode: $exception->normalizedCode,
                providerErrorMessage: $exception->safeMessage,
                success: false,
            );
            if ($action === self::AUTO_ACTION) {
                $diagnosticPath = $this->saveAutoOptionPnrDiagnosticFiles(
                    $connection->id,
                    $correlationId,
                    $safeSummary,
                    $requestSummary,
                    $responseSummary,
                );
                $safeSummary['diagnostic_path'] = $diagnosticPath;
            }

            $this->finalizeAttempt(
                $attempt,
                'failed',
                $safeSummary,
                $this->payloadForAttempt($action, $requestSummary, null),
                $this->payloadForAttempt($action, $responseSummary, is_string($exception->context['response_xml'] ?? null) ? $exception->context['response_xml'] : null),
                null,
                $exception->normalizedCode,
                $exception->safeMessage,
            );

            throw $exception;
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.option_pnr.unexpected', [
                'booking_id' => $booking->id,
                'action' => $action,
                'exception' => $exception::class,
            ]);

            $safeSummary = $this->buildOptionPnrDiagnosticSummary(
                booking: $booking,
                connection: $connection,
                resolved: $resolved,
                providerContext: $providerContext,
                action: $action,
                source: $source,
                correlationId: $correlationId,
                providerErrorCode: 'unexpected',
                providerErrorMessage: 'PIA NDC option PNR creation failed.',
                success: false,
            );
            if ($action === self::AUTO_ACTION) {
                $diagnosticPath = $this->saveAutoOptionPnrDiagnosticFiles(
                    $connection->id,
                    $correlationId,
                    $safeSummary,
                    $requestSummary,
                    $responseSummary,
                );
                $safeSummary['diagnostic_path'] = $diagnosticPath;
            }

            $this->finalizeAttempt(
                $attempt,
                'failed',
                $safeSummary,
                $this->payloadForAttempt($action, $requestSummary, null),
                $this->payloadForAttempt($action, $responseSummary, null),
                null,
                'unexpected',
                'PIA NDC option PNR creation failed.',
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function resolveBookingConnection(Booking $booking, array $meta): ?SupplierConnection
    {
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId > 0) {
            $connection = SupplierConnection::query()
                ->where('id', $connectionId)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
            if ($connection !== null && $connection->is_active) {
                return $connection;
            }
        }

        foreach (['validated_offer_snapshot', 'flight_offer_snapshot', 'normalized_offer_snapshot'] as $key) {
            $snapshot = is_array($meta[$key] ?? null) ? $meta[$key] : [];
            $snapshotConnectionId = (int) ($snapshot['supplier_connection_id'] ?? 0);
            if ($snapshotConnectionId > 0) {
                $connection = SupplierConnection::query()
                    ->where('id', $snapshotConnectionId)
                    ->where('provider', SupplierProvider::PiaNdc)
                    ->first();
                if ($connection !== null && $connection->is_active) {
                    return $connection;
                }
            }
        }

        $booking->loadMissing('latestSupplierBooking.supplierConnection');
        $latest = $booking->latestSupplierBooking?->supplierConnection;
        if ($latest !== null && $latest->provider === SupplierProvider::PiaNdc && $latest->is_active) {
            return $latest;
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

    private function isPiaNdcBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::PiaNdc->value;
    }

    private function isTicketed(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::Ticketed) {
            return true;
        }

        $ticketingStatus = strtolower(trim((string) ($booking->ticketing_status ?? '')));

        return in_array($ticketingStatus, ['ticketed', 'issued', 'completed'], true);
    }

    private function hasActiveSupplierBooking(Booking $booking): bool
    {
        return $booking->supplierBookings()
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->whereIn('status', self::ACTIVE_SUPPLIER_BOOKING_STATUSES)
            ->exists();
    }

    private function hasSuccessfulCreateAttempt(Booking $booking): bool
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->whereIn('action', self::CREATE_ACTIONS)
            ->whereIn('status', ['success', 'succeeded'])
            ->exists();
    }

    private function hasPendingCreateAttempt(Booking $booking): bool
    {
        $pending = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->whereIn('action', self::CREATE_ACTIONS)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if ($pending === null) {
            return false;
        }

        $attemptedAt = $pending->attempted_at ?? $pending->created_at;
        if ($attemptedAt === null) {
            return true;
        }

        return $attemptedAt->greaterThan(now()->subMinutes(10));
    }

    private function passengerReadinessMessage(Booking $booking): ?string
    {
        $booking->loadMissing(['passengers', 'contact']);
        if ($booking->passengers->isEmpty()) {
            return 'Passenger details are required before creating a PIA NDC option PNR.';
        }

        $email = trim((string) ($booking->contact?->email ?? $booking->contact_email ?? ''));
        if ($email === '') {
            return 'Contact email is required before creating a PIA NDC option PNR.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function assertSelectedBrandAlignsWithProviderContext(Booking $booking, array $providerContext): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $selectedCtx = PiaNdcFareFamilyPolicy::extractProviderContextFromSelected($selected, $validated);

        if ($selectedCtx === [] || ! PiaNdcFareFamilyPolicy::hasOrderCreateReadyContext($selectedCtx)) {
            return;
        }

        if (! PiaNdcFareFamilyPolicy::providerContextsAlign($selectedCtx, $providerContext)) {
            throw new PiaNdcValidationException(
                'brand_context_mismatch',
                422,
                'Selected fare family provider context does not match the booking offer context.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function assertOfferNotExpired(array $providerContext): void
    {
        $paymentTimeLimit = trim((string) ($providerContext['payment_time_limit'] ?? ''));
        if ($paymentTimeLimit === '') {
            return;
        }

        try {
            if (Carbon::parse($paymentTimeLimit)->isPast()) {
                throw new PiaNdcValidationException(
                    'offer_expired',
                    422,
                    'Selected offer payment time limit has expired.',
                );
            }
        } catch (PiaNdcValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PiaNdcValidationException(
                'invalid_payment_time_limit',
                422,
                'Selected offer payment time limit is invalid.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function createPendingAttempt(
        Booking $booking,
        SupplierConnection $connection,
        string $action,
        ?User $actor,
        string $source,
        ?string $operatorReason,
        string $contextSource,
        array $providerContext,
    ): SupplierBookingAttempt {
        return SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'action' => $action,
            'status' => 'pending',
            'safe_summary' => SensitiveDataRedactor::redact([
                'action' => $action,
                'source' => $source,
                'operator_reason' => $operatorReason,
                'provider_context_source' => $contextSource,
                'owner_code' => $providerContext['owner_code'] ?? null,
            ]),
            'attempted_by' => $actor?->id,
            'attempted_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $safeSummary
     */
    private function finalizeAttempt(
        SupplierBookingAttempt $attempt,
        string $status,
        array $safeSummary,
        mixed $requestPayload,
        mixed $responsePayload,
        ?string $supplierReference,
        ?string $errorCode,
        ?string $errorMessage,
    ): void {
        $attempt->forceFill([
            'status' => $status,
            'safe_summary' => $safeSummary,
            'request_payload' => $this->normalizeAttemptPayload($requestPayload),
            'response_payload' => $this->normalizeAttemptPayload($responsePayload),
            'supplier_reference' => $supplierReference,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ])->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeAttemptPayload(mixed $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (is_array($payload)) {
            return SensitiveDataRedactor::redact($payload);
        }

        if (is_string($payload) && $payload !== '') {
            return ['xml' => $payload];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    private function payloadForAttempt(string $action, ?array $summary, ?string $sanitizedXml): ?array
    {
        if ($action === self::AUTO_ACTION) {
            if (is_array($summary) && $summary !== []) {
                return $summary;
            }

            if (is_string($sanitizedXml) && $sanitizedXml !== '') {
                return [
                    'xml_length' => strlen($sanitizedXml),
                    'sanitized_xml_present' => true,
                ];
            }

            return null;
        }

        return is_string($sanitizedXml) && $sanitizedXml !== '' ? ['xml' => $sanitizedXml] : null;
    }

    /**
     * @param  array{source: string, context: array<string, mixed>}  $resolved
     * @param  array<string, mixed>  $providerContext
     * @return array<string, mixed>
     */
    private function buildOptionPnrDiagnosticSummary(
        Booking $booking,
        SupplierConnection $connection,
        array $resolved,
        array $providerContext,
        string $action,
        string $source,
        string $correlationId,
        ?int $httpStatus = null,
        ?string $providerErrorCode = null,
        ?string $providerErrorMessage = null,
        ?bool $success = null,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $validated = is_array($meta['validated_offer_snapshot'] ?? null) ? $meta['validated_offer_snapshot'] : [];
        $selected = is_array($meta['selected_fare_family_option'] ?? null) ? $meta['selected_fare_family_option'] : [];
        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $offerRefId = trim((string) ($providerContext['offer_ref_id'] ?? ''));
        $shoppingRef = trim((string) ($providerContext['shopping_response_ref_id'] ?? ''));

        return SensitiveDataRedactor::redact([
            'action' => $action,
            'booking_reference' => $booking->booking_reference,
            'booking_id' => $booking->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'source' => $source,
            'supplier_connection_id' => $connection->id,
            'selected_offer_id' => trim((string) ($meta['checkout_offer_id'] ?? $booking->flight_offer_id ?? '')),
            'selected_fare_family_option' => [
                'name' => $selected['name'] ?? null,
                'displayed_price' => $selected['displayed_price'] ?? null,
            ],
            'validated_offer_snapshot' => [
                'fare_family' => $validated['fare_family'] ?? null,
            ],
            'provider_context' => [
                'fare_type_code' => $providerContext['fare_type_code'] ?? null,
                'fare_basis' => $providerContext['fare_basis'] ?? null,
                'rbd' => $providerContext['rbd'] ?? null,
            ],
            'provider_context_source' => $resolved['source'] ?? null,
            'fare_family_alignment_ok' => PiaNdcFareFamilyPolicy::selectedIntentMatchesValidatedSnapshot($booking),
            'selected_brand_alignment' => PiaNdcFareFamilyPolicy::brandAlignmentDiagnostic($booking),
            'offer_ref_id_present' => $offerRefId !== '',
            'offer_ref_id_length' => strlen($offerRefId),
            'shopping_response_ref_id_present' => $shoppingRef !== '',
            'shopping_response_ref_id_short' => $shoppingRef !== '' ? substr($shoppingRef, 0, 8) : null,
            'route' => trim((string) ($criteria['origin'] ?? '')).'-'.trim((string) ($criteria['destination'] ?? '')),
            'depart_date' => $criteria['depart_date'] ?? null,
            'flight_number' => $this->firstFlightNumberFromSnapshot($validated),
            'passenger_count' => $booking->passengers->count(),
            'http_status' => $httpStatus,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'correlation_id' => $correlationId,
            'success' => $success,
        ]);
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @return array<string, mixed>|null
     */
    private function buildSanitizedRequestSummary(?string $sanitizedXml, array $providerContext): ?array
    {
        if ($sanitizedXml === null) {
            return null;
        }

        return SensitiveDataRedactor::redact([
            'xml_length' => strlen($sanitizedXml),
            'offer_ref_id_length' => strlen(trim((string) ($providerContext['offer_ref_id'] ?? ''))),
            'shopping_response_ref_id_present' => trim((string) ($providerContext['shopping_response_ref_id'] ?? '')) !== '',
            'fare_type_code' => $providerContext['fare_type_code'] ?? null,
            'fare_basis' => $providerContext['fare_basis'] ?? null,
            'rbd' => $providerContext['rbd'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $parsedResponse
     * @return array<string, mixed>|null
     */
    private function buildSanitizedResponseSummary(?string $sanitizedXml, ?int $httpStatus, ?array $parsedResponse): ?array
    {
        if ($sanitizedXml === null && $httpStatus === null && $parsedResponse === null) {
            return null;
        }

        $errors = is_array($parsedResponse['errors'] ?? null) ? $parsedResponse['errors'] : [];
        $firstError = is_array($errors[0] ?? null) ? $errors[0] : [];

        return SensitiveDataRedactor::redact([
            'xml_length' => is_string($sanitizedXml) ? strlen($sanitizedXml) : null,
            'http_status' => $httpStatus,
            'provider_error_code' => $firstError['code'] ?? null,
            'provider_error_message' => $firstError['message'] ?? null,
            'order_status' => $parsedResponse['order_status'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>|null  $requestSummary
     * @param  array<string, mixed>|null  $responseSummary
     */
    private function saveAutoOptionPnrDiagnosticFiles(
        int $connectionId,
        string $correlationId,
        array $summary,
        ?array $requestSummary,
        ?array $responseSummary,
    ): string {
        $directory = storage_path('app/diagnostics/pia-ndc/auto-option-pnr/'.$connectionId.'/'.$correlationId);
        File::ensureDirectoryExists($directory);
        file_put_contents(
            $directory.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if ($requestSummary !== null) {
            file_put_contents(
                $directory.'/request_summary.json',
                json_encode($requestSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }
        if ($responseSummary !== null) {
            file_put_contents(
                $directory.'/response_summary.json',
                json_encode($responseSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function firstFlightNumberFromSnapshot(array $snapshot): ?string
    {
        $segments = is_array($snapshot['segments'] ?? null) ? $snapshot['segments'] : [];
        foreach ($segments as $segment) {
            if (! is_array($segment)) {
                continue;
            }
            $flight = trim((string) ($segment['flight_number'] ?? $segment['marketing_flight_number'] ?? ''));
            if ($flight !== '') {
                return $flight;
            }
        }

        return null;
    }

    private function persistAutoFailureMeta(Booking $booking, ?string $errorCode, string $safeMessage): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $meta['pia_ndc_auto_option_pnr'] = [
            'status' => 'failed',
            'error_code' => $errorCode,
            'safe_message' => $safeMessage,
            'attempted_at' => now()->toIso8601String(),
        ];
        $booking->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $safeSummary
     */
    private function persistSuccess(
        Booking $booking,
        SupplierConnection $connection,
        ?User $actor,
        array $normalized,
        array $safeSummary,
        string $source,
        ?string $operatorReason,
    ): void {
        DB::transaction(function () use ($booking, $connection, $actor, $normalized, $safeSummary, $source, $operatorReason): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $piaContext = array_merge(
                is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [],
                is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
            );
            $piaContext['order_id'] = (string) ($normalized['provider_booking_reference'] ?? $piaContext['order_id'] ?? '');
            $piaContext['airline_locator'] = (string) ($normalized['airline_locator'] ?? $piaContext['airline_locator'] ?? '');
            $piaContext['order_status'] = (string) ($normalized['order_status'] ?? $piaContext['order_status'] ?? 'option');
            $piaContext['option_pnr_created_at'] = now()->toIso8601String();
            if ($actor !== null) {
                $piaContext['option_pnr_created_by'] = $actor->id;
            }

            $paymentTimeLimit = trim((string) ($normalized['payment_time_limit'] ?? ''));
            $paymentRequiredBy = null;
            $pnrExpiresAt = null;
            if ($paymentTimeLimit !== '') {
                try {
                    $paymentRequiredBy = Carbon::parse($paymentTimeLimit);
                    $pnrExpiresAt = $paymentRequiredBy->copy();
                } catch (Throwable) {
                    $paymentRequiredBy = null;
                    $pnrExpiresAt = null;
                }
            }

            $orderId = trim((string) ($normalized['provider_booking_reference'] ?? ''));
            $pnr = trim((string) ($normalized['pnr'] ?? $orderId));

            $meta['supplier_provider'] = SupplierProvider::PiaNdc->value;
            $meta['supplier_connection_id'] = $connection->id;
            $meta['pia_ndc_context'] = $piaContext;
            $meta['pia_ndc_order_create_summary'] = array_merge($safeSummary, [
                'source' => $source,
                'operator_reason' => $operatorReason,
                'created_at' => now()->toIso8601String(),
            ]);
            if ($source === 'public_booking_auto') {
                $meta['pia_ndc_auto_option_pnr'] = [
                    'status' => 'success',
                    'pnr' => $pnr !== '' ? $pnr : null,
                    'order_id' => $orderId !== '' ? $orderId : null,
                    'attempted_at' => now()->toIso8601String(),
                ];
            }

            $booking->forceFill([
                'pnr' => $pnr !== '' ? $pnr : null,
                'supplier_reference' => $orderId !== '' ? $orderId : null,
                'supplier_api_booking_id' => $orderId !== '' ? $orderId : null,
                'supplier_booking_status' => 'option_pnr_created',
                'supplier_booking_created_at' => now(),
                'payment_required_by' => $paymentRequiredBy,
                'pnr_expires_at' => $pnrExpiresAt,
                'meta' => $meta,
            ])->save();

            SupplierBooking::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'provider' => SupplierProvider::PiaNdc->value,
                ],
                [
                    'agency_id' => $booking->agency_id,
                    'supplier_connection_id' => $connection->id,
                    'supplier_reference' => $orderId !== '' ? $orderId : null,
                    'supplier_api_booking_id' => $orderId !== '' ? $orderId : null,
                    'pnr' => $pnr !== '' ? $pnr : null,
                    'status' => 'pending_payment_or_ticketing',
                    'raw_summary' => SensitiveDataRedactor::redact([
                        'source' => $source,
                        'order_id' => $orderId,
                        'pnr' => $pnr,
                        'airline_locator' => $normalized['airline_locator'] ?? null,
                        'order_status' => $normalized['order_status'] ?? null,
                        'payment_time_limit' => $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
                        'operator_reason' => $operatorReason,
                    ]),
                    'created_by' => $actor?->id,
                    'created_at_supplier' => now(),
                ],
            );
        });
    }
}
