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
 * On successful AirBook, `safe_summary.airblue_context` includes supplier-only
 * ticketing payment fields (`supplier_payable_amount`, `payment_currency`,
 * `payment_type`) derived from offer `fare_breakdown.supplier_total` — never from
 * customer payable / markup-inclusive totals.
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
            $providerCtx = array_merge($providerCtx, $this->resolveTicketingPaymentContext($booking, $meta, $config));
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
            return $this->failure(
                $exception->normalizedCode,
                $exception->safeMessage,
                $connection,
                [
                    'http_status' => $exception->context['http_status'] ?? $exception->getCode(),
                    'fault_class' => $exception->context['fault_class'] ?? null,
                    'fault_snippet' => $exception->context['fault_snippet'] ?? null,
                    'underlying_error_code' => $exception->normalizedCode,
                ],
            );
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
     * Supplier-only PaymentInfo fields for later AirDemandTicket.
     *
     * Amounts come from offer fare_breakdown.supplier_total (or provider itinerary
     * total_fare). Never from customer payable / markup-inclusive booking totals.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function resolveTicketingPaymentContext(Booking $booking, array $meta, array $config): array
    {
        $snapshot = $this->resolveOfferSnapshot($booking, $meta);
        $fareBreakdown = is_array($snapshot['fare_breakdown'] ?? null) ? $snapshot['fare_breakdown'] : [];
        $amount = $fareBreakdown['supplier_total'] ?? null;
        $currency = $fareBreakdown['currency'] ?? null;

        if ($amount === null || $amount === '' || (float) $amount <= 0) {
            $airblueContext = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
            $itinerary = is_array($airblueContext['priced_itineraries'][0] ?? null)
                ? $airblueContext['priced_itineraries'][0]
                : [];
            $totalFare = is_array($itinerary['total_fare'] ?? null) ? $itinerary['total_fare'] : [];
            $amount = $totalFare['total'] ?? null;
            $currency = $currency ?? ($totalFare['currency'] ?? null);
        }

        if ($amount === null || $amount === '' || (float) $amount <= 0) {
            return [];
        }

        return [
            'supplier_payable_amount' => round((float) $amount, 2),
            'payment_currency' => strtoupper(trim((string) ($currency ?? $config['currency'] ?? 'PKR'))) ?: 'PKR',
            'payment_type' => 'Cash',
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function resolveOfferSnapshot(Booking $booking, array $meta): array
    {
        foreach ([
            'normalized_offer_snapshot',
            'validated_offer_snapshot',
            'flight_offer_snapshot',
            'offer_snapshot',
        ] as $key) {
            if (is_array($meta[$key] ?? null) && $meta[$key] !== []) {
                return $meta[$key];
            }
        }

        return is_array($booking->offer_snapshot) ? $booking->offer_snapshot : [];
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

    /**
     * @param  array<string, mixed>  $meta
     */
    private function failure(
        string $code,
        string $message,
        SupplierConnection $connection,
        array $meta = [],
    ): SupplierBookingResultData {
        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'create_order',
            status: 'failed',
            safeMessage: $message,
            meta: array_filter([
                'error_code' => $code,
                'http_status' => $meta['http_status'] ?? null,
                'fault_class' => $meta['fault_class'] ?? null,
                'fault_snippet' => $meta['fault_snippet'] ?? null,
                'underlying_error_code' => $meta['underlying_error_code'] ?? null,
            ], static fn ($v) => $v !== null && $v !== ''),
        );

        return new SupplierBookingResultData(
            success: false,
            status: 'failed',
            provider: SupplierProvider::Airblue->value,
            error_code: $code,
            error_message: $message,
            safe_summary: array_filter([
                'fault_class' => $meta['fault_class'] ?? null,
                'http_status' => $meta['http_status'] ?? null,
            ], static fn ($v) => $v !== null && $v !== ''),
        );
    }
}
