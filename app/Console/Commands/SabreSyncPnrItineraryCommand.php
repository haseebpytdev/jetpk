<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Services\Suppliers\Sabre\SabrePnrItinerarySyncService;
use Illuminate\Console\Command;

class SabreSyncPnrItineraryCommand extends Command
{
    protected $signature = 'sabre:sync-pnr-itinerary
                            {--booking= : Booking ID with Sabre PNR}
                            {--dry-run : Call getBooking and print safe preview without DB writes}';

    protected $description = '[local/testing only] B84B.2: Sync sanitized Trip Orders getBooking itinerary into meta.pnr_itinerary_snapshot';

    public function handle(SabrePnrItinerarySyncService $syncService): int
    {
        if (! SabreInspectGate::allowed()) {
            $this->components->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $bookingId = $this->option('booking');
        if ($bookingId === null || $bookingId === '' || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id} with a numeric booking id.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->line('Dry-run: POST getBooking, mapper preview only (no DB writes, no ticketing).');
        } else {
            $this->line('Sync: POST getBooking and write sanitized meta.pnr_itinerary_snapshot when safe.');
        }
        $this->newLine();

        $payload = $syncService->sync($booking, $dryRun);
        unset($payload['json']);

        if (isset($payload['error'])) {
            $this->line('pnr_itinerary_sync_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));
            $this->components->error((string) $payload['error']);

            return self::FAILURE;
        }

        $this->line('pnr_itinerary_sync_json='.json_encode($payload, JSON_UNESCAPED_SLASHES));

        if ($dryRun) {
            return self::SUCCESS;
        }

        if (($payload['synced'] ?? false) === true) {
            return self::SUCCESS;
        }

        $this->components->warn('Sync blocked; see reason_code (existing snapshot preserved if present).');

        return self::SUCCESS;
    }
}
