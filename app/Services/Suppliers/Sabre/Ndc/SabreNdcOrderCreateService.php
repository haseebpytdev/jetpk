<?php

namespace App\Services\Suppliers\Sabre\Ndc;

use App\Models\Booking;
use App\Models\SupplierConnection;

/**
 * Sabre NDC OrderCreate — controlled, default off.
 */
final class SabreNdcOrderCreateService
{
    public function __construct(
        private readonly SabreNdcPayloadBuilder $payloadBuilder,
        private readonly SabreNdcStatusService $statusService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Booking $booking, ?SupplierConnection $connection, bool $dryRun = true): array
    {
        $status = $this->statusService->status($connection);
        $blockers = is_array($status['blockers'] ?? null) ? $status['blockers'] : [];

        if ($dryRun) {
            $blockers[] = 'dry_run_only';
        }

        if (! (bool) config('suppliers.sabre.ndc.order_create_enabled', false)) {
            $blockers[] = 'order_create_disabled_by_env';
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $ndcContext = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        if (trim((string) ($ndcContext['offer_id'] ?? '')) === '') {
            $blockers[] = 'missing_offer_context';
        }

        return [
            'booking_id' => $booking->id,
            'live_supplier_call_attempted' => false,
            'blockers' => array_values(array_unique($blockers)),
            'confirm_phrase' => 'CREATE-NDC-ORDER-FOR-BOOKING-'.$booking->id,
            'endpoint_path' => config('suppliers.sabre.ndc.order_create_path'),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function persistOrderContext(Booking $booking, array $normalized): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $existing = is_array($meta['sabre_ndc_context'] ?? null) ? $meta['sabre_ndc_context'] : [];
        $meta['sabre_ndc_context'] = array_merge($existing, $normalized);
        $meta['distribution_channel'] = 'NDC';
        $booking->meta = $meta;
        $booking->save();
    }
}
