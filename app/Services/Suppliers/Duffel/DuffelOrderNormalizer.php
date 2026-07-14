<?php

namespace App\Services\Suppliers\Duffel;

use App\Support\Security\SensitiveDataRedactor;

class DuffelOrderNormalizer
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $order = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $diagnostic = is_array($payload['_ota_diagnostic'] ?? null) ? $payload['_ota_diagnostic'] : [];
        $orderId = trim((string) ($order['id'] ?? ''));
        $bookingReference = trim((string) ($order['booking_reference'] ?? ''));
        $pnr = trim((string) (
            data_get($order, 'metadata.pnr')
            ?? data_get($order, 'slices.0.segments.0.operating_carrier_pnr')
            ?? data_get($order, 'slices.0.segments.0.marketing_carrier_pnr')
            ?? $bookingReference
        ));
        $status = strtolower(trim((string) ($order['status'] ?? 'confirmed')));
        $total = $this->money($order['total_amount'] ?? 0);
        $currency = strtoupper((string) ($order['total_currency'] ?? $order['currency'] ?? 'USD'));

        return [
            'supplier_reference' => $orderId !== '' ? $orderId : null,
            'booking_reference' => $bookingReference !== '' ? $bookingReference : null,
            'pnr' => $pnr !== '' ? $pnr : null,
            'status' => $this->mapStatus($status),
            'created_at' => isset($order['created_at']) ? (string) $order['created_at'] : null,
            'safe_summary' => [
                'duffel_order_id' => $orderId !== '' ? $orderId : null,
                'booking_reference' => $bookingReference !== '' ? $bookingReference : null,
                'pnr' => $pnr !== '' ? $pnr : null,
                'status' => $status,
                'currency' => $currency,
                'total_amount' => $total,
                'passenger_count' => is_array($order['passengers'] ?? null) ? count($order['passengers']) : 0,
                'correlation_id' => isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
            ],
            'response_payload' => SensitiveDataRedactor::redact($payload),
            'correlation_id' => isset($diagnostic['correlation_id']) ? (string) $diagnostic['correlation_id'] : null,
            'duration_ms' => isset($diagnostic['duration_ms']) ? (int) $diagnostic['duration_ms'] : null,
        ];
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'failed', 'rejected', 'cancelled', 'expired' => 'failed',
            'pending', 'awaiting_payment', 'on_hold' => 'pending_ticketing',
            default => 'created',
        };
    }

    private function money(mixed $value): float
    {
        if (is_string($value)) {
            $value = str_replace(',', '', trim($value));
        }

        return (float) $value;
    }
}
