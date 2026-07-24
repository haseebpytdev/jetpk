<?php

namespace App\Console\Commands;

use App\Support\Sabre\Scenario\SabreGdsQrUnticketedPostCancelRetrieveLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * QR unticketed post-cancel retrieve lifecycle — plan default (zero supplier calls); one retrieve max on send.
 */
class SabreGdsQrUnticketedPostCancelRetrieveCommand extends Command
{
    protected $signature = 'sabre:gds-qr-unticketed-post-cancel-retrieve
                            {--booking-id= : Booking ID (production send requires 3)}
                            {--supplier-booking-id= : Expected supplier booking row id (production send: 2)}
                            {--prior-cancellation-lifecycle-run-id= : Phase 14 cancellation lifecycle_run_id}
                            {--lifecycle-run-id= : Optional Phase 15 lifecycle id for idempotency}
                            {--plan : Plan mode (default when --send omitted)}
                            {--send : Execute one live post-cancel retrieve call}
                            {--confirm-production= : Send: APPROVE-LIVE-SABRE-GDS-POST-CANCEL-RETRIEVE}
                            {--confirm-retrieve= : Send: LIVE-SABRE-GDS-RETRIEVE-ONE-CANCELLED-PNR}
                            {--confirm-no-ticketing= : Send: CONFIRM-SABRE-TICKETING-DISABLED}';

    protected $description = '[operator] QR unticketed Sabre GDS post-cancel retrieve — plan default; one retrieve call max on send';

    public function handle(SabreGdsQrUnticketedPostCancelRetrieveLifecycle $lifecycle): int
    {
        $send = $this->option('send') === true;
        $bookingId = (int) ($this->option('booking-id') ?? 0);
        if ($bookingId <= 0) {
            $this->components->error('--booking-id is required.');

            return self::FAILURE;
        }

        $priorCancellationLifecycleRunId = trim((string) ($this->option('prior-cancellation-lifecycle-run-id') ?? ''));
        if ($priorCancellationLifecycleRunId === '') {
            $this->components->error('--prior-cancellation-lifecycle-run-id is required.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_qr_unticketed_post_cancel_retrieve_command', 30);
        if (! $lock->get()) {
            $this->components->error('Command duplicate protection lock active.');

            return self::FAILURE;
        }

        try {
            $supplierBookingIdOption = $this->option('supplier-booking-id');
            $result = $lifecycle->run([
                'send' => $send,
                'booking_id' => $bookingId,
                'supplier_booking_id' => is_numeric($supplierBookingIdOption) ? (int) $supplierBookingIdOption : null,
                'prior_cancellation_lifecycle_run_id' => $priorCancellationLifecycleRunId,
                'lifecycle_run_id' => trim((string) ($this->option('lifecycle-run-id') ?? '')),
                'confirm_production' => trim((string) ($this->option('confirm-production') ?? '')),
                'confirm_retrieve' => trim((string) ($this->option('confirm-retrieve') ?? '')),
                'confirm_no_ticketing' => trim((string) ($this->option('confirm-no-ticketing') ?? '')),
            ]);

            $this->printResult($result, $send);

            return isset($result['error']) ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function printResult(array $result, bool $send): void
    {
        $this->line('lifecycle_run_id='.($result['lifecycle_run_id'] ?? ''));
        $this->line('command_mode='.SabreGdsQrUnticketedPostCancelRetrieveLifecycle::MODE);
        $this->line('probe_mode='.($send ? 'send' : 'plan'));
        $this->line('artifact_path='.($result['artifact_path'] ?? ''));
        $this->line('booking_id='.($result['booking_id'] ?? ''));
        $this->line('supplier_booking_id='.($result['supplier_booking_id'] ?? ''));
        $this->line('prior_cancellation_lifecycle_run_id='.($result['prior_cancellation_lifecycle_run_id'] ?? ''));
        $this->line('locator_present='.(($result['locator_present'] ?? false) ? 'true' : 'false'));
        $this->line('locator_matches='.(($result['locator_matches'] ?? $result['identity_checks']['locator_matches'] ?? false) ? 'true' : 'false'));
        $this->line('locator_denylisted='.(($result['locator_denylisted'] ?? $result['identity_checks']['locator_denylisted'] ?? false) ? 'true' : 'false'));
        $this->line('prior_cancellation_confirmed='.(($result['prior_cancellation_confirmed'] ?? false) ? 'true' : 'false'));
        $this->line('prior_cancellation_ambiguous='.(($result['prior_cancellation_ambiguous'] ?? false) ? 'true' : 'false'));
        $this->line('retrieve_planned='.(($result['retrieve_planned'] ?? false) ? 'true' : 'false'));
        $this->line('maximum_retrieve_calls='.($result['maximum_retrieve_calls'] ?? SabreGdsQrUnticketedPostCancelRetrieveLifecycle::MAX_RETRIEVE_CALLS));
        $this->line('automatic_retrieve_retry=false');
        $this->line('cancellation_planned=false');
        $this->line('pnr_create_planned=false');
        $this->line('ticketing_planned=false');
        $this->line('airticket_planned=false');
        $this->line('void_planned=false');
        $this->line('refund_planned=false');
        $this->line('cancellation_attempted=false');
        $this->line('pnr_create_attempted=false');
        $this->line('ticketing_attempted=false');
        $this->line('airticket_attempted=false');
        $this->line('void_attempted=false');
        $this->line('refund_attempted=false');

        if (isset($result['error'])) {
            $this->components->error('Lifecycle blocked: '.(string) $result['error']);
            if (is_array($result['gate']['reasons'] ?? null)) {
                foreach ($result['gate']['reasons'] as $reason) {
                    $this->line('gate_reason='.$reason);
                }
            }
        }
    }
}
