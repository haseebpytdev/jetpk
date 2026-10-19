<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Support\Facades\Log;

/**
 * AirBlue Zapways OTA booking orchestrator — validate, call provider, normalize, return DTO.
 *
 * Canonical SupplierBooking / SupplierBookingAttempt persistence is owned by
 * {@see \App\Services\Suppliers\SupplierBookingService}. This service must not create
 * a second successful attempt or SupplierBooking row.
 *
 * Transport timeouts after air_book may mean the provider accepted the reservation.
 * Those outcomes return status=manual_review / error_code=booking_outcome_uncertain
 * and must not auto-retry AirBook.
 */
class AirBlueBookingService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBluePassengerPayloadBuilder $passengerPayloadBuilder,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
        private readonly AirBlueProtocolGuard $protocolGuard,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    public function createSupplierBooking(Booking $booking, SupplierConnection $connection, User $actor): SupplierBookingResultData
    {
        if ($connection->provider !== SupplierProvider::Airblue) {
            return $this->failure('supplier_provider_mismatch', 'Supplier provider mismatch for AirBlue booking.', $connection);
        }

        if ($this->hasBlockingCreateAttempt($booking)) {
            return $this->failure('duplicate_booking_guard', 'A AirBlue booking already exists or is uncertain for this reservation.', $connection);
        }

        $booking->loadMissing(['passengers', 'contact', 'supplierBookings']);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $airblueContext = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $providerContext = $this->resolveProviderContext($booking, $meta);
        $existingOrderId = trim((string) ($airblueContext['order_id'] ?? $booking->supplier_reference ?? ''));
        $existingPnr = trim((string) ($airblueContext['pnr'] ?? $booking->pnr ?? ''));

        if ($existingOrderId !== '' || $existingPnr !== '') {
            return $this->failure('duplicate_booking_guard', 'This booking already has a AirBlue order.', $connection);
        }

        if (($airblueContext['booking_outcome'] ?? null) === 'uncertain') {
            return $this->uncertainResult(
                $connection,
                'booking_outcome_uncertain',
                'Supplier booking outcome is uncertain. Retrieve before any retry.',
            );
        }

        $airBookAttempted = false;

        try {
            $this->protocolGuard->assertCompatible($connection, $providerContext, 'booking');
            $passengers = $this->passengerPayloadBuilder->buildPassengersFromBooking($booking);
            $contact = $this->passengerPayloadBuilder->buildContactFromBooking($booking);
            $config = $this->configResolver->resolveOta($connection);
            $xml = $this->otaXmlBuilder->buildAirBookRequest($config, $providerContext, $passengers, $contact);

            $airBookAttempted = true;
            $response = $this->client->callOta($connection, 'air_book', $xml, [
                'request_context' => 'air_book',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);
            $normalized = $this->otaNormalizer->normalizeBookingResponse($response, $providerContext);
            $providerCtx = is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [];
            $pnr = (string) ($normalized['pnr'] ?? '');
            $supplierReference = (string) ($normalized['provider_booking_reference'] ?? $pnr);

            return new SupplierBookingResultData(
                success: true,
                status: 'confirmed',
                provider: SupplierProvider::Airblue->value,
                supplier_reference: $supplierReference,
                pnr: $pnr,
                safe_summary: [
                    'supplier_booking_status' => 'pending_ticketing',
                    'airblue_context' => $providerCtx,
                ],
                response_payload: ['provider_context' => $providerCtx],
            );
        } catch (AirBlueUnavailableException $exception) {
            // Request may already have been accepted — never auto-retry AirBook.
            return $this->uncertainResult(
                $connection,
                $exception->normalizedCode !== '' ? $exception->normalizedCode : 'supplier_transport_failed',
                'Supplier booking outcome is uncertain. Retrieve before any retry.',
            );
        } catch (AirBlueException $exception) {
            // Pre-network gates (e.g. external_calls_disabled) and provider rejections are ordinary failures.
            return $this->failure($exception->normalizedCode, $exception->safeMessage, $connection);
        } catch (\Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.booking.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
                'air_book_attempted' => $airBookAttempted,
            ]);

            if (! $airBookAttempted) {
                return $this->failure('unexpected', 'AirBlue booking failed before the provider request was sent.', $connection);
            }

            // Unknown failure after possible provider acceptance — uncertain DTO for outer persistence.
            return $this->uncertainResult(
                $connection,
                'unexpected',
                'Supplier booking outcome is uncertain. Retrieve before any retry.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function resolveProviderContext(Booking $booking, array $meta): array
    {
        $fromMeta = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $snapshot = is_array($booking->offer_snapshot) ? $booking->offer_snapshot : [];
        $raw = is_array($snapshot['raw_payload'] ?? null) ? $snapshot['raw_payload'] : [];
        $fromSnapshot = is_array($raw['provider_context'] ?? null) ? $raw['provider_context'] : [];

        return array_merge($fromSnapshot, $fromMeta);
    }

    /**
     * Read-only duplicate / uncertain guards (no persistence).
     */
    private function hasBlockingCreateAttempt(Booking $booking): bool
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Airblue->value)
            ->where('action', 'create_pnr')
            ->whereIn('status', ['success', 'created', 'uncertain', 'reconciled'])
            ->exists()
            || $booking->supplierBookings()
                ->where('provider', SupplierProvider::Airblue->value)
                ->whereIn('status', ['created', 'confirmed', 'pending_ticketing', 'ticketed', 'pending_reconciliation', 'on_hold'])
                ->exists();
    }

    private function uncertainResult(
        SupplierConnection $connection,
        string $underlyingCode,
        string $message,
    ): SupplierBookingResultData {
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'create_order',
            status: 'manual_review',
            safeMessage: $message,
            meta: [
                'error_code' => 'booking_outcome_uncertain',
                'underlying_error_code' => $underlyingCode,
            ],
        );

        return new SupplierBookingResultData(
            success: false,
            status: 'manual_review',
            provider: SupplierProvider::Airblue->value,
            error_code: 'booking_outcome_uncertain',
            error_message: $message,
            safe_summary: [
                'supplier_booking_status' => 'pending_reconciliation',
                'supplier_outcome_unknown' => true,
                'requires_retrieve_before_retry' => true,
                'block_retry' => true,
                'pending_reconciliation' => true,
                'underlying_error_code' => $underlyingCode,
            ],
        );
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
            provider: SupplierProvider::Airblue->value,
            error_code: $code,
            error_message: $message,
        );
    }
}
