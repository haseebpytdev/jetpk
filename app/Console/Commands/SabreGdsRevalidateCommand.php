<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\Gds\SabreGdsRevalidationService;
use App\Services\Suppliers\Sabre\Gds\SabreRevalidationPayloadBuilder;
use App\Support\Sabre\SabreMutationCommandGate;
use Illuminate\Console\Command;

class SabreGdsRevalidateCommand extends Command
{
    protected $signature = 'sabre:gds-revalidate
                            {--booking= : Booking ID}
                            {--connection= : Supplier connection ID}
                            {--dry-run : Preview only (default)}
                            {--send : Attempt live revalidation HTTP}
                            {--confirm= : REVALIDATE-FOR-BOOKING-{id}}';

    protected $description = 'Sabre GDS revalidation — dry-run default; live requires --send + env gates + --confirm';

    public function handle(
        SabreGdsRevalidationService $revalidationService,
        SabreBookingService $sabreBookingService,
    ): int {
        $booking = $this->resolveBooking();
        if ($booking === null) {
            $this->error('Booking not found.');

            return self::FAILURE;
        }

        $connection = $this->resolveConnection($booking);
        if ($connection === null) {
            $this->error('Sabre connection not found.');

            return self::FAILURE;
        }

        $expectedConfirm = 'REVALIDATE-FOR-BOOKING-'.$booking->id;
        $gate = SabreMutationCommandGate::evaluate(
            (bool) $this->option('dry-run'),
            $this->option('send') !== null ? '1' : null,
            $this->option('confirm'),
            $expectedConfirm,
            ['suppliers.sabre.booking_live_call_enabled', 'suppliers.sabre.revalidate_before_booking'],
        );

        if (! $gate['live_allowed']) {
            $offer = $this->offerFromBooking($booking);
            $draft = $sabreBookingService->prepareBookingPayload($offer, [
                'passengers' => [['type' => 'ADT', 'first_name' => 'TEST', 'last_name' => 'PASSENGER']],
            ]);
            unset($draft['_valid']);

            $payload = app(SabreRevalidationPayloadBuilder::class)
                ->buildPayload($draft, config('suppliers.sabre.revalidate_payload_style', 'iati_like_bfm_revalidate_v1'));

            $this->emitPayload([
                'mode' => 'dry_run',
                'booking_id' => $booking->id,
                'gate' => $gate,
                'expected_confirm' => $expectedConfirm,
                'payload_safe_summary' => app(SabreRevalidationPayloadBuilder::class)
                    ->safePayloadSummary($payload),
                'live_supplier_call_attempted' => false,
            ]);

            return self::SUCCESS;
        }

        $outcome = $revalidationService->revalidateForBooking($booking, $connection, true);
        $this->emitPayload([
            'mode' => 'live',
            'booking_id' => $booking->id,
            'success' => ($outcome['success'] ?? false) === true,
            'reason_code' => (string) ($outcome['reason_code'] ?? ''),
            'fare_comparison' => $outcome['fare_comparison'] ?? [],
            'linkage_digest' => $outcome['linkage_digest'] ?? [],
            'live_supplier_call_attempted' => true,
        ]);

        return ($outcome['success'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitPayload(array $payload): void
    {
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function resolveBooking(): ?Booking
    {
        $id = $this->option('booking');
        if ($id === null || ! is_numeric($id)) {
            return null;
        }

        return Booking::query()->with('passengers')->find((int) $id);
    }

    private function resolveConnection(Booking $booking): ?SupplierConnection
    {
        $connectionId = (int) $this->option('connection');
        if ($connectionId > 0) {
            return SupplierConnection::query()->find($connectionId);
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $fromMeta = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($fromMeta > 0) {
            return SupplierConnection::query()->find($fromMeta);
        }

        return SupplierConnection::query()->where('provider', 'sabre')->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function offerFromBooking(Booking $booking): array
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $snapshot = is_array($meta['offer_snapshot'] ?? null) ? $meta['offer_snapshot'] : [];
        if ($snapshot !== []) {
            return $snapshot;
        }

        return is_array($meta['sabre_booking_context']['offer'] ?? null)
            ? $meta['sabre_booking_context']['offer']
            : [];
    }
}
