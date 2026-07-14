<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabreSegmentFreshShopSellabilityService;
use Illuminate\Console\Command;

/**
 * B76 local/testing-only fresh Offers-shop diagnostic per stored booking segment (no Passenger Records / PNR / ticketing).
 */
class SabreDiagnoseBookingSegmentSellabilityCommand extends Command
{
    protected $signature = 'sabre:diagnose-booking-segment-sellability
                            {booking_id : Booking primary key}
                            {--connection= : Sabre SupplierConnection ID (defaults from snapshot or first Sabre connection for booking agency)}';

    protected $description = '[local/testing only] Fresh Sabre shop vs stored snapshot segments — safe JSON only (no raw Sabre response, no Passenger Records).';

    public function handle(SabreSegmentFreshShopSellabilityService $sellability): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->line(json_encode([
                'error' => 'environment_not_allowed',
                'booking_id' => (int) $this->argument('booking_id'),
                'segment_count' => 0,
                'segments' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $bookingId = (int) $this->argument('booking_id');
        $booking = Booking::query()->find($bookingId);
        if ($booking === null) {
            $this->line(json_encode([
                'error' => 'booking_not_found',
                'booking_id' => $bookingId,
                'segment_count' => 0,
                'segments' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = $this->resolveOfferSnapshot($meta);
        if ($snapshot === null) {
            $this->line(json_encode([
                'booking_id' => $bookingId,
                'error' => 'missing_offer_snapshot',
                'segment_count' => 0,
                'segments' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $supplierBooking = strtolower(trim((string) ($booking->supplier ?? '')));
        $supplierSnap = strtolower(trim((string) ($snapshot['supplier_provider'] ?? '')));
        if ($supplierBooking !== 'sabre' && $supplierSnap !== 'sabre') {
            $this->line(json_encode([
                'booking_id' => $bookingId,
                'error' => 'not_sabre_booking',
                'segment_count' => 0,
                'segments' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking, $snapshot);
        if ($connection === null) {
            $this->line(json_encode([
                'booking_id' => $bookingId,
                'error' => 'missing_sabre_connection',
                'segment_count' => 0,
                'segments' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::FAILURE;
        }

        $criteria = is_array($meta['search_criteria'] ?? null) ? $meta['search_criteria'] : [];
        $offerForService = array_merge($snapshot, $criteria !== [] ? ['search_criteria' => $criteria] : []);
        if (trim((string) ($booking->currency ?? '')) !== '') {
            $offerForService['currency'] = $booking->currency;
        }
        $segmentReports = $sellability->segmentReportsForOffer($offerForService, $connection);

        $this->line(json_encode([
            'booking_id' => $bookingId,
            'segment_count' => count($segmentReports),
            'note' => 'Per-segment one-way shop may differ from full-itinerary priced shop (see sabre:refresh-booking-offer).',
            'segments' => $segmentReports,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>|null
     */
    protected function resolveOfferSnapshot(array $meta): ?array
    {
        foreach (['normalized_offer_snapshot', 'validated_offer_snapshot', 'flight_offer_snapshot'] as $key) {
            $snap = $meta[$key] ?? null;
            if (is_array($snap) && $snap !== []) {
                return $snap;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function resolveConnection(Booking $booking, array $snapshot): ?SupplierConnection
    {
        $opt = trim((string) ($this->option('connection') ?? ''));
        $base = SupplierConnection::query()
            ->where('provider', SupplierProvider::Sabre)
            ->where('agency_id', $booking->agency_id);

        if ($opt !== '') {
            /** @var SupplierConnection|null $c */
            $c = (clone $base)->whereKey((int) $opt)->first();

            return $c;
        }

        $sid = isset($snapshot['supplier_connection_id']) ? (int) $snapshot['supplier_connection_id'] : 0;
        if ($sid > 0) {
            /** @var SupplierConnection|null $c */
            $c = (clone $base)->whereKey($sid)->first();
            if ($c !== null) {
                return $c;
            }
        }

        /** @var SupplierConnection|null */
        return (clone $base)->orderBy('id')->first();
    }
}
