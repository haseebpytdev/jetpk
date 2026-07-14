<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Sabre NDC OrderChange — POST /v1/orders/change (Binham acceptOffers shape).
 */
final class SabreNdcOrderChangeService
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
    public function cancelPreview(Booking $booking, bool $dryRun = true): array
    {
        return $this->changeOrder($booking, null, [], $dryRun, 'cancel_preview');
    }

    /**
     * @param  array<string, mixed>  $acceptOfferContext  offer_id + selected_offer_items from reprice
     * @return array<string, mixed>
     */
    public function changeOrder(
        Booking $booking,
        ?SupplierConnection $connection,
        array $acceptOfferContext = [],
        bool $dryRun = true,
        string $action = 'accept_offers',
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        $orderId = trim((string) ($ndcContext['order_id'] ?? $booking->supplier_api_booking_id ?? ''));

        $reprice = is_array($ndcContext['reprice'] ?? null) ? $ndcContext['reprice'] : [];
        $offerId = trim((string) ($acceptOfferContext['offer_id'] ?? $reprice['offer_id'] ?? ''));
        $selectedItems = is_array($acceptOfferContext['selected_offer_items'] ?? null)
            ? $acceptOfferContext['selected_offer_items']
            : (is_array($reprice['selected_offer_items'] ?? null) ? $reprice['selected_offer_items'] : []);

        $blockers = [];
        if ($connection !== null) {
            $blockers = array_merge($blockers, $this->statusService->status($connection)['blockers'] ?? []);
        }
        if ($orderId === '') {
            $blockers[] = 'missing_order_id';
        }
        if ($action === 'accept_offers' && ($offerId === '' || $selectedItems === [])) {
            $blockers[] = 'missing_accept_offer_data';
        }
        if ($dryRun) {
            $blockers[] = 'dry_run_only';
        }
        if (! (bool) config('suppliers.sabre.ndc.order_change_enabled', false)) {
            $blockers[] = 'ndc_order_change_disabled';
        }

        $payload = $this->payloadBuilder->buildOrderChange($orderId, $offerId, $selectedItems);
        $base = [
            'booking_id' => $booking->id,
            'action' => $action,
            'blockers' => array_values(array_unique($blockers)),
            'live_supplier_call_attempted' => false,
            'endpoint_path' => config('suppliers.sabre.ndc.order_change_path', '/v1/orders/change'),
        ];

        if ($dryRun || $connection === null || $blockers !== []) {
            $base['payload_shape'] = array_keys($payload);

            return $base;
        }

        try {
            $path = (string) config('suppliers.sabre.ndc.order_change_path', '/v1/orders/change');
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $payload);
            $json = is_array($response->json()) ? $response->json() : [];
            $base['live_supplier_call_attempted'] = true;
            $base['http_status'] = $response->status();

            if (! $response->successful()) {
                $base['blockers'][] = 'order_change_http_'.$response->status();

                return $base;
            }

            $normalized = $this->normalizer->normalizeOrderCreate($json);
            $this->persistOrderChange($booking, $normalized, $json);
            $base['success'] = true;
            $base['order'] = SensitiveDataRedactor::redact($normalized);
        } catch (\Throwable $exception) {
            $base['blockers'][] = 'order_change_unexpected';
            $base['safe_error'] = $exception::class;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $json
     */
    private function persistOrderChange(Booking $booking, array $normalized, array $json): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        $ndcContext['order_change'] = SensitiveDataRedactor::redact(array_merge($normalized, [
            'changed_at' => now()->toIso8601String(),
            'response_digest' => md5(json_encode($json)),
        ]));
        $meta['sabre_ndc_context'] = $ndcContext;
        $booking->meta = $meta;
        if (! empty($normalized['pnr_locator'])) {
            $booking->pnr = (string) $normalized['pnr_locator'];
        }
        $booking->save();
    }
}
