<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Models\SupplierBookingAttempt;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
use Illuminate\Console\Command;

class PiaNdcVoidReconcileCommand extends Command
{
    protected $signature = 'pia:ndc-void-reconcile
                            {--booking= : Booking ID}
                            {--dry-run : Inspect only; no local mutations (default)}
                            {--apply : Apply safe local void reconciliation repairs}';

    protected $description = 'PIA NDC void state reconciliation (read-only by default, PIA-NDC-OPS1.2).';

    public function handle(): int
    {
        $bookingId = (int) $this->option('booking');
        if ($bookingId <= 0) {
            $this->error('Provide --booking={id}');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $dryRun = ! $apply || (bool) $this->option('dry-run');

        $booking = Booking::query()->with('tickets')->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found: '.$bookingId);

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        if ($provider !== SupplierProvider::PiaNdc->value) {
            $this->error('Booking is not PIA NDC.');

            return self::FAILURE;
        }

        $snapshot = PiaNdcVoidLocalReconciliation::diagnosticSnapshot($booking);
        $latestVoidAttempt = SupplierBookingAttempt::query()
            ->where('booking_id', $booking->id)
            ->where('action', PiaNdcOperationAuditRecorder::ACTION_VOID_TICKET)
            ->latest('id')
            ->first();

        $this->info('PIA NDC void reconcile'.($dryRun ? ' (dry-run)' : ' (apply)'));
        $this->line('Booking #'.$booking->id);
        $this->line('Local ticketing_status: '.$snapshot['local_ticketing_status']);
        $this->line('Supplier booking status: '.$snapshot['supplier_booking_status']);
        $this->line('Context void_status: '.$snapshot['context_void_status']);
        $this->line('Context ticketing_status: '.$snapshot['context_ticketing_status']);
        $this->line('Supplier blocking tickets: '.($snapshot['has_blocking_ticket_numbers'] ? 'yes' : 'no'));
        $this->line('Supplier ticket numbers: '.(count($snapshot['supplier_ticket_numbers']) > 0 ? implode(', ', $snapshot['supplier_ticket_numbers']) : '—'));
        $this->line('Ticket active state: '.$snapshot['ticket_active_state']);
        $this->line('Is voided (local): '.($snapshot['is_voided'] ? 'yes' : 'no'));
        $this->line('Requires void review: '.($snapshot['requires_void_review'] ? 'yes' : 'no'));

        if ($latestVoidAttempt !== null) {
            $this->line('Latest void attempt: '.$latestVoidAttempt->status.' @ '.$latestVoidAttempt->created_at?->toDateTimeString());
        } else {
            $this->line('Latest void attempt: —');
        }

        $this->newLine();
        $this->info('Local ticket rows');
        if ($snapshot['ticket_rows'] === []) {
            $this->line('  (none)');
        } else {
            foreach ($snapshot['ticket_rows'] as $row) {
                $this->line(sprintf(
                    '  %s status=%s void_status=%s voided_at=%s',
                    (string) ($row['ticket_number'] ?? '—'),
                    (string) ($row['status'] ?? '—'),
                    (string) ($row['void_status'] ?? '—'),
                    (string) ($row['voided_at'] ?? '—'),
                ));
            }
        }

        $proposal = PiaNdcVoidLocalReconciliation::voidRepairProposal($booking);
        if ($proposal['should_repair']) {
            $this->warn('Proposed action: apply local void reconciliation ('.$proposal['reason'].').');
        } elseif ($snapshot['is_voided']) {
            $this->line('Proposed action: none (void state already reconciled).');
        } else {
            $this->line('Proposed action: '.$proposal['reason']);
        }

        if ($apply && ! $dryRun) {
            $repair = PiaNdcVoidLocalReconciliation::repairLocalVoidState($booking);
            if ($repair['applied']) {
                $this->info('Applied local void reconciliation repair.');
            } else {
                $this->warn('No repair applied: '.$repair['reason']);
            }
        } else {
            $this->warn('Dry-run only — no supplier mutations; use --apply to repair local void rows when safe.');
        }

        return self::SUCCESS;
    }
}
