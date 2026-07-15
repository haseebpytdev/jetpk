<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\Cancel\SabreBookingCancelService;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

class SabreProductionCancelEvidenceCommand extends Command
{
    protected $signature = 'sabre:production-cancel-evidence
                            {--booking= : Booking ID}
                            {--dry-run : Preview eligibility only (default)}
                            {--send : Attempt live cancel when gated}
                            {--confirm= : CANCEL-FOR-BOOKING-{id}}';

    protected $description = 'Production cancel evidence — endpoint, HTTP status, sanitized request/response summary';

    public function handle(SabreBookingCancelService $cancelService): int
    {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $expected = 'CANCEL-FOR-BOOKING-'.$booking->id;
        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expected,
            ['suppliers.sabre.cancel_enabled', 'suppliers.sabre.cancel_live_call_enabled'],
        );

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $pnr = trim((string) ($booking->pnr ?? ''));
        $ticketed = in_array((string) ($booking->ticketing_status ?? ''), ['ticketed', 'issued'], true)
            || $booking->tickets()->exists();

        $evidence = [
            'booking_id' => $booking->id,
            'pnr_present' => $pnr !== '',
            'ticketed' => $ticketed,
            'recommended_lane' => $pnr === '' ? 'local_cancel_only'
                : ($ticketed ? 'void_or_refund_path' : 'trip_orders_cancel_booking'),
            'endpoint_path' => config('suppliers.sabre.cancel_endpoint_path', '/v1/trip/orders/cancelBooking'),
            'gate' => $gate,
            'live_supplier_call_attempted' => false,
        ];

        if ($gate['live_allowed']) {
            $result = $cancelService->cancelForBooking($booking, true, [
                'source' => 'production_cancel_evidence_command',
                'admin_live_cancel_approved' => true,
            ]);
            $evidence['live_supplier_call_attempted'] = (bool) ($result['live_call_attempted'] ?? false);
            $evidence['http_status'] = data_get($result, 'cancel_probe.http_status');
            $evidence['classification'] = $result['classification'] ?? data_get($result, 'post_cancel_verification.classification');
            $evidence['supplier_cancel_verified'] = (bool) ($result['supplier_cancel_verified'] ?? false);
            $evidence['status'] = $result['status'] ?? null;
            $evidence['category'] = $result['category'] ?? null;
            $evidence['safe_summary'] = [
                'cancel_probe' => data_get($result, 'cancel_probe'),
                'post_cancel_verification' => data_get($result, 'post_cancel_verification'),
            ];
        }

        $this->line(json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');

        return is_numeric($id) ? Booking::query()->with('tickets')->find((int) $id) : null;
    }
}
