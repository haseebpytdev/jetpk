<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Core\SabreClient;
use App\Support\Security\SensitiveDataRedactor;

/**
 * Sabre NDC Offer Price — POST /v1/offers/price with live path when gated.
 */
final class SabreNdcOfferPriceService
{
    public function __construct(
        private readonly SabreNdcPayloadBuilder $payloadBuilder,
        private readonly SabreNdcResponseNormalizer $normalizer,
        private readonly SabreNdcStatusService $statusService,
        private readonly SabreClient $sabreClient,
    ) {}

    /**
     * @param  array<string, mixed>  $offerContext
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, ?SupplierConnection $connection, array $offerContext = [], bool $dryRun = true): array
    {
        return $this->offerPrice($booking, $connection, $offerContext, $dryRun);
    }

    /**
     * @param  array<string, mixed>  $offerContext
     * @return array<string, mixed>
     */
    public function offerPrice(
        Booking $booking,
        ?SupplierConnection $connection,
        array $offerContext = [],
        bool $dryRun = true,
    ): array {
        $status = $this->statusService->status($connection);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];

        $context = array_merge($ndcContext, $offerContext);
        $payload = $this->payloadBuilder->buildOfferPrice($context);

        $blockers = $status['blockers'] ?? [];
        if ($dryRun) {
            $blockers[] = 'dry_run_only';
        }
        if (trim((string) ($context['offer_id'] ?? '')) === '') {
            $blockers[] = 'missing_offer_id';
        }
        if (! (bool) config('suppliers.sabre.ndc.offer_price_enabled', false)) {
            $blockers[] = 'ndc_offer_price_disabled';
        }

        $base = [
            'booking_id' => $booking->id,
            'live_supplier_call_attempted' => false,
            'blockers' => array_values(array_unique($blockers)),
            'payload_shape_ready' => trim((string) ($context['offer_id'] ?? '')) !== '',
            'endpoint_path' => config('suppliers.sabre.ndc.offer_price_path'),
        ];

        if ($dryRun || $connection === null || $blockers !== []) {
            return $base;
        }

        try {
            $path = (string) config('suppliers.sabre.ndc.offer_price_path', '/v1/offers/price');
            $response = $this->sabreClient->postAuthenticatedJson($connection, $path, $payload);
            $json = is_array($response->json()) ? $response->json() : [];
            $base['live_supplier_call_attempted'] = true;
            $base['http_status'] = $response->status();

            if (! $response->successful()) {
                $base['blockers'][] = 'offer_price_http_'.$response->status();

                return $base;
            }

            $normalized = $this->normalizer->normalizeOfferPrice($json);
            $meta['sabre_ndc_context'] = array_merge($ndcContext, [
                'offer_price' => SensitiveDataRedactor::redact($normalized),
            ]);
            $booking->meta = $meta;
            $booking->save();

            $base['success'] = true;
            $base['offer_price'] = SensitiveDataRedactor::redact($normalized);
        } catch (\Throwable $exception) {
            $base['blockers'][] = 'offer_price_unexpected';
            $base['safe_error'] = $exception::class;
        }

        return $base;
    }
}
