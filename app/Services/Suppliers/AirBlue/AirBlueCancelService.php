<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueApiChannel;
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
        private readonly AirBlueXmlBuilder $xmlBuilder,
        private readonly AirBlueOtaXmlBuilder $otaXmlBuilder,
        private readonly AirBlueResponseNormalizer $normalizer,
        private readonly AirBlueOtaResponseNormalizer $otaNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancelForBooking(Booking $booking, SupplierConnection $connection, User $actor): array
    {
        unset($actor);
        if ($this->configResolver->apiChannel($connection) === AirBlueApiChannel::ZapwaysOta) {
            return $this->cancelOta($booking, $connection);
        }

        $preview = $this->preview($booking, $connection);

        return array_merge($preview, $this->commit($booking, $connection), [
            'success' => true,
            'status' => 'cancelled',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        $orderId = $this->orderId($booking);
        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildCancelPreviewRequest($config, $orderId);

        try {
            $response = $this->client->call($connection, 'cancel_preview', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'cancel_preview',
            ]);
            $preview = $this->normalizer->normalizeCancelPreviewResponse($response);
            $this->persistPreview($booking, $preview);

            return $preview;
        } catch (AirBlueException $exception) {
            throw new AirBlueCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Cancellation preview failed.',
                $exception->context,
                $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        if (($context['cancel_committed'] ?? false) === true) {
            throw new AirBlueCancellationException('duplicate_cancellation_guard', 409, 'Cancellation already committed.');
        }

        $orderId = $this->orderId($booking);
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildCancelCommitRequest($config, $orderId, $ownerCode);

        try {
            $response = $this->client->call($connection, 'cancel_commit', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'cancel_commit',
            ]);
            $result = $this->normalizer->normalizeCancelCommitResponse($response);
            $context['cancel_committed'] = true;
            $context['cancellation_status'] = $result['cancellation_status'] ?? 'cancelled';
            $meta['airblue_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return $result;
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

    /**
     * @param  array<string, mixed>  $preview
     */
    private function persistPreview(Booking $booking, array $preview): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $context['cancel_preview'] = $preview;
        $meta['airblue_context'] = $context;
        $booking->meta = $meta;
        $booking->save();
    }

    private function orderId(Booking $booking): string
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        if ($orderId === '') {
            throw new AirBlueCancellationException('missing_order_context', 422, 'Cancellation preview failed.');
        }

        return $orderId;
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
            $response = $this->client->call($connection, 'cancel', $xml, [
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
