<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Bookings\IatiPersistedContextResolver;
use Illuminate\Console\Command;

class IatiBookingReadinessCommand extends Command
{
    protected $signature = 'ota:iati-booking-readiness {--booking-id= : Booking id to inspect (read-only)}';

    protected $description = 'Read-only IATI supplier booking readiness for a persisted booking snapshot (no live API calls)';

    public function handle(): int
    {
        $bookingId = (int) $this->option('booking-id');
        if ($bookingId <= 0) {
            $this->error('Provide --booking-id=.');

            return self::FAILURE;
        }

        $booking = Booking::query()->with(['passengers', 'contact', 'supplierBookings'])->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found: '.$bookingId);

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));

        if ($provider !== SupplierProvider::Iati->value) {
            $this->warn('Booking provider is not iati (provider='.$provider.').');

            return self::FAILURE;
        }

        $report = IatiPersistedContextResolver::readiness($booking);
        $readinessEligible = (bool) ($report['eligible_for_supplier_book'] ?? false);

        foreach ($report as $key => $value) {
            if (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_UNICODE));

                continue;
            }
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));

                continue;
            }
            $this->line($key.'='.(string) ($value ?? ''));
        }

        return $readinessEligible ? self::SUCCESS : self::FAILURE;
    }
}
