<?php

namespace App\Services\Suppliers\Iati;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBooking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\SupplierDiagnosticLogger;
use Illuminate\Support\Facades\Log;

class IatiRetrieveService
{
    public function __construct(
        private readonly IatiClient $client,
        private readonly IatiResponseNormalizer $normalizer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $bookingNormalized
     * @return array<string, mixed>
     */
    public function syncBooking(
        Booking $booking,
        SupplierConnection $connection,
        User $actor,
        array $bookingNormalized = [],
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $orderId = trim((string) (
            $iatiContext['order_id']
            ?? $booking->supplier_reference
            ?? $bookingNormalized['provider_booking_reference']
            ?? ''
        ));

        if ($orderId === '') {
            throw new IatiException('missing_order_id', 422, 'IATI order id is required for retrieve.');
        }

        try {
            $response = $this->client->get($connection, '/order/'.rawurlencode($orderId), [
                'request_context' => 'order_retrieve',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);

            $synced = $this->normalizer->normalizeRetrieveResponse(
                $this->client->unwrapResult($response),
                $iatiContext,
            );

            $this->mergeBookingMeta($booking, $synced, $connection);

            $this->diagnosticLogger->log(
                connection: $connection,
                action: 'retrieve_order',
                status: 'success',
                safeMessage: 'IATI booking sync completed.',
                meta: ['booking_id' => $booking->id, 'order_id' => $orderId],
            );

            return $synced;
        } catch (IatiException $exception) {
            $this->recordSyncFailure($booking, $connection, $exception);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $synced
     */
    protected function mergeBookingMeta(Booking $booking, array $synced, SupplierConnection $connection): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];

        $iatiContext = array_merge($existing, array_filter([
            'order_id' => $synced['order_id'] ?? $existing['order_id'] ?? null,
            'pnr' => $synced['pnr'] ?? $existing['pnr'] ?? null,
            'status' => $synced['status'] ?? $existing['status'] ?? null,
            'ticketing_status' => $synced['ticketing_status'] ?? $existing['ticketing_status'] ?? null,
            'ticket_numbers' => $synced['ticket_numbers'] ?? $existing['ticket_numbers'] ?? null,
            'airline_locator' => $synced['airline_locator'] ?? $existing['airline_locator'] ?? null,
            'last_ticketing_date' => $synced['last_ticketing_date'] ?? $existing['last_ticketing_date'] ?? null,
            'last_sync_at' => now()->toIso8601String(),
            'last_sync_status' => 'synced',
            'last_provider_error' => null,
            'last_correlation_id' => data_get($synced, 'correlation_id'),
        ], fn ($v) => $v !== null && $v !== ''));

        $meta['iati_context'] = $iatiContext;

        $updates = ['meta' => $meta];
        $existingPnr = trim((string) ($booking->pnr ?? ''));
        $syncedPnr = trim((string) ($synced['pnr'] ?? ''));
        if ($syncedPnr !== '' && $existingPnr === '') {
            $updates['pnr'] = $syncedPnr;
        }
        if (! empty($synced['order_id']) && trim((string) ($booking->supplier_reference ?? '')) === '') {
            $updates['supplier_reference'] = $synced['order_id'];
        }
        if (! empty($synced['order_id']) && trim((string) ($booking->supplier_api_booking_id ?? '')) === '') {
            $updates['supplier_api_booking_id'] = $synced['order_id'];
        }

        $booking->update($updates);

        if (($synced['ticketing_status'] ?? '') === 'ticketed') {
            $booking->update(['ticketing_status' => 'ticketed']);
        }

        $this->syncSupplierBookingRecord($booking, $connection, $synced);

        unset($connection);
    }

    /**
     * @param  array<string, mixed>  $synced
     */
    protected function syncSupplierBookingRecord(Booking $booking, SupplierConnection $connection, array $synced): void
    {
        $orderId = trim((string) ($synced['order_id'] ?? ''));
        if ($orderId === '' && trim((string) ($synced['pnr'] ?? '')) === '') {
            return;
        }

        SupplierBooking::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'provider' => SupplierProvider::Iati->value,
            ],
            array_filter([
                'agency_id' => $booking->agency_id,
                'supplier_connection_id' => $connection->id,
                'supplier_reference' => $orderId !== '' ? $orderId : null,
                'supplier_api_booking_id' => $orderId !== '' ? $orderId : null,
                'pnr' => trim((string) ($synced['pnr'] ?? '')) !== '' ? $synced['pnr'] : null,
                'status' => ($synced['ticketing_status'] ?? '') === 'ticketed' ? 'ticketed' : null,
            ], fn ($value) => $value !== null),
        );
    }

    protected function recordSyncFailure(Booking $booking, SupplierConnection $connection, IatiException $exception): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];
        $iatiContext['last_sync_at'] = now()->toIso8601String();
        $iatiContext['last_sync_status'] = 'failed';
        $iatiContext['last_provider_error'] = $exception->safeMessage;
        $meta['iati_context'] = $iatiContext;
        $booking->update(['meta' => $meta]);

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'retrieve_order',
            status: 'failed',
            safeMessage: $exception->safeMessage,
            meta: ['booking_id' => $booking->id, 'error_code' => $exception->normalizedCode],
        );

        Log::channel('iati')->warning('iati.retrieve.failed', [
            'booking_id' => $booking->id,
            'error_code' => $exception->normalizedCode,
        ]);
    }
}
