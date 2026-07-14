<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\Sabre\Booking\SabreBookingService;
use App\Services\Suppliers\Sabre\SabreInspectGate;
use App\Support\Bookings\SupplierBookingAttemptGuard;
use App\Support\Sabre\GdsPnrCreate\SabreGdsPnrCreateStrategyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Admin/operator-confirmed live Sabre GDS PNR create with an explicit strategy (single attempt; no automatic fallback chain).
 */
class SabreGdsPnrCreateWithStrategyCommand extends Command
{
    public const CONFIRM_PHRASE = 'CREATE-SABRE-GDS-PNR-WITH-STRATEGY';

    public const PRODUCTION_OPS_APPROVAL_PHRASE = 'APPROVE-PRODUCTION-SABRE-GDS-PNR-CREATE';

    protected $signature = 'sabre:gds-pnr-create-with-strategy
                            {--booking= : Booking ID}
                            {--strategy= : Strategy code from sabre:gds-pnr-strategy-digest}
                            {--confirm= : Required: CREATE-SABRE-GDS-PNR-WITH-STRATEGY}
                            {--production-ops-approval= : Production only: APPROVE-PRODUCTION-SABRE-GDS-PNR-CREATE}';

    protected $description = '[admin/operator] Live Sabre GDS PNR create with one explicit strategy (requires confirmation)';

    public function handle(
        SabreBookingService $sabreBooking,
        SabreGdsPnrCreateStrategyRegistry $registry,
        SupplierBookingAttemptGuard $attemptGuard,
    ): int {
        $productionOpsApproved = $this->resolveProductionGate();
        if ($productionOpsApproved === null) {
            return self::FAILURE;
        }

        $confirm = trim((string) $this->option('confirm'));
        if ($confirm !== self::CONFIRM_PHRASE) {
            $this->components->error('--confirm='.self::CONFIRM_PHRASE.' is required.');

            return self::FAILURE;
        }

        $bookingId = $this->option('booking');
        $strategy = trim((string) $this->option('strategy'));
        if ($bookingId === null || ! is_numeric($bookingId)) {
            $this->components->error('Pass --booking={id}.');

            return self::FAILURE;
        }
        if ($strategy === '' || ! $registry->isSupported($strategy)) {
            $this->components->error('Pass a supported --strategy code.');

            return self::FAILURE;
        }

        $booking = Booking::query()->with(['passengers', 'contact', 'supplierBookings', 'supplierBookingAttempts'])->find((int) $bookingId);
        if ($booking === null) {
            $this->components->error('Booking not found.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_pnr_strategy_create_'.$booking->id, 120);
        if (! $lock->get()) {
            $this->printPreflightFailureLines($productionOpsApproved, $booking->id, $strategy, false, [
                'duplicate_protection_lock_active',
            ], 'duplicate_protection_lock_active');

            return self::FAILURE;
        }

        try {
            $guard = $attemptGuard->assertRetryAllowed($booking, SupplierProvider::Sabre->value);
            if (($guard['blocked'] ?? false) === true) {
                $reasonCode = (string) ($guard['reason_code'] ?? 'supplier_booking_in_progress');
                $this->printPreflightFailureLines($productionOpsApproved, $booking->id, $strategy, false, [$reasonCode], $reasonCode);

                return self::FAILURE;
            }

            $result = $sabreBooking->createBookingWithStrategyForAdminFallback($booking, $strategy);

            $preflightPassed = ($result['preflight_passed'] ?? false) === true;
            $liveCallAttempted = ($result['live_call_attempted'] ?? false) === true;
            $blockingConditions = is_array($result['blocking_conditions'] ?? null)
                ? $result['blocking_conditions']
                : [];
            $reasonCode = (string) ($result['reason_code'] ?? '');

            $this->printOutcomeLines(
                $productionOpsApproved,
                $booking->id,
                $strategy,
                $preflightPassed,
                $liveCallAttempted,
                $reasonCode,
                $blockingConditions,
            );

            $pnr = trim((string) ($result['pnr'] ?? ''));
            $this->line('success='.(($result['success'] ?? false) ? 'true' : 'false'));
            $this->line('status='.(string) ($result['status'] ?? ''));
            $this->line('pnr='.($pnr !== '' ? $pnr : '—'));

            if ($pnr === '' && $liveCallAttempted) {
                $this->components->warn('Live attempt completed without PNR locator — stored for needs_review.');
            }

            return ($result['success'] ?? false) === true ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $blockingConditions
     */
    protected function printOutcomeLines(
        bool $productionOpsApproved,
        int $bookingId,
        string $strategy,
        bool $preflightPassed,
        bool $liveCallAttempted,
        string $reasonCode,
        array $blockingConditions,
    ): void {
        if ($productionOpsApproved) {
            $this->line('production_ops_approved=true');
        }
        $this->line('preflight_passed='.($preflightPassed ? 'true' : 'false'));
        $this->line('live_supplier_call_attempted='.($liveCallAttempted ? 'true' : 'false'));
        $this->line('ticketing_attempted=false');
        $this->line('cancellation_attempted=false');
        $this->line('booking_id='.$bookingId);
        $this->line('strategy='.$strategy);
        $this->line('reason_code='.$reasonCode);
        $this->line('blocking_conditions='.$this->encodeBlockingConditions($blockingConditions));
        $this->newLine();
    }

    /**
     * @param  list<string>  $blockingConditions
     */
    protected function printPreflightFailureLines(
        bool $productionOpsApproved,
        int $bookingId,
        string $strategy,
        bool $preflightPassed,
        array $blockingConditions,
        string $reasonCode,
    ): void {
        $this->printOutcomeLines(
            $productionOpsApproved,
            $bookingId,
            $strategy,
            $preflightPassed,
            false,
            $reasonCode,
            $blockingConditions,
        );
        $this->components->error('Admin manual PNR fallback blocked before supplier call.');
    }

    /**
     * @param  list<string>  $blockingConditions
     */
    protected function encodeBlockingConditions(array $blockingConditions): string
    {
        if ($blockingConditions === []) {
            return '[]';
        }

        return json_encode(array_values($blockingConditions), JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @return bool|null true when production ops approved; false when local/testing; null when blocked
     */
    protected function resolveProductionGate(): ?bool
    {
        if (SabreInspectGate::allowed()) {
            return false;
        }

        $env = (string) config('app.env', 'production');
        if ($env !== 'production') {
            return false;
        }

        $approval = trim((string) $this->option('production-ops-approval'));
        if ($approval === self::PRODUCTION_OPS_APPROVAL_PHRASE) {
            return true;
        }

        if ($approval === '') {
            $this->components->error(
                'Production PNR create requires --production-ops-approval='.self::PRODUCTION_OPS_APPROVAL_PHRASE
            );
        } else {
            $this->components->error('Invalid --production-ops-approval phrase for production PNR create.');
        }

        return null;
    }
}
