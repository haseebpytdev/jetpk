<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Sabre NDC order retrieve — /v1/orders/view then /v1/ndc/orders/retrieve fallback (Binham parity).
 */
final class SabreNdcOrderRetrieveService
{
    public function __construct(
        private readonly SabreNdcPayloadBuilder $payloadBuilder,
        private readonly SabreNdcResponseNormalizer $normalizer,
        private readonly SabreNdcStatusService $statusService,
        private readonly SabreClient $sabreClient,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, bool $dryRun = true): array
    {
        return $this->retrieveOrder($booking, null, $dryRun);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveOrder(Booking $booking, ?SupplierConnection $connection, bool $dryRun = true): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        $orderId = trim((string) ($ndcContext['order_id'] ?? $booking->supplier_api_booking_id ?? $booking->supplier_reference ?? ''));

        $blockers = [];
        if ($orderId === '') {
            $blockers[] = 'missing_order_id';
        }
        if ($dryRun) {
            $blockers[] = 'dry_run_only';
        }
        if ($connection !== null) {
            $blockers = array_merge($blockers, $this->statusService->status($connection)['blockers'] ?? []);
        }
        if (! (bool) config('suppliers.sabre.ndc.enabled', false)) {
            $blockers[] = 'sabre_ndc_disabled';
        }

        $base = [
            'booking_id' => $booking->id,
            'order_id_present' => $orderId !== '',
            'order_id_masked' => $orderId !== '' ? substr($orderId, 0, 4).'...' : null,
            'live_supplier_call_attempted' => false,
            'blockers' => array_values(array_unique($blockers)),
        ];

        if ($dryRun || $connection === null || in_array('missing_order_id', $blockers, true)) {
            return $base;
        }

        $viewPath = (string) config('suppliers.sabre.ndc.order_view_path', '/v1/orders/view');
        $retrievePath = (string) config('suppliers.sabre.ndc.order_retrieve_path', '/v1/ndc/orders/retrieve');

        try {
            $viewPayload = $this->payloadBuilder->buildOrderView($orderId);
            $viewResponse = $this->sabreClient->postAuthenticatedJson($connection, $viewPath, $viewPayload);
            $viewJson = is_array($viewResponse->json()) ? $viewResponse->json() : [];

            $retrieveJson = [];
            if (! $viewResponse->successful() || ! $this->hasFlights($viewJson)) {
                $retrievePayload = $this->payloadBuilder->buildOrderRetrieve($orderId);
                $retrieveResponse = $this->sabreClient->postAuthenticatedJson($connection, $retrievePath, $retrievePayload);
                $retrieveJson = is_array($retrieveResponse->json()) ? $retrieveResponse->json() : [];
            }

            $merged = $retrieveJson !== [] ? $retrieveJson : $viewJson;
            $base['live_supplier_call_attempted'] = true;
            $snapshot = $this->normalizeRetrieveSnapshot($merged);
            $this->persistRetrieveSnapshot($booking, $snapshot, $merged);
            $base['success'] = true;
            $base['snapshot'] = SensitiveDataRedactor::redact($snapshot);
        } catch (\Throwable $exception) {
            $base['blockers'][] = 'retrieve_unexpected';
            $base['safe_error'] = $exception::class;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $json
     */
    private function hasFlights(array $json): bool
    {
        $flights = $json['flights'] ?? data_get($json, 'order.flights');

        return is_array($flights) && $flights !== [];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function normalizeRetrieveSnapshot(array $json): array
    {
        $order = is_array($json['order'] ?? null) ? $json['order'] : $json;

        return array_filter([
            'order_id' => trim((string) ($order['id'] ?? $order['orderId'] ?? '')),
            'confirmation_id' => trim((string) ($order['pnrLocator'] ?? $order['confirmationId'] ?? '')),
            'source_type' => 'NDC',
            'is_ticketed' => (bool) ($order['isTicketed'] ?? $json['isTicketed'] ?? false),
            'is_cancelable' => (bool) ($order['isCancelable'] ?? $json['isCancelable'] ?? false),
            'order_status' => trim((string) ($order['status'] ?? '')),
            'payment_status' => trim((string) ($order['paymentStatus'] ?? '')),
            'future_ticketing_policy' => data_get($order, 'futureTicketingPolicy') ?? data_get($json, 'futureTicketingPolicy'),
            'flight_count' => is_array($order['flights'] ?? $json['flights'] ?? null)
                ? count($order['flights'] ?? $json['flights'])
                : 0,
            'fare_offer_count' => is_array($order['fareOffers'] ?? $json['fareOffers'] ?? null)
                ? count($order['fareOffers'] ?? $json['fareOffers'])
                : 0,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<string, mixed>  $json
     */
    private function persistRetrieveSnapshot(Booking $booking, array $snapshot, array $json): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        $ndcContext['retrieve'] = SensitiveDataRedactor::redact(array_merge($snapshot, [
            'retrieved_at' => now()->toIso8601String(),
            'response_digest' => md5(json_encode($json)),
        ]));
        $meta['sabre_ndc_context'] = $ndcContext;
        $meta['provider_context'] = array_merge(
            is_array($meta['provider_context'] ?? null) ? $meta['provider_context'] : [],
            ['source_type' => 'NDC', 'source_channel' => 'ndc'],
        );
        $booking->meta = $meta;
        if (! empty($snapshot['confirmation_id'])) {
            $booking->pnr = (string) $snapshot['confirmation_id'];
        }
        $booking->save();
    }
}
