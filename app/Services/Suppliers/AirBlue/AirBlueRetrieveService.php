<?php

namespace App\Services\Suppliers\AirBlue;

use App\Enums\AirBlueApiChannel;
use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use Illuminate\Support\Facades\Log;

class AirBlueRetrieveService
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
    public function retrieveAndSync(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];

        if ($this->configResolver->apiChannel($connection) === AirBlueApiChannel::ZapwaysOta) {
            return $this->retrieveOta($booking, $connection, $context);
        }

        return $this->retrieveNdc($booking, $connection, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function retrieveNdc(Booking $booking, SupplierConnection $connection, array $context): array
    {
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($orderId === '' || $ownerCode === '') {
            return ['synced' => false, 'reason' => 'missing_order_context'];
        }

        try {
            $config = $this->configResolver->resolveNdc($connection);
            $xml = $this->xmlBuilder->buildOrderRetrieveRequest($config, $orderId, $ownerCode);
            $response = $this->client->call($connection, 'order_retrieve', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'retrieve',
            ]);
            $normalized = $this->normalizer->normalizeRetrieveResponse($response, $context);
            $this->mergeSync($booking, $normalized);

            return ['synced' => true, 'data' => $normalized];
        } catch (AirBlueException $exception) {
            Log::channel('air-blue')->warning('airblue.retrieve.failed', [
                'booking_id' => $booking->id,
                'error_code' => $exception->normalizedCode,
            ]);

            return ['synced' => false, 'reason' => $exception->normalizedCode];
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function retrieveOta(Booking $booking, SupplierConnection $connection, array $context): array
    {
        $pnr = trim((string) ($context['pnr'] ?? $booking->pnr ?? $booking->supplier_reference ?? ''));
        $instance = trim((string) ($context['instance'] ?? ''));
        if ($pnr === '' || $instance === '') {
            return ['synced' => false, 'reason' => 'missing_order_context'];
        }

        try {
            $config = $this->configResolver->resolveOta($connection);
            $xml = $this->otaXmlBuilder->buildReadRequest($config, $pnr, $instance);
            $response = $this->client->call($connection, 'read', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'read',
            ]);
            $normalized = $this->otaNormalizer->normalizeRetrieveResponse($response, $context);
            $this->mergeSync($booking, $normalized);

            return ['synced' => true, 'data' => $normalized];
        } catch (AirBlueException $exception) {
            Log::channel('air-blue')->warning('airblue.retrieve.failed', [
                'booking_id' => $booking->id,
                'error_code' => $exception->normalizedCode,
            ]);

            return ['synced' => false, 'reason' => $exception->normalizedCode];
        }
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function mergeSync(Booking $booking, array $normalized): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        foreach ($normalized as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $existing[$key] = $value;
        }
        $existing['last_sync_at'] = now()->toIso8601String();
        $meta['airblue_context'] = $existing;
        $meta['supplier_provider'] = SupplierProvider::Airblue->value;
        $booking->meta = $meta;
        if (($normalized['order_id'] ?? '') !== '') {
            $booking->supplier_reference = (string) $normalized['order_id'];
        } elseif (($normalized['pnr'] ?? '') !== '') {
            $booking->supplier_reference = (string) $normalized['pnr'];
        }
        $booking->save();
    }
}
