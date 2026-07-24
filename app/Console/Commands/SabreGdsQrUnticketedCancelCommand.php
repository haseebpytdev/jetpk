<?php

namespace App\Console\Commands;

use App\Support\Sabre\Scenario\SabreGdsQrUnticketedCancelLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * QR unticketed PNR cancellation lifecycle — plan default (zero supplier calls); live send requires production confirmations.
 */
class SabreGdsQrUnticketedCancelCommand extends Command
{
    protected $signature = 'sabre:gds-qr-unticketed-cancel
                            {--booking-id= : Booking ID (production send requires 3)}
                            {--supplier-booking-id= : Expected supplier booking row id (production send: 2)}
                            {--lifecycle-run-id= : Optional lifecycle id for idempotency}
                            {--plan : Plan mode (default when --send omitted)}
                            {--send : Execute one live cancellation call}
                            {--confirm-production= : Send: APPROVE-LIVE-SABRE-GDS-UNTICKETED-CANCELLATION}
                            {--confirm-cancellation= : Send: LIVE-SABRE-GDS-CANCEL-ONE-UNTICKETED-PNR}
                            {--confirm-no-ticketing= : Send: CONFIRM-SABRE-TICKETING-DISABLED}';

    protected $description = '[operator] QR unticketed Sabre GDS cancel — plan default; one cancellation call max on send';

    public function handle(SabreGdsQrUnticketedCancelLifecycle $lifecycle): int
    {
        $send = $this->option('send') === true;
        $bookingId = (int) ($this->option('booking-id') ?? 0);
        if ($bookingId <= 0) {
            $this->components->error('--booking-id is required.');

            return self::FAILURE;
        }

        $lock = Cache::lock('sabre_gds_qr_unticketed_cancel_command', 30);
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
                'lifecycle_run_id' => trim((string) ($this->option('lifecycle-run-id') ?? '')),
                'confirm_production' => trim((string) ($this->option('confirm-production') ?? '')),
                'confirm_cancellation' => trim((string) ($this->option('confirm-cancellation') ?? '')),
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
        $this->line('command_mode='.SabreGdsQrUnticketedCancelLifecycle::MODE);
        $this->line('probe_mode='.($send ? 'send' : 'plan'));
        $this->line('artifact_path='.($result['artifact_path'] ?? ''));
        $this->line('booking_id='.($result['booking_id'] ?? ''));
        $this->line('supplier_booking_id='.($result['supplier_booking_id'] ?? ''));
        $this->line('locator_present='.(($result['locator_present'] ?? false) ? 'true' : 'false'));
        $this->line('locator_matches='.(($result['locator_matches'] ?? $result['identity_checks']['locator_matches'] ?? false) ? 'true' : 'false'));
        $this->line('locator_denylisted='.(($result['locator_denylisted'] ?? $result['identity_checks']['locator_denylisted'] ?? false) ? 'true' : 'false'));
        $this->line('unticketed='.(($result['unticketed'] ?? false) ? 'true' : 'false'));
        $this->line('ticket_number_count='.($result['ticket_number_count'] ?? 0));
        $this->line('ticketing_attempted=false');
        $this->line('airticket_attempted=false');
        $this->line('void_attempted=false');
        $this->line('refund_attempted=false');
        $this->line('post_cancel_retrieve_attempted=false');

        if (is_array($result['operation_plan'] ?? null)) {
            foreach ($result['operation_plan'] as $key => $value) {
                $this->line($key.'='.(is_bool($value) ? ($value ? 'true' : 'false') : $value));
            }
        }

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
