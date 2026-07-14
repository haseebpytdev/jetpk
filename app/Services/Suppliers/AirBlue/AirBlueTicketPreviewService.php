<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueTicketingException;

class AirBlueTicketPreviewService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueXmlBuilder $xmlBuilder,
        private readonly AirBlueResponseNormalizer $normalizer,
    ) {}

    /**
     * @return array{amount: float, currency: string}
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        [$orderId, $ownerCode] = $this->orderContext($booking);
        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildTicketPreviewRequest($config, $orderId, $ownerCode);

        try {
            $response = $this->client->call($connection, 'ticket_preview', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'ticket_preview',
            ]);
            $preview = $this->normalizer->normalizeTicketPreviewResponse($response);
            $this->persistPreview($booking, $preview);

            return $preview;
        } catch (AirBlueException $exception) {
            throw new AirBlueTicketingException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Ticketing preview failed, admin review required.',
                $exception->context,
                $exception,
            );
        }
    }

    /**
     * @param  array{amount: float, currency: string}  $preview
     */
    private function persistPreview(Booking $booking, array $preview): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $context['ticket_preview'] = $preview;
        $context['ticket_preview_at'] = now()->toIso8601String();
        $meta['airblue_context'] = $context;
        $booking->meta = $meta;
        $booking->save();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function orderContext(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($orderId === '' || $ownerCode === '') {
            throw new AirBlueTicketingException('missing_order_context', 422, 'Ticketing failed, admin review required.');
        }

        return [$orderId, $ownerCode];
    }
}
