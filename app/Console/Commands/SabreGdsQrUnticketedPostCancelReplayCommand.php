<?php

namespace App\Console\Commands;

use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelReplayLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Zero-call replay of persisted post-cancel evidence; dry-run default; local-only closure on apply.
 */
class SabreGdsQrUnticketedPostCancelReplayCommand extends Command
{
    protected $signature = 'sabre:gds-qr-unticketed-post-cancel-replay
                            {--booking-id= : Booking ID (production apply requires 3)}
                            {--supplier-booking-id= : Supplier booking id (production apply requires 2)}
                            {--prior-cancellation-lifecycle-run-id= : Phase 14 lifecycle_run_id}
                            {--post-cancel-retrieve-lifecycle-run-id= : Phase 15 lifecycle_run_id}
                            {--retrieve-attempt-id= : pnr_retrieve attempt id (production apply requires 9)}
                            {--lifecycle-run-id= : Optional replay lifecycle id}
                            {--dry-run : Dry-run replay (default)}
                            {--apply-local-closure : Apply local-only DB closure (no supplier calls)}
                            {--confirm-local-closure= : Apply: APPROVE-LOCAL-SABRE-GDS-POST-CANCEL-ZERO-SEGMENT-CLOSURE}
                            {--confirm-replay-booking= : Apply: CONFIRM-SABRE-GDS-REPLAY-CLOSURE-BOOKING-3}';

    protected $description = '[operator] QR post-cancel zero-segment replay — dry-run default; local closure only on apply';

    public function handle(SabreGdsQrUnticketedPostCancelReplayLifecycle $lifecycle): int
    {
        $apply = $this->option('apply-local-closure') === true;
        $bookingId = (int) ($this->option('booking-id') ?? 0);
        if ($bookingId <= 0) {
            $this->components->error('--booking-id is required.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_qr_unticketed_post_cancel_replay_command', 30);
        if (! $lock->get()) {
            $this->components->error('Command duplicate protection lock active.');

            return self::FAILURE;
        }

        try {
            $result = $lifecycle->run([
                'apply_local_closure' => $apply,
                'booking_id' => $bookingId,
                'supplier_booking_id' => (int) ($this->option('supplier-booking-id') ?? 0),
                'prior_cancellation_lifecycle_run_id' => trim((string) ($this->option('prior-cancellation-lifecycle-run-id') ?? '')),
                'post_cancel_retrieve_lifecycle_run_id' => trim((string) ($this->option('post-cancel-retrieve-lifecycle-run-id') ?? '')),
                'retrieve_attempt_id' => (int) ($this->option('retrieve-attempt-id') ?? 0),
                'lifecycle_run_id' => trim((string) ($this->option('lifecycle-run-id') ?? '')),
                'confirm_local_closure' => trim((string) ($this->option('confirm-local-closure') ?? '')),
                'confirm_replay_booking' => trim((string) ($this->option('confirm-replay-booking') ?? '')),
            ]);

            $this->printResult($result, $apply);

            return isset($result['error']) ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function printResult(array $result, bool $apply): void
    {
        $this->line('lifecycle_run_id='.($result['lifecycle_run_id'] ?? ''));
        $this->line('command_mode='.SabreGdsQrUnticketedPostCancelReplayLifecycle::MODE);
        $this->line('replay_mode='.($apply ? 'apply_local_closure' : 'dry_run'));
        $this->line('artifact_path='.($result['artifact_path'] ?? ''));
        $this->line('retrieve_outcome_state='.($result['retrieve_outcome_state'] ?? ''));
        $this->line('post_cancel_retrieve_confirmed='.(($result['post_cancel_retrieve_confirmed'] ?? false) ? 'true' : 'false'));
        $this->line('cancellation_closure_verified='.(($result['cancellation_closure_verified'] ?? false) ? 'true' : 'false'));
        $this->line('manual_reconciliation_required='.(($result['manual_reconciliation_required'] ?? false) ? 'true' : 'false'));
        $this->line('active_segment_count='.($result['active_segment_count'] ?? 0));
        $this->line('supplier_call_count=0');
        $this->line('cancellation_attempted=false');
        $this->line('retrieve_attempted=false');

        if (isset($result['error'])) {
            $this->components->error('Replay blocked: '.(string) $result['error']);
        }
    }
}
