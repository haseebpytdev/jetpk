<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Support\Pricing\IatiPricingRepairService;
use Illuminate\Console\Command;

class IatiRepairPricingCommand extends Command
{
    protected $signature = 'ota:iati-repair-pricing
                            {--booking-id= : Booking id to repair}
                            {--dry-run : Report planned changes without writing}
                            {--apply : Apply safe repair to unpaid booking without supplier order}';

    protected $description = 'Repair IATI bookings with inflated totals from mistaken USD conversion';

    public function handle(IatiPricingRepairService $repairService): int
    {
        $bookingId = (int) $this->option('booking-id');
        if ($bookingId <= 0) {
            $this->error('Provide --booking-id=.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $dryRun = (bool) $this->option('dry-run') || ! $apply;
        if ($apply && $this->option('dry-run')) {
            $this->error('Use either --dry-run or --apply, not both.');

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

        $audit = $repairService->audit($booking);
        foreach ($audit as $key => $value) {
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

        $result = $repairService->repair($booking, $apply);
        if (($result['blockers'] ?? []) !== []) {
            $this->error('Repair blocked: '.implode(', ', $result['blockers']));

            return self::FAILURE;
        }

        $this->line('planned_changes='.json_encode($result['changes'] ?? [], JSON_UNESCAPED_UNICODE));
        $this->line('planned_residual_changes='.json_encode($result['planned_residual_changes'] ?? [], JSON_UNESCAPED_UNICODE));
        if ($dryRun) {
            $this->info('Dry run only — no database changes made.');

            return self::SUCCESS;
        }

        $this->info('Repair applied.');

        return self::SUCCESS;
    }
}
