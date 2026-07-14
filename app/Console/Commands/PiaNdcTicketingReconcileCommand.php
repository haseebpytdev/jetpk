<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\PiaNdc\PiaNdcBookingStatusRefreshService;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminPiaNdcTicketingPresenter;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Bookings\PiaNdcPnrItinerarySyncMapper;
use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
use Illuminate\Console\Command;

class PiaNdcTicketingReconcileCommand extends Command
{
    protected $signature = 'pia:ndc-ticketing-reconcile {--booking= : Booking ID}';

    protected $description = 'PIA NDC ticketing reconciliation report (read-only, PIA-NDC-OPS1).';

    public function handle(
        PiaNdcBookingStatusRefreshService $refreshService,
        AdminPiaNdcTicketingPresenter $presenter,
        TicketingService $ticketingService,
    ): int {
        $bookingId = (int) $this->option('booking');
        if ($bookingId <= 0) {
            $this->error('Provide --booking={id}');

            return self::FAILURE;
        }

        $dryRun = true;
        $booking = Booking::query()->with(['tickets', 'ticketingAttempts', 'latestSupplierBooking'])->find($bookingId);
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

        $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];
        $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
        $ticketingMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_TICKETING] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_TICKETING]
            : [];
        $voidMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET] ?? null)
            ? $meta[PiaNdcOperationAuditRecorder::META_VOID_TICKET]
            : (is_array($meta[PiaNdcVoidLocalReconciliation::META_LAST_VOID_RESPONSE] ?? null)
                ? $meta[PiaNdcVoidLocalReconciliation::META_LAST_VOID_RESPONSE]
                : []);
        $voidSnapshot = PiaNdcVoidLocalReconciliation::diagnosticSnapshot($booking);

        $this->info('PIA NDC ticketing reconcile'.($dryRun ? ' (dry-run)' : ''));
        $this->line('Booking #'.$booking->id.' PNR: '.(trim((string) ($booking->pnr ?? '')) !== '' ? (string) $booking->pnr : '—'));
        $this->line('Local ticketing_status: '.(string) ($booking->ticketing_status ?? 'not_started'));
        $this->line('Supplier PNR present: '.(trim((string) ($booking->pnr ?? '')) !== '' ? 'yes' : 'no'));
        $this->line('Supplier retrieve available: '.($refreshService->canRefreshBooking($booking) ? 'yes' : 'no'));
        $this->line('PNR sync status: '.(string) ($syncSidecar['status'] ?? '—'));
        $this->line('Supplier ticket evidence: '.(PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta) ? 'yes' : 'no'));
        $this->line('Local ticket rows: '.$booking->tickets->count());
        $this->line('Supplier ticket numbers: '.(is_array($context['ticket_numbers'] ?? null) ? implode(', ', $context['ticket_numbers']) : '—'));
        $this->line('Last ticketing meta status: '.(string) ($ticketingMeta['status'] ?? '—'));
        $latestAttempt = $booking->ticketingAttempts->sortByDesc('created_at')->first();

        if ($latestAttempt !== null) {
            $this->line('Latest ticketing attempt: '.$latestAttempt->status.' @ '.$latestAttempt->attempted_at?->toDateTimeString());
        }

        $this->line('Void status (local): '.($voidSnapshot['is_voided'] ? 'voided' : ($voidSnapshot['requires_void_review'] ? 'requires_review' : 'active')));
        $this->line('Latest void attempt: '.(string) ($voidMeta['status'] ?? '—'));
        $this->line('Void sidecar void_status: '.(string) ($voidMeta['void_status'] ?? '—'));
        foreach ($voidSnapshot['ticket_rows'] as $row) {
            $this->line(sprintf(
                '  ticket %s status=%s voided_at=%s',
                (string) ($row['ticket_number'] ?? '—'),
                (string) ($row['status'] ?? '—'),
                (string) ($row['voided_at'] ?? '—'),
            ));
        }

        $panel = $presenter->panel($booking, $ticketingService->isBookingEligibleForTicketing($booking));
        $this->line('Admin show presenter safe: '.(($panel['show'] ?? false) ? 'yes' : 'no'));

        $proposed = [];
        if (PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta) && ($syncSidecar['status'] ?? '') !== 'synced') {
            $proposed[] = 'Run PIA status refresh / retrieve to sync PNR itinerary sidecar.';
        }
        if ($booking->tickets->isEmpty() && PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta)) {
            $proposed[] = 'Set ticketing_status=ticketing_requires_review or backfill local ticket rows from supplier context.';
        }
        if ((string) ($booking->ticketing_status ?? '') === 'ticketed' && $booking->tickets->isEmpty()) {
            $proposed[] = 'Reconcile ticketed status with missing local ticket records.';
        }
        if ($voidSnapshot['is_voided'] && strtolower((string) ($booking->ticketing_status ?? '')) !== PiaNdcVoidLocalReconciliation::TICKETING_STATUS_VOIDED) {
            $proposed[] = 'Run pia:ndc-void-reconcile --booking='.$booking->id.' --apply to align local ticket rows and ticketing_status=voided.';
        }
        if ($voidSnapshot['requires_void_review']) {
            $proposed[] = 'Review ambiguous void; do not re-issue. Confirm supplier coupon status before retry.';
        }
        if ($proposed === []) {
            $proposed[] = 'No reconciliation changes proposed.';
        }

        $this->newLine();
        $this->info('Proposed reconciliation');
        foreach ($proposed as $line) {
            $this->line('  - '.$line);
        }

        if ($dryRun) {
            $this->warn('Dry-run only — no supplier mutations performed.');
        }

        return self::SUCCESS;
    }
}
