<?php

namespace App\Services\Suppliers\PiaNdc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcException;
use App\Services\Suppliers\PiaNdc\Exceptions\PiaNdcProviderException;

/**
 * Reissue preview/commit — admin workflow only; uses OrderReshop + OrderChange patterns.
 */
class PiaNdcReissueService
{
    public function __construct(
        private readonly PiaNdcClient $client,
        private readonly PiaNdcConfigResolver $configResolver,
        private readonly PiaNdcXmlBuilder $xmlBuilder,
        private readonly PiaNdcResponseNormalizer $normalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, SupplierConnection $connection): array
    {
        return $this->runReshop($booking, $connection, 'reissue_preview');
    }

    /**
     * @return array<string, mixed>
     */
    public function commit(Booking $booking, SupplierConnection $connection): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        if (($context['reissue_committed'] ?? false) === true) {
            throw new PiaNdcProviderException('duplicate_reissue_guard', 409, 'Reissue already committed.');
        }

        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        $ownerCode = trim((string) ($context['owner_code'] ?? ''));
        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildTicketingOrderChangeRequest($config, $orderId, $ownerCode, [
            'amount' => (float) data_get($context, 'reissue_preview.amount', 0),
            'currency' => (string) data_get($context, 'reissue_preview.currency', $config['currency']),
        ]);

        try {
            $response = $this->client->call($connection, 'reissue_commit', $xml, [
                'booking_id' => $booking->id,
                'request_context' => 'reissue_commit',
            ]);
            $result = $this->normalizer->normalizeTicketingResponse($response, $context);
            $context['reissue_committed'] = true;
            $context['reissue_result'] = $result;
            $meta['pia_ndc_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return $result;
        } catch (PiaNdcException $exception) {
            throw new PiaNdcProviderException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Reissue failed, admin review required.',
                $exception->context,
                $exception,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runReshop(Booking $booking, SupplierConnection $connection, string $operation): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $orderId = trim((string) ($context['order_id'] ?? $booking->supplier_reference ?? ''));
        if ($orderId === '') {
            throw new PiaNdcProviderException('missing_order_context', 422, 'Reissue preview failed.');
        }

        $config = $this->configResolver->resolve($connection);
        $xml = $this->xmlBuilder->buildCancelPreviewRequest($config, $orderId);

        try {
            $response = $this->client->call($connection, $operation, $xml, [
                'booking_id' => $booking->id,
                'request_context' => $operation,
            ]);
            $preview = $this->normalizer->normalizeCancelPreviewResponse($response);
            $context['reissue_preview'] = $preview;
            $meta['pia_ndc_context'] = $context;
            $booking->meta = $meta;
            $booking->save();

            return $preview;
        } catch (PiaNdcException $exception) {
            throw new PiaNdcProviderException(
                $exception->normalizedCode,
                $exception->httpStatus,
                'Reissue preview failed.',
                $exception->context,
                $exception,
            );
        }
    }
}
