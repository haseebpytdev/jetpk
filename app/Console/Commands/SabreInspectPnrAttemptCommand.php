<?php

namespace App\Console\Commands;

use App\Models\SupplierBookingAttempt;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Sabre\SabrePnrAttemptReadOnlyDiagnostics;
use Illuminate\Console\Command;

/**
 * V25-CPNR: Read-only supplier_booking_attempt diagnostics (no live Sabre HTTP, no DB mutation).
 */
class SabreInspectPnrAttemptCommand extends Command
{
    public const CONFIRM_PHRASE = 'READONLY-SABRE-PNR-ATTEMPT';

    protected $signature = 'sabre:inspect-pnr-attempt
                            {--attempt= : supplier_booking_attempt ID}
                            {--confirm= : Production: READONLY-SABRE-PNR-ATTEMPT}';

    protected $description = '[read-only] Safe Sabre PNR attempt diagnostics (pointer/message excerpts only)';

    public function handle(SabrePnrAttemptReadOnlyDiagnostics $diagnostics): int
    {
        if (! SabreInspectGate::allowed() && (string) config('app.env', 'production') === 'production') {
            $confirm = trim((string) $this->option('confirm'));
            if ($confirm !== self::CONFIRM_PHRASE) {
                $this->components->error('--confirm='.self::CONFIRM_PHRASE.' required on production.');

                return self::FAILURE;
            }
        }

        $attemptId = $this->option('attempt');
        if ($attemptId === null || ! is_numeric($attemptId)) {
            $this->components->error('Pass --attempt={supplier_booking_attempt_id}.');

            return self::FAILURE;
        }

        $attempt = SupplierBookingAttempt::query()->find((int) $attemptId);
        if ($attempt === null) {
            $this->components->error('Supplier booking attempt not found.');

            return self::FAILURE;
        }

        $summary = $diagnostics->summarizeAttempt($attempt);
        foreach ($summary as $key => $value) {
            $this->line($key.'='.json_encode($value, JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
