<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Suppliers\SupplierActionStrategyDigest;
use App\Support\Suppliers\SupplierActionStrategySelector;
use Illuminate\Console\Command;

/**
 * Universal read-only supplier strategy digest (delegates to provider adapter).
 */
class SupplierStrategyDigestCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-SUPPLIER-STRATEGY-DIGEST';

    protected $signature = 'supplier:strategy-digest
                            {--booking= : Booking ID}
                            {--provider=sabre : Supplier provider}
                            {--action=create_pnr : Lifecycle action}
                            {--confirm= : Production: READONLY-SUPPLIER-STRATEGY-DIGEST}';

    protected $description = '[read-only] Universal supplier create-strategy digest';

    public function handle(
        SupplierActionStrategyDigest $digest,
        SupplierActionStrategySelector $selector,
    ): int {
        if (! SabreInspectGate::allowed() && (string) config('app.env', 'production') === 'production') {
            $confirm = trim((string) $this->option('confirm'));
            if ($confirm !== self::CONFIRM_PHRASE) {
                $this->components->error('--confirm='.self::CONFIRM_PHRASE.' required on production.');

                return self::FAILURE;
            }
        }

        $bookingId = $this->option('booking');
        $provider = strtolower(trim((string) $this->option('provider')));
        $action = strtolower(trim((string) $this->option('action')));
        if ($bookingId === null || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id}.');

            return self::FAILURE;
        }

        $booking = Booking::query()->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $selection = $selector->selectForBooking($booking, $provider, $action);
        foreach ($digest->buildBookingSummary($booking, $provider, $action) as $key => $value) {
            $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
        }
        $this->newLine();

        foreach ($digest->buildCandidateDigests($booking, $provider, $action, $selection) as $index => $candidate) {
            $this->line('candidate['.$index.']');
            foreach ($candidate as $key => $value) {
                $this->line('  '.$key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
            }
            $this->newLine();
        }

        $this->line('selected_strategy='.(string) ($selection['selected_strategy'] ?? ''));
        $this->line('selection_reason='.(string) ($selection['selection_reason'] ?? ''));
        $this->line('fallback_available='.(($selection['fallback_available'] ?? false) ? 'true' : 'false'));

        return self::SUCCESS;
    }
}
