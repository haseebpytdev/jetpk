<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcBookingException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use App\Support\Security\SensitiveDataRedactor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PIA NDC booking orchestrator: DoOrderCreate option PNR with duplicate guards.
 *
 * Hitit OfferPrice may return zero/fee-only totals — OrderCreate diagnostic uses AirShopping
 * provider_context and treats the supplier OrderCreate response as binding validation.
 */
class PiaNdcBookingService
{
    private const ORDER_CREATE_CONFIRM_PHRASE = 'CREATE_OPTION_PNR';

    private const EXECUTION_LOCK_MINUTES = 10;

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcCorrelationContext $correlationContext,
        private readonly PiaNdcDiagnosticService $diagnosticService,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        if ($connection->provider !== SupplierProvider::PiaNdc) {
            return $this->failure('supplier_provider_mismatch', 'Supplier provider mismatch for PIA NDC booking.', $connection);
        }

        if ($this->hasSuccessfulCreateAttempt($booking)) {
            return $this->failure('duplicate_booking_guard', 'A PIA NDC booking already exists for this reservation.', $connection);
        }

        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $piaContext = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $providerContext = $this->resolveProviderContext($booking, $meta);
        $config = $this->configResolver->resolve($connection);
        $existingOrderId = trim((string) ($piaContext['order_id'] ?? $booking->supplier_reference ?? ''));

        if ($existingOrderId !== '') {
            return $this->failure('duplicate_booking_guard', 'This booking already has a PIA NDC order.', $connection);
        }

