<?php

namespace App\Services\Suppliers\Iati;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\Iati\Exceptions\IatiException;
use App\Services\Suppliers\SupplierDiagnosticLogger;

class IatiCancelService
{
    public function __construct(
        private readonly IatiClient $client,
        private readonly IatiResponseNormalizer $normalizer,
        private readonly SupplierDiagnosticLogger $diagnosticLogger,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, SupplierConnection $connection, User $actor): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $iatiContext = is_array($meta['iati_context'] ?? null) ? $meta['iati_context'] : [];

        if (! empty($iatiContext['cancelled_at']) || in_array((string) ($booking->cancellation_status ?? ''), ['cancelled', 'voided'], true)) {
            return [
                'success' => true,
                'status' => 'already_cancelled',
                'message' => 'Booking already cancelled.',
            ];
        }

        $orderId = trim((string) ($iatiContext['order_id'] ?? $booking->supplier_reference ?? ''));
        if ($orderId === '') {
            return [
                'success' => true,
                'status' => 'local_only',
                'message' => 'No IATI order id; cancelled locally only.',
            ];
        }

        $mode = (string) ($iatiContext['mode'] ?? '');
        if ($mode === '') {
            $mode = trim((string) ($booking->pnr ?? '')) !== '' ? 'book' : 'option';
        }

        try {
            $path = '/'.$mode.'/'.rawurlencode($orderId).'/cancel';
            $response = $this->client->get($connection, $path, [
                'request_context' => 'cancel',
                'booking_id' => $booking->id,
                'user_id' => $actor->id,
            ]);

            if (! ($response['_ota_diagnostic']['http_status'] ?? 200) < 400) {
                // client throws on failure; this is defensive
            }
        } catch (IatiException $exception) {
            if ($mode === 'book') {
                $path = '/option/'.rawurlencode($orderId).'/cancel';
                $response = $this->client->get($connection, $path, [
                    'request_context' => 'cancel_option_fallback',
                    'booking_id' => $booking->id,
                    'user_id' => $actor->id,
                ]);
            } else {
                throw $exception;
            }
        }

        $normalized = $this->normalizer->normalizeCancelResponse($this->client->unwrapResult($response));
        $cancelled = ($normalized['cancellation_status'] ?? '') === 'cancelled';

        $iatiContext['cancelled_at'] = now()->toIso8601String();
        $iatiContext['cancel_response'] = $normalized;
        $meta['iati_context'] = $iatiContext;
        $booking->update([
            'meta' => $meta,
            'cancellation_status' => $cancelled ? 'cancelled' : 'cancel_failed',
        ]);

        $this->diagnosticLogger->log(
            connection: $connection,
            action: 'cancel_order',
            status: $cancelled ? 'success' : 'failed',
            safeMessage: $cancelled ? 'IATI order cancelled.' : 'IATI did not confirm cancellation.',
            meta: ['booking_id' => $booking->id, 'order_id' => $orderId],
        );

        return [
            'success' => $cancelled,
            'status' => $normalized['cancellation_status'],
            'normalized' => $normalized,
        ];
    }
}
