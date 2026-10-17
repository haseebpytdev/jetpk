<?php

namespace App\Services\Suppliers\AirBlue;

use App\Data\SupplierBookingResultData;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierBookingAttempt;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueBookingException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueUnavailableException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AirBlue Zapways OTA booking orchestrator with duplicate guards.
 *
 * Transport timeouts after air_book may mean the provider accepted the reservation.
 * Those outcomes are recorded as uncertain and must not auto-retry AirBook.
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
            return $this->failure(
                'booking_outcome_uncertain',
                'Supplier booking outcome is uncertain. Retrieve before any retry.',
                $connection,
            );
        }

        try {
            $this->protocolGuard->assertCompatible($connection, $providerContext, 'booking');
            $passengers = $this->passengerPayloadBuilder->buildPassengersFromBooking($booking);
            $contact = $this->passengerPayloadBuilder->buildContactFromBooking($booking);
            $config = $this->configResolver->resolveOta($connection);
            $xml = $this->otaXmlBuilder->buildAirBookRequest($config, $providerContext, $passengers, $contact);
            $response = $this->client->callOta($connection, 'air_book', $xml, [
                'request_context' => 'air_book',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);
            $normalized = $this->otaNormalizer->normalizeBookingResponse($response, $providerContext);
            $this->persistBookingState($booking, $connection, $normalized, $actor);

            return new SupplierBookingResultData(
                success: true,
                status: 'confirmed',
                provider: SupplierProvider::Airblue->value,
                supplier_reference: (string) ($normalized['provider_booking_reference'] ?? ''),
                pnr: (string) ($normalized['pnr'] ?? ''),
                response_payload: ['provider_context' => $normalized['provider_context'] ?? []],
            );
        } catch (AirBlueUnavailableException $exception) {
            // Request may already have been accepted — never auto-retry AirBook.
            $this->recordAttempt($booking, $connection, $actor, 'uncertain', $exception->normalizedCode);
            $this->markUncertainBookingState($booking, $connection, $exception->normalizedCode);

            return $this->failure(
                'booking_outcome_uncertain',
                'Supplier booking outcome is uncertain. Retrieve before any retry.',
                $connection,
            );
        } catch (AirBlueException $exception) {
            $this->recordAttempt($booking, $connection, $actor, 'failed', $exception->normalizedCode);

            return $this->failure($exception->normalizedCode, $exception->safeMessage, $connection);
        } catch (\Throwable $exception) {
            Log::channel('air-blue')->warning('airblue.booking.unexpected', [
                'booking_id' => $booking->id,
                'exception' => $exception::class,
            ]);
            // Unknown local failure after possible provider acceptance — treat as uncertain.
            $this->recordAttempt($booking, $connection, $actor, 'uncertain', 'unexpected');
            $this->markUncertainBookingState($booking, $connection, 'unexpected');

            throw new AirBlueBookingException(
                'booking_outcome_uncertain',
                500,
                'Supplier booking outcome is uncertain. Retrieve before any retry.',
                ['booking_id' => $booking->id],
                $exception,
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
     * @param  array<string, mixed>  $normalized
     */
    private function persistBookingState(Booking $booking, SupplierConnection $connection, array $normalized, User $actor): void
    {
        DB::transaction(function () use ($booking, $connection, $normalized, $actor): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $airblueContext = array_merge(
                is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [],
                is_array($normalized['provider_context'] ?? null) ? $normalized['provider_context'] : [],
            );
            $meta['supplier_provider'] = SupplierProvider::Airblue->value;
            $meta['supplier_connection_id'] = $connection->id;
            $meta['airblue_context'] = $airblueContext;
            $booking->meta = $meta;
            $booking->supplier_reference = (string) ($normalized['pnr'] ?? $booking->supplier_reference);
            $booking->save();

            SupplierBooking::query()->updateOrCreate(
                ['booking_id' => $booking->id, 'provider' => SupplierProvider::Airblue->value],
                [
                    'agency_id' => $booking->agency_id,
                    'supplier_connection_id' => $connection->id,
                    'provider_reference' => (string) ($normalized['provider_booking_reference'] ?? ''),
                    'status' => 'confirmed',
                    'meta' => ['airblue_context' => $airblueContext],
                ],
            );

            $this->recordAttempt($booking, $connection, $actor, 'success', null);
        });
    }

    private function hasBlockingCreateAttempt(Booking $booking): bool
    {
        return SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('provider', SupplierProvider::Airblue->value)
            ->where('action', 'create_pnr')
            ->whereIn('status', ['success', 'created', 'uncertain'])
            ->exists()
            || $booking->supplierBookings()
                ->where('provider', SupplierProvider::Airblue->value)
                ->whereIn('status', ['created', 'confirmed', 'pending_ticketing', 'ticketed', 'pending_reconciliation'])
                ->exists();
    }

    private function markUncertainBookingState(Booking $booking, SupplierConnection $connection, string $errorCode): void
    {
        DB::transaction(function () use ($booking, $connection, $errorCode): void {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $airblueContext = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
            $airblueContext['booking_outcome'] = 'uncertain';
            $airblueContext['supplier_outcome_unknown'] = true;
            $airblueContext['uncertain_error_code'] = $errorCode;
            $airblueContext['uncertain_at'] = now()->toIso8601String();
            $meta['supplier_provider'] = SupplierProvider::Airblue->value;
            $meta['supplier_connection_id'] = $connection->id;
            $meta['airblue_context'] = $airblueContext;
            $booking->meta = $meta;
            $booking->save();

            SupplierBooking::query()->updateOrCreate(
                ['booking_id' => $booking->id, 'provider' => SupplierProvider::Airblue->value],
                [
                    'agency_id' => $booking->agency_id,
                    'supplier_connection_id' => $connection->id,
                    'provider_reference' => (string) ($airblueContext['order_id'] ?? $airblueContext['pnr'] ?? ''),
                    'status' => 'pending_reconciliation',
                    'meta' => ['airblue_context' => $airblueContext],
                ],
            );
        });
    }

    private function recordAttempt(Booking $booking, SupplierConnection $connection, User $actor, string $status, ?string $errorCode): void
    {
        SupplierBookingAttempt::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'supplier_connection_id' => $connection->id,
            'provider' => SupplierProvider::Airblue->value,
            'action' => 'create_pnr',
            'status' => $status,
            'error_code' => $errorCode,
            'attempted_by' => $actor->id,
            'attempted_at' => now(),
            'completed_at' => now(),
            'safe_summary' => $status === 'uncertain'
                ? [
                    'reason_code' => 'booking_outcome_uncertain',
                    'requires_retrieve_before_retry' => true,
                ]
                : null,
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
            provider: SupplierProvider::Airblue->value,
            error_code: $code,
            error_message: $message,
        );
    }
}
