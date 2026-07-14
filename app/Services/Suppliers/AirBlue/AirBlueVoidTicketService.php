<?php

namespace App\Services\Suppliers\AirBlue;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueCancellationException;
use App\Services\Suppliers\AirBlue\Exceptions\AirBlueException;

class AirBlueVoidTicketService
{
    public function __construct(
        private readonly AirBlueClient $client,
        private readonly AirBlueConfigResolver $configResolver,
        private readonly AirBlueXmlBuilder $xmlBuilder,
        private readonly AirBlueResponseNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function voidTicket(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['airblue_context'] ?? null) ? $meta['airblue_context'] : [];
        if (($context['void_status'] ?? '') === 'voided') {
            throw new AirBlueCancellationException('duplicate_void_guard', 409, 'Ticket already voided.');
        }

        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        if ($orderId === '' || $ownerCode === '') {
            throw new AirBlueCancellationException('missing_order_context', 422, 'Void ticket failed.');
        }

        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildVoidTicketRequest($config, $orderId, $ownerCode);

        try {
            $response = $this->client->call($connection, 'void_ticket', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'void_ticket',
            ]);
            $result = $this->normalizer->normalizeVoidResponse($response, $context);
            $context = array_merge($context, $result);
            $meta['airblue_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return $result;
        } catch (AirBlueException $exception) {
            throw new AirBlueCancellationException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Void ticket failed, admin review required.',
                $exception->context,
                $exception,
            );
        }
    }
}
