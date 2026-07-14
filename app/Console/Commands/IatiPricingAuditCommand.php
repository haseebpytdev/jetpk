<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Pricing\IatiPricingRepairService;
use Illuminate\Console\Command;

class IatiPricingAuditCommand extends Command
{
    protected $signature = 'ota:iati-pricing-audit {--booking-id= : Booking id to inspect (read-only)}';

    protected $description = 'Read-only IATI pricing audit for double USD→PKR conversion on persisted bookings';

    public function handle(IatiPricingRepairService $repairService): int
    {
        $bookingId = (int) $this->option('booking-id');
        if ($bookingId <= 0) {
            $this->error('Provide --booking-id=.');

            return self::FAILURE;
        }

        $booking = Booking::query()->with(['fareBreakdown', 'holdSession', 'supplierBookingAttempts'])->find($bookingId);
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

        $report = $repairService->audit($booking);
        foreach ($report as $key => $value) {
            if (is_bool($value)) {
                $this->line($key.'='.($value ? 'true' : 'false'));

                continue;
            }
            if (is_array($value)) {
                $this->line($key.'='.json_encode($value, JSON_UNESCAPED_UNICODE));

                continue;
            }
            $this->line($key.'='.(string) ($value ?? ''));
        }

        return ($report['detected_double_conversion'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
