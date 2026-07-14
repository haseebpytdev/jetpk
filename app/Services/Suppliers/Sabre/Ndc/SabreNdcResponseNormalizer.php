<?php

namespace App\Services\Suppliers\Sabre\Ndc;

/**
 * Normalize Sabre NDC responses into safe OTA meta slices.
 */
final class SabreNdcResponseNormalizer
{
    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function normalizeOfferPrice(array $json): array
    {
        $order = is_array($json['order'] ?? null) ? $json['order'] : $json;

        return array_filter([
            'offer_id' => trim((string) ($order['offerId'] ?? $json['offerId'] ?? '')),
            'owner_code' => trim((string) ($order['ownerCode'] ?? $json['ownerCode'] ?? '')),
            'total_amount' => data_get($json, 'pricedOffer.totalAmount.amount'),
            'currency' => data_get($json, 'pricedOffer.totalAmount.currency'),
            'payment_time_limit' => data_get($order, 'timeLimits.paymentTimeLimit.dateTime')
                ?? data_get($json, 'paymentTimeLimit'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function normalizeOrderCreate(array $json): array
    {
        $order = is_array($json['order'] ?? null) ? $json['order'] : $json;

        return array_filter([
            'order_id' => trim((string) ($order['id'] ?? $order['orderId'] ?? '')),
            'owner_code' => trim((string) ($order['ownerCode'] ?? '')),
            'pnr_locator' => trim((string) ($order['pnrLocator'] ?? '')),
            'order_status' => trim((string) ($order['status'] ?? '')),
            'payment_status' => trim((string) ($order['paymentStatus'] ?? '')),
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function normalizeOrderRetrieve(array $json): array
    {
        return $this->normalizeOrderCreate($json);
    }
}
