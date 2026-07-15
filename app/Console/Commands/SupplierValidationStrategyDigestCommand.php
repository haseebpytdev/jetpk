<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Suppliers\SupplierValidationActionCode;
use App\Support\Suppliers\SupplierValidationStrategyDigest;
use App\Support\Suppliers\SupplierValidationStrategySelector;
use Illuminate\Console\Command;

/**
 * Read-only supplier validation/freshness strategy digest (no live supplier call, no booking mutation).
 */
class SupplierValidationStrategyDigestCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-SUPPLIER-VALIDATION-STRATEGY-DIGEST';

    protected $signature = 'supplier:validation-strategy-digest
                            {--booking= : Booking ID}
                            {--action=gds_pre_pnr_freshness : Validation action code}
                            {--confirm= : Production: READONLY-SUPPLIER-VALIDATION-STRATEGY-DIGEST}';

    protected $description = '[read-only] Supplier validation/freshness strategy digest';

    public function handle(
        SupplierValidationStrategyDigest $digest,
        SupplierValidationStrategySelector $selector,
    ): int {
        if (! SabreInspectGate::allowed() && (string) config('app.env', 'production') === 'production') {
            $confirm = trim((string) $this->option('confirm'));
            if ($confirm !== self::CONFIRM_PHRASE) {
                $this->components->error('--confirm='.self::CONFIRM_PHRASE.' required on production.');

                return self::FAILURE;
            }
        }

        $bookingId = $this->option('booking');
        $action = strtolower(trim((string) $this->option('action')));
        if ($bookingId === null || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id}.');

            return self::FAILURE;
        }
        if (! SupplierValidationActionCode::isSupported($action)) {
            $this->components->error('Unsupported --action='.$action);

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $selection = $selector->selectForBooking($booking, $action);
        foreach ($digest->buildBookingSummary($booking, $action) as $key => $value) {
            $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
        }
        $this->newLine();

        foreach ($digest->buildCandidateDigests($booking, $action, $selection) as $index => $candidate) {
            $this->line('candidate['.$index.']');
            foreach ($candidate as $key => $value) {
                $this->line('  '.$key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
            }
            $this->newLine();
        }

        $this->line('selected_strategy='.(string) ($selection['selected_strategy'] ?? ''));
        $this->line('selection_reason='.(string) ($selection['selection_reason'] ?? ''));
        $this->line('fallback_available='.(($selection['fallback_available'] ?? false) ? 'true' : 'false'));
        $this->line('automatic_multi_strategy_retry=false');

        return self::SUCCESS;
    }
}
