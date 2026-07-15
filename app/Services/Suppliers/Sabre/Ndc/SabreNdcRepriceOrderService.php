<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Sabre NDC repriceOrder — POST /v1/offers/repriceOrder (Binham parity).
 */
final class SabreNdcRepriceOrderService
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
    public function repriceOrder(
        Booking $booking,
        SupplierConnection $connection,
        bool $dryRun = true,
    ): array {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        $orderId = trim((string) ($ndcContext['order_id'] ?? $booking->supplier_api_booking_id ?? ''));

        $blockers = $this->statusService->status($connection)['blockers'] ?? [];
        if ($orderId === '') {
            $blockers[] = 'missing_order_id';
        }
        if (! str_starts_with(strtoupper($orderId), '1S')) {
            $blockers[] = 'invalid_ndc_order_id_prefix';
        }

        $payload = $this->payloadBuilder->buildRepriceOrder($orderId);
        $base = [
            'booking_id' => $booking->id,
            'order_id_masked' => $orderId !== '' ? substr($orderId, 0, 4).'...' : null,
            'endpoint_path' => config('suppliers.sabre.ndc.reprice_order_path', '/v1/offers/repriceOrder'),
            'blockers' => array_values(array_unique($blockers)),
            'live_supplier_call_attempted' => false,
        ];

        if ($dryRun || $blockers !== []) {
            $base['payload_shape'] = array_keys($payload);

            return $base;
        }

        try {
            $path = (string) config('suppliers.sabre.ndc.reprice_order_path', '/v1/offers/repriceOrder');
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $payload);
            $json = is_array($response->json()) ? $response->json() : [];
            $base['live_supplier_call_attempted'] = true;
            $base['http_status'] = $response->status();

            if (! $response->successful()) {
                $base['blockers'][] = 'reprice_http_'.$response->status();

                return $base;
            }

            $offerInfo = $this->extractRepriceOffer($json);
            if (($offerInfo['status'] ?? false) !== true) {
                $base['blockers'][] = 'reprice_no_usable_offer';

                return $base;
            }

            $this->persistRepriceContext($booking, $offerInfo, $json);
            $base['success'] = true;
            $base['offer'] = SensitiveDataRedactor::redact($offerInfo);
        } catch (\Throwable $exception) {
            $base['blockers'][] = 'reprice_unexpected';
            $base['safe_error'] = $exception::class;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    public function extractRepriceOffer(array $json): array
    {
        $offers = $json['response']['offers'] ?? $json['offers'] ?? [];
        $offer = is_array($offers) && ! empty($offers[0]) && is_array($offers[0]) ? $offers[0] : [];
        if ($offer === []) {
            return ['status' => false, 'message' => 'No offer returned by NDC repriceOrder.'];
        }

        $total = (float) data_get($offer, 'price.totalAmount.amount', data_get($offer, 'totalPrice.totalAmount.amount', 0));
        $currency = strtoupper(trim((string) data_get($offer, 'price.totalAmount.currency', data_get($offer, 'totalPrice.totalAmount.currency', ''))));
        $tax = (float) data_get($offer, 'price.taxSummary.totalTaxAmount.amount', data_get($offer, 'totalTaxAmount.amount', 0));

        $selectedOfferItems = [];
        foreach ($offer['offerItems'] ?? [] as $offerItem) {
            if (! is_array($offerItem)) {
                continue;
            }
            $passengerRefIds = [];
            foreach ($offerItem['passengers'] ?? [] as $passenger) {
                if (! is_array($passenger)) {
                    continue;
                }
                $ref = $passenger['passengerRef'] ?? $passenger['passengerRefId'] ?? $passenger['id'] ?? null;
                if (is_scalar($ref)) {
                    $passengerRefIds[] = (string) $ref;
                }
            }
            foreach ($offerItem['passengerRefIds'] ?? [] as $ref) {
                if (is_scalar($ref)) {
                    $passengerRefIds[] = (string) $ref;
                }
            }
            $passengerRefIds = array_values(array_unique(array_filter($passengerRefIds)));
            if ($passengerRefIds === []) {
                $passengerRefIds = ['Passenger1'];
            }

            $itemId = $offerItem['offerItemId'] ?? $offerItem['id'] ?? null;
            if ($itemId) {
                $selectedOfferItems[] = [
                    'id' => (string) $itemId,
                    'passengerRefIds' => $passengerRefIds,
                ];
            }
        }

        $offerId = (string) ($offer['offerId'] ?? $offer['id'] ?? '');
        if ($offerId === '' || $selectedOfferItems === [] || $total <= 0) {
            return [
                'status' => false,
                'message' => 'NDC repriceOrder returned an offer without usable IDs or price.',
                'offer_id' => $offerId,
            ];
        }

        return [
            'status' => true,
            'offer_id' => $offerId,
            'selected_offer_items' => $selectedOfferItems,
            'total' => $total,
            'tax' => $tax,
            'currency' => $currency !== '' ? $currency : 'USD',
            'expiration' => data_get($offer, 'offerExpirationDateTime') ?? data_get($offer, 'paymentTimeLimit'),
        ];
    }

    /**
     * @param  array<string, mixed>  $offerInfo
     * @param  array<string, mixed>  $json
     */
    private function persistRepriceContext(Booking $booking, array $offerInfo, array $json): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];

        $ndcContext['reprice'] = SensitiveDataRedactor::redact([
            'repriced_at' => now()->toIso8601String(),
            'offer_id' => $offerInfo['offer_id'] ?? null,
            'selected_offer_items' => $offerInfo['selected_offer_items'] ?? [],
            'total' => $offerInfo['total'] ?? null,
            'tax' => $offerInfo['tax'] ?? null,
            'currency' => $offerInfo['currency'] ?? null,
            'expiration' => $offerInfo['expiration'] ?? null,
            'response_digest' => md5(json_encode($json)),
        ]);

        $meta['sabre_ndc_context'] = $ndcContext;
        $booking->meta = $meta;
        $booking->revalidated_fare_total = isset($offerInfo['total']) ? (float) $offerInfo['total'] : $booking->revalidated_fare_total;
        $booking->fare_revalidated_at = now();
        $booking->save();
    }
}
