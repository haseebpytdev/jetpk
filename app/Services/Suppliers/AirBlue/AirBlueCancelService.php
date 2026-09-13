<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;

class AirBlueCancelService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, SupplierConnection $connection, User $actor): array
    {
        unset($actor);

        return $this->cancelOta($booking, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        throw new AirBlueCancellationException(
            'cancel_preview_unsupported',
            422,
            'Zapways OTA does not support a separate cancellation preview step.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(Booking $booking, SupplierConnection $connection): array
    {
        return $this->cancelOta($booking, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelOta(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        if (($context['cancel_committed'] ?? false) === true) {
            throw new AirBlueCancellationException('duplicate_cancellation_guard', 409, 'Cancellation already committed.');
        }

        $pnr = trim((string) ($context['pnr'] ?? $booking->pnr ?? $booking->supplier_reference ?? ''));
        $instance = trim((string) ($context['instance'] ?? ''));
        if ($pnr === '' || $instance === '') {
            throw new AirBlueCancellationException('missing_order_context', 422, 'Cancellation failed, admin review required.');
        }

        $config = $this->configResolver->resolveOta($connection);
        $xml = $this->otaXmlBuilder->buildCancelRequest($config, $pnr, $instance);

        try {
            $response = $this->client->callOta($connection, 'cancel', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'cancel',
            ]);
            $result = $this->otaNormalizer->normalizeCancelResponse($response);
            $context['cancel_committed'] = true;
            $context['cancellation_status'] = $result['cancellation_status'] ?? 'cancelled';
            $meta['airblue_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return array_merge($result, ['success' => true, 'status' => 'cancelled']);
        } catch (AirBlueException $exception) {
            throw new AirBlueCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Cancellation failed, admin review required.',
                $exception->context,
                $exception,
            );
        }
    }
}
