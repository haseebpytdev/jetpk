<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcValidationException;
use App\Support\Bookings\PiaNdcBookingStatusInterpreter;
use App\Support\Bookings\PiaNdcPnrItinerarySyncMapper;
use App\Support\Security\SensitiveDataRedactor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Controlled PIA NDC DoOrderRetrieve refresh + local booking reconciliation (R12L).
 */
class PiaNdcBookingStatusRefreshService
{
    public const ACTION = 'refresh_pia_ndc_status';

    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcXmlParser $xmlParser,
        private readonly PiaNdcResponseNormalizer $normalizer,
        private readonly PiaNdcCorrelationContext $correlationContext,
    ) {}

    public function canRefreshBooking(Booking $booking): bool
    {
        if (! $this->isPiaNdcBooking($booking)) {
            return false;
        }

        return $this->resolveOrderId($booking) !== '' && $this->resolveOwnerCode($booking) !== '';
    }

    public function isStatusStale(Booking $booking): bool
    {
        if (! $this->canRefreshBooking($booking)) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $refreshMeta = is_array($meta['pia_ndc_last_status_refresh'] ?? null) ? $meta['pia_ndc_last_status_refresh'] : [];
        $checkedAt = trim((string) ($refreshMeta['checked_at'] ?? ''));
        if ($checkedAt === '') {
            return true;
        }

        try {
            $thresholdMinutes = max(1, (int) config('suppliers.pia_ndc.status_refresh_stale_minutes', 60));

            return Carbon::parse($checkedAt)->addMinutes($thresholdMinutes)->isPast();
        } catch (Throwable) {
            return true;
        }
    }

    public function shouldWarnStaleStatus(Booking $booking): bool
    {
        if (! $this->canRefreshBooking($booking)) {
            return false;
        }

        if (! $this->localStatusLooksActive($booking)) {
            return false;
        }

        if ($this->isStatusStale($booking)) {
            return true;
        }

        $paymentRequiredBy = $booking->payment_required_by;
        if ($paymentRequiredBy !== null && $paymentRequiredBy->isPast()) {
            return true;
        }

        $pnrExpiresAt = $booking->pnr_expires_at;
        if ($pnrExpiresAt !== null && $pnrExpiresAt->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * @return array{success: bool, interpreted: array<string, mixed>, summary: array<string, mixed>}
     */
    public function refreshBooking(Booking $booking, ?User $actor = null, string $source = 'admin_manual'): array
    {
        $this->assertRefreshable($booking);
        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            throw new PiaNdcValidationException('missing_connection', 422, 'PIA NDC supplier connection not found for this booking.');
        }

        $orderId = $this->resolveOrderId($booking);
        $ownerCode = $this->resolveOwnerCode($booking);
        $retrieve = $this->performOrderRetrieve($connection, $orderId, $ownerCode, $booking);
        if (($retrieve['success'] ?? false) !== true) {
            throw new PiaNdcValidationException(
                'status_refresh_retrieve_failed',
                422,
                'Could not refresh PIA NDC status from supplier.',
            );
        }

        $normalized = is_array($retrieve['normalized'] ?? null) ? $retrieve['normalized'] : [];
        $interpreted = PiaNdcBookingStatusInterpreter::interpret($normalized);
        $this->applyLocalReconciliation(
            booking: $booking,
            connection: $connection,
            normalized: $normalized,
            interpreted: $interpreted,
            actor: $actor,
            source: $source,
            diagnosticPath: (string) ($retrieve['diagnostic_path'] ?? ''),
            httpStatus: isset($retrieve['http_status']) ? (int) $retrieve['http_status'] : null,
        );

        return [
            'success' => true,
            'interpreted' => $interpreted,
            'summary' => array_merge($interpreted, [
                'order_id' => $orderId,
                'owner_code' => $ownerCode,
                'order_status' => $normalized['order_status'] ?? null,
                'payment_time_limit' => $normalized['payment_time_limit'] ?? null,
                'ticket_numbers' => is_array($normalized['ticket_numbers'] ?? null) ? $normalized['ticket_numbers'] : [],
                'ticket_doc_infos' => is_array($normalized['ticket_doc_infos'] ?? null) ? $normalized['ticket_doc_infos'] : null,
                'has_blocking_ticket_numbers' => (bool) ($normalized['has_blocking_ticket_numbers'] ?? false),
                'segment_count' => (int) ($normalized['segment_count'] ?? ($interpreted['segment_count'] ?? 0)),
                'diagnostic_path' => $retrieve['diagnostic_path'] ?? null,
            ]),
        ];
    }

    public function refreshIfRequiredForSensitiveAction(Booking $booking, ?User $actor, string $source): Booking
    {
        if (! $this->isPiaNdcBooking($booking) || ! $this->canRefreshBooking($booking)) {
            return $booking;
        }

        $this->refreshBooking($booking, $actor, $source);

        return $booking->fresh() ?? $booking;
    }

    /**
     * @return array{refreshed: bool, booking: Booking}
     */
    public function refreshIfRequiredForPayment(Booking $booking): array
    {
        if (! $this->isPiaNdcBooking($booking) || ! $this->canRefreshBooking($booking)) {
            return ['refreshed' => false, 'booking' => $booking];
        }

        if (! $this->needsRefreshBeforePayment($booking)) {
            return ['refreshed' => false, 'booking' => $booking];
        }

        try {
            $this->refreshBooking($booking, null, 'payment_start');
        } catch (PiaNdcValidationException $exception) {
            throw new PiaNdcValidationException(
                'payment_status_refresh_required',
                422,
                'Airline reservation status must be refreshed before online payment.',
                [],
                $exception,
            );
        }

        return ['refreshed' => true, 'booking' => $booking->fresh() ?? $booking];
    }

    public function needsRefreshBeforePayment(Booking $booking): bool
    {
        if (! $this->isPiaNdcBooking($booking) || ! $this->canRefreshBooking($booking)) {
            return false;
        }

        if ($this->isStatusStale($booking)) {
            return true;
        }

        $paymentRequiredBy = $booking->payment_required_by;
        if ($paymentRequiredBy !== null && $paymentRequiredBy->isPast()) {
            return true;
        }

        $pnrExpiresAt = $booking->pnr_expires_at;

        return $pnrExpiresAt !== null && $pnrExpiresAt->isPast();
    }

    /**
     * @param  array<string, mixed>  $releaseSummary
     */
    public function reconcileLocalAfterSuccessfulRelease(Booking $booking, SupplierConnection $connection, array $releaseSummary, ?User $actor = null): void
    {
        $normalized = [
            'order_id' => $releaseSummary['order_id'] ?? $this->resolveOrderId($booking),
            'order_status' => $releaseSummary['order_status'] ?? 'CLOSED',
            'segment_count' => 0,
            'payment_time_limit' => null,
            'ticket_numbers' => is_array($releaseSummary['ticket_numbers'] ?? null) ? $releaseSummary['ticket_numbers'] : [],
            'has_blocking_ticket_numbers' => (bool) ($releaseSummary['has_blocking_ticket_numbers'] ?? false),
            'cancellation_status' => $releaseSummary['cancellation_status'] ?? 'cancelled',
        ];
        $interpreted = PiaNdcBookingStatusInterpreter::interpret($normalized);
        if (($releaseSummary['cancellation_status'] ?? '') === 'cancelled') {
            $interpreted['interpreted_status'] = PiaNdcBookingStatusInterpreter::STATUS_RELEASED;
            $interpreted['released'] = true;
            $interpreted['active_option_pnr'] = false;
        }

        $this->applyLocalReconciliation(
            booking: $booking,
            connection: $connection,
            normalized: $normalized,
            interpreted: $interpreted,
            actor: $actor,
            source: 'release_option_pnr',
            diagnosticPath: (string) ($releaseSummary['diagnostic_path'] ?? ''),
            httpStatus: null,
            forceReleased: true,
        );
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $interpreted
     */
    public function applyLocalReconciliation(
        Booking $booking,
        SupplierConnection $connection,
        array $normalized,
        array $interpreted,
        ?User $actor,
        string $source,
        string $diagnosticPath = '',
        ?int $httpStatus = null,
        bool $forceReleased = false,
    ): void {
        DB::transaction(function () use ($booking, $connection, $normalized, $interpreted, $actor, $source, $diagnosticPath, $httpStatus, $forceReleased): void {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
            $released = $forceReleased || ($interpreted['released'] ?? false) === true;
            $ticketed = ($interpreted['ticketed'] ?? false) === true;
            $activeOptionPnr = ($interpreted['active_option_pnr'] ?? false) === true;

            foreach ($normalized as $key => $value) {
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }
                if (in_array($key, ['segments', 'provider_errors', 'provider_warnings'], true)) {
                    continue;
                }
                $context[$key] = $value;
            }

            $context['interpreted_status'] = (string) ($interpreted['interpreted_status'] ?? PiaNdcBookingStatusInterpreter::STATUS_UNKNOWN);
            $context['segment_count'] = (int) ($interpreted['segment_count'] ?? 0);
            $context['has_ticket_numbers'] = (bool) ($interpreted['has_ticket_numbers'] ?? false);

            if ($released) {
                $context['option_pnr_released'] = true;
                $context['option_pnr_released_at'] = $context['option_pnr_released_at'] ?? now()->toIso8601String();
                $context['cancel_committed'] = true;
                $context['cancellation_status'] = (string) ($normalized['cancellation_status'] ?? $context['cancellation_status'] ?? 'cancelled');
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

            $supplierBookingStatus = match (true) {
                $ticketed => 'ticketed',
                $released => 'released',
                ($interpreted['interpreted_status'] ?? '') === PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID => 'option_pnr_after_void',
                $activeOptionPnr => 'option_pnr_created',
                default => (string) ($booking->supplier_booking_status ?? 'unknown'),
            };

            $supplierBookingRowStatus = match (true) {
                $ticketed => 'ticketed',
                $released => 'cancelled',
                ($interpreted['interpreted_status'] ?? '') === PiaNdcBookingStatusInterpreter::STATUS_OPTION_PNR_AFTER_VOID => 'pending_payment_or_ticketing',
                $activeOptionPnr => 'pending_payment_or_ticketing',
                default => 'unknown',
            };

            $meta['pia_ndc_context'] = $context;
            $meta['pia_ndc_last_status_refresh'] = [
                'checked_at' => now()->toIso8601String(),
                'source' => $source,
                'interpreted_status' => $context['interpreted_status'],
                'segment_count' => $context['segment_count'],
                'order_status' => $context['order_status'] ?? null,
                'has_ticket_numbers' => $context['has_ticket_numbers'],
                'released' => $released,
            ];
            $meta = PiaNdcPnrItinerarySyncMapper::applyRetrieveToBookingMeta($meta, $normalized);

            $booking->forceFill([
                'supplier_booking_status' => $supplierBookingStatus,
                'payment_required_by' => $released ? null : $paymentRequiredBy,
                'pnr_expires_at' => $released ? null : $pnrExpiresAt,
                'meta' => $meta,
            ])->save();

            $orderId = trim((string) ($normalized['order_id'] ?? $this->resolveOrderId($booking)));
            $pnr = trim((string) ($booking->pnr ?? $orderId));

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
                    'status' => $supplierBookingRowStatus,
                    'raw_summary' => SensitiveDataRedactor::redact([
                        'source' => $source,
                        'interpreted_status' => $context['interpreted_status'],
                        'order_id' => $orderId,
                        'order_status' => $context['order_status'] ?? null,
                        'segment_count' => $context['segment_count'],
                        'payment_time_limit' => $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
                        'ticket_numbers' => $normalized['ticket_numbers'] ?? [],
                        'released' => $released,
                    ]),
                ],
            );

            $safeSummary = SensitiveDataRedactor::redact([
                'source' => $source,
                'interpreted_status' => $context['interpreted_status'],
                'segment_count' => $context['segment_count'],
                'order_status' => $context['order_status'] ?? null,
                'order_id' => $orderId,
                'payment_time_limit' => $paymentTimeLimit !== '' ? $paymentTimeLimit : null,
                'has_ticket_numbers' => $context['has_ticket_numbers'],
                'released' => $released,
                'http_status' => $httpStatus,
                'diagnostic_path' => $diagnosticPath !== '' ? $diagnosticPath : null,
            ]);

            SupplierBookingAttempt::query()->create([
                'agency_id' => $booking->agency_id,
                'booking_id' => $booking->id,
                'supplier_connection_id' => $connection->id,
                'provider' => SupplierProvider::PiaNdc->value,
                'action' => self::ACTION,
                'status' => 'success',
                'safe_summary' => $safeSummary,
                'supplier_reference' => $orderId !== '' ? $orderId : null,
                'attempted_by' => $actor?->id,
                'attempted_at' => now(),
                'completed_at' => now(),
            ]);
        });
    }

    public function localStatusLooksActive(Booking $booking): bool
    {
        if (! $this->isPiaNdcBooking($booking)) {
            return false;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        if (($context['option_pnr_released'] ?? false) === true || ($context['cancel_committed'] ?? false) === true) {
            return false;
        }

        $supplierStatus = strtolower(trim((string) ($booking->supplier_booking_status ?? '')));
        if (in_array($supplierStatus, ['released', 'cancelled', 'closed', 'ticketed'], true)) {
            return false;
        }

        $interpreted = strtolower(trim((string) ($context['interpreted_status'] ?? '')));
        if (in_array($interpreted, [
            PiaNdcBookingStatusInterpreter::STATUS_RELEASED,
            PiaNdcBookingStatusInterpreter::STATUS_NO_ACTIVE_SEGMENTS,
            PiaNdcBookingStatusInterpreter::STATUS_TICKETED,
        ], true)) {
            return false;
        }

        $pnr = trim((string) ($booking->pnr ?? ''));
        $supplierRef = trim((string) ($booking->supplier_reference ?? ''));

        return $pnr !== '' || $supplierRef !== '';
    }

    /**
     * @return array{success: bool, normalized: ?array<string, mixed>, http_status: ?int, diagnostic_path: string}
     */
    private function performOrderRetrieve(
        SupplierConnection $connection,
        string $orderId,
        string $ownerCode,
        Booking $booking,
    ): array {
        $correlationId = $this->correlationContext->newCorrelationId();
        $config = $this->configResolver->resolve($connection);
        $requestXml = $this->xmlBuilder->buildOrderRetrieveRequest($config, $orderId, $ownerCode);
        $sanitizedRequestXml = $this->client->sanitizeXmlForDiagnostics($requestXml);
        $diagnosticRoot = storage_path('app/diagnostics/pia-ndc/status-refresh/'.$connection->id.'/'.$correlationId);
        File::ensureDirectoryExists($diagnosticRoot);
        file_put_contents($diagnosticRoot.'/request.xml', $sanitizedRequestXml);

        $httpStatus = null;
        $responseXml = null;
        $normalized = null;
        $providerErrorCode = null;

        try {
            $parsedResponse = $this->client->call($connection, 'order_retrieve', $requestXml, [
                'request_context' => 'pia-ndc:status-refresh',
                'correlation_id' => $correlationId,
                'booking_id' => $booking->id,
            ]);
            $diagnostic = is_array($parsedResponse['_ota_diagnostic'] ?? null) ? $parsedResponse['_ota_diagnostic'] : [];
            $httpStatus = isset($diagnostic['http_status']) ? (int) $diagnostic['http_status'] : null;
            $responseXml = is_string($parsedResponse['raw_xml'] ?? null)
                ? $this->client->sanitizeXmlForDiagnostics($parsedResponse['raw_xml'])
                : null;
            $normalized = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($parsedResponse, [
                'order_id' => $orderId,
                'owner_code' => $ownerCode,
            ]);
            if (($parsedResponse['errors'][0]['code'] ?? '') !== '') {
                $providerErrorCode = (string) $parsedResponse['errors'][0]['code'];
            }
        } catch (PiaNdcException $exception) {
            $safeMeta = $exception->safeDiagnosticMeta('order_retrieve');
            $httpStatus = isset($safeMeta['http_status']) ? (int) $safeMeta['http_status'] : null;
            $providerErrorCode = $exception->normalizedCode;
            $responseXml = is_string($exception->context['response_xml'] ?? null)
                ? $exception->context['response_xml']
                : null;
            if ($responseXml !== null) {
                try {
                    $parsedResponse = $this->xmlParser->parse($responseXml);
                    $normalized = $this->normalizer->normalizeOrderRetrieveDiagnosticResponse($parsedResponse, [
                        'order_id' => $orderId,
                        'owner_code' => $ownerCode,
                    ]);
                } catch (Throwable) {
                    $normalized = null;
                }
            }
        }

        if ($responseXml !== null) {
            file_put_contents($diagnosticRoot.'/response.xml', $responseXml);
        }
        if ($normalized !== null) {
            file_put_contents(
                $diagnosticRoot.'/normalized_response.json',
                json_encode(SensitiveDataRedactor::redact($normalized), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        }

        $resolvedOrderId = trim((string) ($normalized['order_id'] ?? $orderId));
        $httpOk = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        $success = $httpOk && $providerErrorCode === null && $resolvedOrderId !== '';

        $summary = [
            'success' => $success,
            'http_status' => $httpStatus,
            'order_id' => $resolvedOrderId,
            'provider_error_code' => $providerErrorCode,
            'segment_count' => $normalized['segment_count'] ?? 0,
            'order_status' => $normalized['order_status'] ?? null,
        ];
        file_put_contents(
            $diagnosticRoot.'/summary.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );

        return [
            'success' => $success,
            'normalized' => $normalized,
            'http_status' => $httpStatus,
            'diagnostic_path' => $diagnosticRoot,
        ];
    }

    private function assertRefreshable(Booking $booking): void
    {
        if (! $this->isPiaNdcBooking($booking)) {
            throw new PiaNdcValidationException('supplier_provider_mismatch', 422, 'This booking is not a PIA NDC supplier booking.');
        }

        if ($this->resolveOrderId($booking) === '') {
            throw new PiaNdcValidationException('missing_order_reference', 422, 'PIA NDC order reference is missing on this booking.');
        }

        if ($this->resolveOwnerCode($booking) === '') {
            throw new PiaNdcValidationException('missing_owner_code', 422, 'PIA NDC owner code is missing on this booking.');
        }
    }

    private function isPiaNdcBooking(Booking $booking): bool
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        return $provider === SupplierProvider::PiaNdc->value;
    }

    private function resolveOrderId(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? ''));
        if ($orderId === '' && Schema::hasColumn($booking->getTable(), 'supplier_reference')) {
            $orderId = trim((string) ($booking->supplier_reference ?? ''));
        }
        if ($orderId === '') {
            $orderId = trim((string) ($booking->pnr ?? ''));
        }

        return $orderId;
    }

    private function resolveOwnerCode(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($ownerCode !== '') {
            return $ownerCode;
        }

        $connection = $this->resolveConnection($booking);
        $credentials = is_array($connection?->credentials) ? $connection->credentials : [];

        return trim((string) ($credentials['owner_code'] ?? 'PK'));
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId > 0) {
            $connection = SupplierConnection::query()
                ->where('id', $connectionId)
                ->where('provider', SupplierProvider::PiaNdc)
                ->first();
            if ($connection !== null) {
                return $connection;
            }
        }

        $booking->loadMissing('latestSupplierBooking.supplierConnection');
        $latest = $booking->latestSupplierBooking?->supplierConnection;
        if ($latest !== null && $latest->provider === SupplierProvider::PiaNdc) {
            return $latest;
        }

        return SupplierConnection::query()
            ->where('provider', SupplierProvider::PiaNdc)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }
}