        try {
            $passengers = $this->xmlBuilder->buildPassengersFromBooking($booking);
            $contact = $this->xmlBuilder->buildContactFromBooking($booking);
            $xml = $this->xmlBuilder->buildOrderCreateRequest($config, $providerContext, $passengers, $contact);
            $response = $this->client->call($connection, 'order_create', $xml, [
                'request_context' => 'order_create',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);
            $normalized = $this->normalizer->normalizeBookingResponse($response, $providerContext);
            $this->persistBookingState($booking, $connection, $normalized, $actor);

            return new SupplierBookingResultData(
                success: true,
                status: 'confirmed',
                provider: SupplierProvider::PiaNdc->value,
                supplier_reference: (string) ($normalized['provider_booking_reference'] ?? ''),
                pnr: (string) ($normalized['pnr'] ?? ''),
                response_payload: ['provider_context' => $normalized['provider_context'] ?? []],
            );
        } catch (PiaNdcException $exception) {
            $this->recordAttempt($booking, $connection, $actor, 'failed', $exception->normalizedCode);

            return $this->failure($exception->normalizedCode, $exception->safeMessage, $connection);
        } catch (Throwable $exception) {
            Log::channel('pia-ndc')->warning('pia_ndc.booking.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);
            $this->recordAttempt($booking, $connection, $actor, 'failed', 'unexpected');

            throw new PiaNdcBookingException(
                'booking_unexpected',
                500,
                'Booking unavailable.',
                ['booking_id' => $booking->id],
                $exception,
            );
        }
    }

    /**
     * CLI-only DoOrderCreate option PNR diagnostic (dry-run by default).
     *
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $passengerInput
     * @return array{
     *     success: bool,
     *     diagnostic_path: string,
     *     summary: array<string, mixed>
     * }
     */
    public function runOrderCreateDiagnostic(
        SupplierConnection $connection,
        array $providerContext,
        array $passengerInput,
        int $selectedOfferIndex = 0,
        ?string $selectedPublicOfferId = null,
        ?float $selectedSupplierTotal = null,
        ?string $currency = null,
        ?string $sourceDiagnosticPath = null,
        bool $executeOptionPnr = false,
        ?string $confirmPhrase = null,
    ): array {
        if ($connection->provider !== SupplierProvider::PiaNdc) {
            throw new PiaNdcValidationException('supplier_provider_mismatch', 422, 'Supplier connection is not PIA NDC.');
        }

        $this->assertOrderCreateProviderContext($providerContext);
        $this->validateDiagnosticPassengerInput($passengerInput);

        if ($executeOptionPnr) {
            $this->assertExecuteGuards(
                connection: $connection,
                providerContext: $providerContext,
                passengerInput: $passengerInput,
                confirmPhrase: $confirmPhrase,
                sourceDiagnosticPath: $sourceDiagnosticPath,
                selectedOfferIndex: $selectedOfferIndex,
            );
        }

        $config = $this->configResolver->resolve($connection);
        $correlationId = $this->correlationContext->newCorrelationId();
        $paxRefId = trim((string) ($providerContext['pax_ref_id'] ?? 'ADTPax-1'));
        $passengers = $this->xmlBuilder->buildDiagnosticPassengers($passengerInput, $paxRefId);
        $contact = $this->xmlBuilder->buildDiagnosticContact($passengerInput);
        $requestXml = $this->xmlBuilder->buildOrderCreateRequest($config, $providerContext, $passengers, $contact);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);

        $supplierCalled = false;
        $httpStatus = null;
        $responseXml = null;
        $normalizedResponse = null;
        $providerErrorCode = null;
        $providerErrorMessage = null;
        $executionLockPath = null;

        if ($executeOptionPnr) {
            $executionLockPath = $this->acquireExecutionLock(
                $sourceDiagnosticPath,
                $selectedOfferIndex,
                (string) ($passengerInput['passport_number'] ?? ''),
            );
            try {
                $supplierCalled = true;
                $parsedResponse = $this->client->call($connection, 'order_create', $requestXml, [
                    'request_context' => 'pia-ndc:order-create-diagnostic',
                    'correlation_id' => $correlationId,
                ]);
                $diagnostic = is_array($parsedResponse['_ota_diagnostic'] ?? null) ? $parsedResponse['_ota_diagnostic'] : [];
                $correlationId = (string) ($diagnostic['correlation_id'] ?? $correlationId);
                $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
                $responseXml = is_string($parsedResponse['raw_xml'] ?? null)
                    ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                    : null;
                $normalizedResponse = $this->normalizer->normalizeBookingResponse($parsedResponse, $providerContext);
                if (($parsedResponse['errors'][0]['code'] ?? '') !== '') {
                    $providerErrorCode = (string) $parsedResponse['errors'][0]['code'];
                    $providerErrorMessage = (string) ($parsedResponse['errors'][0]['message'] ?? '');
                }
            } catch (PiaNdcException $exception) {
                $safeMeta = $exception->safeDiagnosticMeta('order_create');
                $correlationId = (string) ($safeMeta['correlation_id'] ?? $correlationId);
                $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
                $providerErrorCode = $exception->normalizedCode;
                $providerErrorMessage = $exception->safeMessage;
                $responseXml = is_string($exception->context['response_xml'] ?? null)
                    ? $exception->context['response_xml']
                    : null;
                if ($responseXml !== null) {
                    try {
                        $parsedResponse = $this->xmlParser->parse($responseXml);
                        $normalizedResponse = $this->normalizer->normalizeBookingResponse($parsedResponse, $providerContext);
                    } catch (Throwable) {
                        $normalizedResponse = null;
                    }
                }
            } finally {
                if ($executionLockPath !== null && is_file($executionLockPath)) {
                    @unlink($executionLockPath);
                }
            }
        }

        $evaluation = $this->evaluateOrderCreateDiagnosticOutcome(
            executeOptionPnr: $executeOptionPnr,
            supplierCalled: $supplierCalled,
            httpStatus: $httpStatus,
            providerErrorCode: $providerErrorCode,
            normalizedResponse: $normalizedResponse,
        );

        $normalizedInput = [
            'provider_context' => SensitiveDataRedactor::redact($providerContext),
            'passenger' => SensitiveDataRedactor::redact($passengerInput),
            'selected_offer_index' => $selectedOfferIndex,
            'selected_public_offer_id' => $selectedPublicOfferId,
            'selected_supplier_total' => $selectedSupplierTotal,
            'currency' => $currency,
            'execute_option_pnr' => $executeOptionPnr,
        ];

        $summary = [
            'connection_id' => $connection->id,
            'endpoint' => (string) $config['endpoint_url'],
            'correlation_id' => $correlationId,
            'source_air_shopping_diagnostic_path' => $sourceDiagnosticPath,
            'selected_offer_index' => $selectedOfferIndex,
            'selected_public_offer_id' => $selectedPublicOfferId,
            'selected_supplier_total' => $selectedSupplierTotal,
            'currency' => $currency ?? $config['currency'],
            'dry_run' => ! $executeOptionPnr,
            'supplier_called' => $supplierCalled,
            'execute_option_pnr' => $executeOptionPnr,
            'passenger_masked' => $this->maskPassengerSummary($passengerInput),
            'http_status' => $httpStatus,
            'provider_error_code' => $providerErrorCode,
            'provider_error_message' => $providerErrorMessage,
            'order_id' => $normalizedResponse['provider_booking_reference'] ?? null,
            'pnr' => $normalizedResponse['pnr'] ?? null,
            'booking_reference' => $normalizedResponse['booking_reference'] ?? null,
            'airline_locator' => $normalizedResponse['airline_locator'] ?? null,
            'order_status' => $normalizedResponse['order_status'] ?? null,
            'payment_time_limit' => $normalizedResponse['payment_time_limit']
                ?? ($providerContext['payment_time_limit'] ?? null),
            'success' => $evaluation['success'],
        ];

        $diagnosticPath = $this->saveOrderCreateDiagnosticFiles(
            connectionId: $connection->id,
            correlationId: $correlationId,
            requestXml: $sanitizedRequestXml,
            responseXml: $responseXml,
            normalizedInput: $normalizedInput,
            normalizedResponse: $normalizedResponse,
            summary: $summary,
        );
        $summary['diagnostic_path'] = $diagnosticPath;

        return [
            'success' => $evaluation['success'],
            'diagnostic_path' => $diagnosticPath,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $providerContext
     */
    private function assertOrderCreateProviderContext(array $providerContext): void
    {
        if (trim((string) ($providerContext['offer_ref_id'] ?? '')) === '') {
            throw new PiaNdcValidationException('missing_provider_context', 422, 'Selected offer is missing raw offer_ref_id.');
        }
        if (trim((string) ($providerContext['offer_item_ref_id'] ?? '')) === '') {
            throw new PiaNdcValidationException('missing_provider_context', 422, 'Selected offer is missing offer_item_ref_id.');
        }
    }

    /**
     * @param  array<string, mixed>  $passengerInput
     */
    private function validateDiagnosticPassengerInput(array $passengerInput): void
    {
        $required = [
            'given_name' => 'given-name',
            'surname' => 'surname',
            'title' => 'title',
            'gender' => 'gender',
            'dob' => 'dob',
            'nationality' => 'nationality',
            'passport_number' => 'passport-number',
            'passport_expiry' => 'passport-expiry',
            'email' => 'email',
            'phone' => 'phone',
        ];

        foreach ($required as $key => $label) {
            if (trim((string) ($passengerInput[$key] ?? '')) === '') {
                throw new PiaNdcValidationException(
                    'missing_passenger_field',
                    422,
                    'Passenger field --'.$label.' is required.',
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $providerContext
     * @param  array<string, mixed>  $passengerInput
     */
    private function assertExecuteGuards(
        SupplierConnection $connection,
        array $providerContext,
        array $passengerInput,
        ?string $confirmPhrase,
        ?string $sourceDiagnosticPath,
        int $selectedOfferIndex,
    ): void {
        if ($confirmPhrase !== self::ORDER_CREATE_CONFIRM_PHRASE) {
            throw new PiaNdcValidationException(
                'missing_confirmation',
                422,
                'Execute requires --confirm="'.self::ORDER_CREATE_CONFIRM_PHRASE.'".',
            );
        }

        if (! $connection->is_active) {
            throw new PiaNdcValidationException('connection_inactive', 422, 'Supplier connection is not active.');
        }

        $health = $this->diagnosticService->healthCheck($connection);
        if (! ($health['healthy'] ?? false)) {
            throw new PiaNdcValidationException(
                'connection_unhealthy',
                422,
                'Supplier connection failed health check.',
            );
        }

        $paymentTimeLimit = trim((string) ($providerContext['payment_time_limit'] ?? ''));
        if ($paymentTimeLimit !== '') {
            try {
                if (Carbon::parse($paymentTimeLimit)->isPast()) {
                    throw new PiaNdcValidationException(
                        'offer_expired',
                        422,
                        'Selected offer payment_time_limit has expired.',
                    );
                }
            } catch (PiaNdcValidationException $exception) {
                throw $exception;
            } catch (Throwable) {
                throw new PiaNdcValidationException(
                    'invalid_payment_time_limit',
                    422,
                    'Selected offer payment_time_limit is invalid.',
                );
            }
        }

        $this->assertRecentDuplicateExecution(
            $sourceDiagnosticPath,
            $selectedOfferIndex,
            (string) ($passengerInput['passport_number'] ?? ''),
        );
    }

    private function acquireExecutionLock(?string $sourceDiagnosticPath, int $offerIndex, string $passportNumber): string
    {
        $hash = hash('sha256', implode('|', [
            (string) $sourceDiagnosticPath,
            (string) $offerIndex,
            $passportNumber,
        ]));
        $directory = storage_path('app/diagnostics/pia-ndc/order-create-locks');
        File::ensureDirectoryExists($directory);
        $lockPath = $directory.'/'.$hash.'.lock';

        if (is_file($lockPath)) {
            $ageMinutes = (time() - (int) filemtime($lockPath)) / 60;
            if ($ageMinutes < self::EXECUTION_LOCK_MINUTES) {
                throw new PiaNdcValidationException(
                    'duplicate_execution_guard',
                    422,
                    'A recent OrderCreate diagnostic execution exists for this offer/passenger.',
                );
            }
            @unlink($lockPath);
        }

        file_put_contents($lockPath, (string) now()->toIso8601String());

        return $lockPath;
    }

    private function assertRecentDuplicateExecution(?string $sourceDiagnosticPath, int $offerIndex, string $passportNumber): void
    {
        $hash = hash('sha256', implode('|', [
            (string) $sourceDiagnosticPath,
            (string) $offerIndex,
            $passportNumber,
        ]));
        $lockPath = storage_path('app/diagnostics/pia-ndc/order-create-locks/'.$hash.'.lock');
        if (is_file($lockPath) && ((time() - (int) filemtime($lockPath)) / 60) < self::EXECUTION_LOCK_MINUTES) {
            throw new PiaNdcValidationException(
                'duplicate_execution_guard',
                422,
                'A recent OrderCreate diagnostic execution exists for this offer/passenger.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $passengerInput
     * @return array<string, string>
     */
    private function maskPassengerSummary(array $passengerInput): array
    {
        return [
            'given_name' => $this->maskToken((string) ($passengerInput['given_name'] ?? '')),
            'surname' => $this->maskToken((string) ($passengerInput['surname'] ?? '')),
            'passport_number' => $this->maskToken((string) ($passengerInput['passport_number'] ?? '')),
            'email' => $this->maskToken((string) ($passengerInput['email'] ?? '')),
            'phone' => $this->maskToken((string) ($passengerInput['phone'] ?? '')),
        ];
    }

    private function maskToken(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        if (strlen($trimmed) <= 2) {
            return str_repeat('*', strlen($trimmed));
        }

        return substr($trimmed, 0, 2).str_repeat('*', max(1, strlen($trimmed) - 4)).substr($trimmed, -2);
    }

    /**
     * @param  ?array<string, mixed>  $normalizedResponse
     * @return array{success: bool}
     */
    private function evaluateOrderCreateDiagnosticOutcome(
        bool $executeOptionPnr,
        bool $supplierCalled,
        ?int $httpStatus,
        ?string $providerErrorCode,
        ?array $normalizedResponse,
    ): array {
        if (! $executeOptionPnr) {
            return ['success' => true];
        }

        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $orderId = trim((string) ($normalizedResponse['provider_booking_reference'] ?? $normalizedResponse['pnr'] ?? ''));

        return [
            'success' => $supplierCalled
                && $httpOk
                && $providerErrorCode === null
                && $orderId !== '',
        ];
    }

    /**
     * @param  array<string, mixed>  $normalizedInput
     * @param  ?array<string, mixed>  $normalizedResponse
     * @param  array<string, mixed>  $summary
     */
    private function saveOrderCreateDiagnosticFiles(
        int $connectionId,
        string $correlationId,
        string $requestXml,
        ?string $responseXml,
        array $normalizedInput,
        ?array $normalizedResponse,
        array $summary,
    ): string {
        $directory = storage_path('app/diagnostics/pia-ndc/order-create/'.$connectionId.'/'.$correlationId);
        File::ensureDirectoryExists($directory);

        file_put_contents($directory.'/request.xml', $requestXml);
        file_put_contents(
            $directory.'/normalized_input.json',
            json_encode(SensitiveDataRedactor::redact($normalizedInput), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        file_put_contents(
            $directory.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        if ($responseXml !== null) {
            file_put_contents($directory.'/response.xml', $responseXml);
        }
        if ($normalizedResponse !== null) {
            file_put_contents(
                $directory.'/normalized_response.json',
                json_encode(SensitiveDataRedactor::redact($normalizedResponse), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        return $directory;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function resolveProviderContext(Booking $booking, array $meta): array
    {
        $fromMeta = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $snapshot = is_array($booking->offer_snapshot) ? $booking->offer_snapshot : [];
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $fromSnapshot = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];

        return array_merge($fromSnapshot, $fromMeta);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function persistBookingState(Booking $booking, SupplierConnection $connection, array $normalized, User $actor): void
    {
        DB::transaction(function () use ($booking, $connection, $normalized, $actor): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $piaContext = array_merge(
                is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [],
                is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
            );
            $meta['supplier_provider'] = SupplierProvider::PiaNdc->value;
            $meta['supplier_connection_id'] = $connection->id;
            $meta['pia_ndc_context'] = $piaContext;
            $booking->meta = $meta;
            $booking->supplier_reference = (string) ($normalized['pnr'] ?? $booking->supplier_reference);
            $booking->save();

            SupplierBooking::query()->updateOrCreate(
                ['booking_id' => $booking->id, 'provider' => SupplierProvider::PiaNdc->value],
                [
                    'supplier_connection_id' => $connection->id,
                    'provider_reference' => (string) ($normalized['provider_booking_reference'] ?? ''),
                    'status' => 'confirmed',
                    'meta' => ['pia_ndc_context' => $piaContext],
                ],
            );

            $this->recordAttempt($booking, $connection, $actor, 'success', null);
        });
    }

    private function hasSuccessfulCreateAttempt(Booking $booking): bool
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::PiaNdc->value)
            ->where('action', 'create_pnr')
            ->whereIn('status', ['success', 'created'])
            ->exists()
            || $booking->supplierBookings()
                ->where('provider', SupplierProvider::PiaNdc->value)
                ->whereIn('status', ['created', 'confirmed', 'pending_ticketing', 'ticketed'])
                ->exists();
    }

    private function recordAttempt(Booking $booking, SupplierConnection $connection, User $actor, string $status, ?string $errorCode): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::PiaNdc->value,
            'action' => 'create_pnr',
            'status' => $status,
            'error_code' => $errorCode,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function failure(string $code, string $message, SupplierConnection $connection): SupplierBookingResultData
    {
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'create_order',
            status: 'failed',
            safeMessage: $message,
            meta: ['error_code' => $code],
        );

        return new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: SupplierProvider::PiaNdc->value,
            error_code: $code,
            error_message: $message,
        );
    }
}
