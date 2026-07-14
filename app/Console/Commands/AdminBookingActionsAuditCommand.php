<?php

namespace App\Console\Commands;

use App\Enums\SupplierProvider;
use App\Models\Booking;
use App\Services\Suppliers\TicketingService;
use App\Support\Bookings\AdminBookingSupplierActionGate;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Bookings\AdminPiaNdcTicketingPresenter;
use App\Support\Bookings\PiaNdcOperationAuditRecorder;
use App\Support\Bookings\PiaNdcPnrItinerarySyncMapper;
use App\Support\Bookings\PiaNdcVoidLocalReconciliation;
use App\Support\Bookings\TicketingReadinessPresenter;
use Illuminate\Console\Command;

class AdminBookingActionsAuditCommand extends Command
{
    protected $signature = 'admin:booking-actions-audit {--booking= : Booking ID}';

    protected $description = 'Diagnostic: admin supplier action matrix and button states for a booking (PIA-NDC-OPS1).';

    public function handle(
        AdminBookingSupplierActionGate $actionGate,
        AdminBookingSupplierActions $supplierActions,
        AdminPiaNdcTicketingPresenter $piaNdcTicketingPresenter,
        TicketingService $ticketingService,
    ): int {
        $bookingId = (int) $this->option('booking');
        if ($bookingId <= 0) {
            $this->error('Provide --booking={id}');

            return self::FAILURE;
        }

        $booking = Booking::query()->with(['contact', 'tickets', 'latestSupplierBooking', 'ticketingAttempts'])->find($bookingId);
        if ($booking === null) {
            $this->error('Booking not found: '.$bookingId);

            return self::FAILURE;
        }

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $provider = strtolower(trim((string) ($meta['supplier_provider'] ?? $booking->supplier ?? '')));
        $supplierEligible = true;
        $ticketingEligible = $ticketingService->isBookingEligibleForTicketing($booking);
        $actions = $supplierActions->build($booking, $supplierEligible, $ticketingEligible);
        $readiness = TicketingReadinessPresenter::forBooking($booking);

        $this->line('Booking #'.$booking->id.' ('.($booking->booking_reference ?: 'no ref').')');
        $this->line('Supplier: '.($provider !== '' ? $provider : 'unknown'));
        $this->line('Payment status: '.(string) ($booking->payment_status ?? 'unpaid'));
        $this->line('PNR: '.(trim((string) ($booking->pnr ?? '')) !== '' ? (string) $booking->pnr : '—'));
        $this->line('Ticketing status: '.(string) ($booking->ticketing_status ?? 'not_started'));
        $this->line('Ticketing eligible (strict): '.($ticketingEligible ? 'yes' : 'no'));
        $this->line('Readiness overall: '.$readiness['overall_status'].' — '.$readiness['overall_label']);
        $this->newLine();

        if ($provider === SupplierProvider::PiaNdc->value) {
            $manual = $actionGate->piaNdcManualTicketing($booking, $ticketingEligible);
            $panel = $piaNdcTicketingPresenter->panel($booking, $ticketingEligible);
            $matrix = $actionGate->actionMatrix($booking, $ticketingEligible, $supplierEligible);

            $this->info('PIA NDC manual ticketing');
            $this->line('  Admin override allowed: '.(($manual['admin_override_allowed'] ?? false) ? 'yes' : 'no'));
            $this->line('  Itinerary synced: '.(($manual['itinerary_synced'] ?? false) ? 'yes' : 'no'));
            $this->line('  Selected branded fare present: '.(($manual['selected_fare_present'] ?? false) ? 'yes' : 'no'));
            $this->line('  Normalized supplier phone: '.($manual['normalized_supplier_phone'] ?? '—'));
            if (($manual['warnings'] ?? []) !== []) {
                foreach ($manual['warnings'] as $warning) {
                    $this->warn('  '.$warning);
                }
            }
            if (($manual['hard_block_reason'] ?? null) !== null) {
                $this->error('  Hard block: '.$manual['hard_block_reason']);
            }

            $this->newLine();
            $this->info('Action matrix');
            foreach ($matrix as $action => $row) {
                $state = ($row['enabled'] ?? false) ? 'ENABLED' : 'DISABLED';
                $override = ($row['admin_override_allowed'] ?? false) ? ' (admin override)' : '';
                $this->line(sprintf('  %-28s %s%s', $action.':', $state.$override, ($row['reason'] ?? '') !== '' ? ' — '.$row['reason'] : ''));
            }

            $this->newLine();
            $this->info('Presenter flags');
            $this->line('  can_preview: '.(($panel['can_preview'] ?? false) ? 'yes' : 'no'));
            $this->line('  can_issue: '.(($panel['can_issue'] ?? false) ? 'yes' : 'no'));
            $this->line('  can_void: '.(($panel['can_void'] ?? false) ? 'yes' : 'no'));
            $this->line('  can_resend_eticket: '.(($panel['can_resend_eticket'] ?? false) ? 'yes' : 'no'));
            $this->line('  void_status: '.(string) ($panel['void_status'] ?? '—'));
            $this->line('  latest_void_attempt: '.(string) ($panel['latest_void_attempt_status'] ?? '—'));
            $this->line('  void_supplier_summary: '.(string) ($panel['latest_void_supplier_summary'] ?? '—'));
        }

        $this->newLine();
        $this->info('Supplier actions summary');
        $this->line('  can_issue_ticket_action: '.(($actions['can_issue_ticket_action'] ?? false) ? 'yes' : 'no'));
        $this->line('  can_issue_ticket_live: '.(($actions['can_issue_ticket_live'] ?? false) ? 'yes' : 'no'));
        $this->line('  can_retry_ticketing: '.(($actions['can_retry_ticketing'] ?? false) ? 'yes' : 'no'));
        if (trim((string) ($actions['issue_ticket_disabled_reason'] ?? '')) !== '') {
            $this->line('  issue_ticket_disabled_reason: '.$actions['issue_ticket_disabled_reason']);
        }

        if ($provider === SupplierProvider::PiaNdc->value) {
            $meta = is_array($booking->meta) ? $booking->meta : [];
            $syncSidecar = is_array($meta['pnr_itinerary_sync'] ?? null) ? $meta['pnr_itinerary_sync'] : [];
            $ticketingMeta = is_array($meta[PiaNdcOperationAuditRecorder::META_TICKETING] ?? null)
                ? $meta[PiaNdcOperationAuditRecorder::META_TICKETING]
                : [];
            $latestTicketAttempt = $booking->ticketingAttempts->sortByDesc('created_at')->first();
            $context = is_array($meta['pia_ndc_context'] ?? null) ? $meta['pia_ndc_context'] : [];

            $voidSnapshot = PiaNdcVoidLocalReconciliation::diagnosticSnapshot($booking);

            $this->newLine();
            $this->info('Void diagnostics');
            $this->line('  ticket_active_state: '.$voidSnapshot['ticket_active_state']);
            $this->line('  local_voided: '.($voidSnapshot['is_voided'] ? 'yes' : 'no'));
            $this->line('  requires_void_review: '.($voidSnapshot['requires_void_review'] ? 'yes' : 'no'));
            $this->line('  context_void_status: '.$voidSnapshot['context_void_status']);
            $this->line('  supplier_blocking_tickets: '.($voidSnapshot['has_blocking_ticket_numbers'] ? 'yes' : 'no'));

            $this->newLine();
            $this->info('Ticketing diagnostics');
            $this->line('  Last ticketing action: '.(string) ($ticketingMeta['operation'] ?? ($latestTicketAttempt?->status ?? '—')));
            $this->line('  Last ticketing result: '.(string) ($ticketingMeta['status'] ?? $latestTicketAttempt?->status ?? '—'));
            $this->line('  Local ticket rows: '.$booking->tickets->count());
            $this->line('  Supplier ticket numbers: '.(is_array($context['ticket_numbers'] ?? null) ? implode(', ', $context['ticket_numbers']) : '—'));
            $this->line('  PNR sync status: '.(string) ($syncSidecar['status'] ?? '—'));
            $presenterSafe = true;
            try {
                $piaNdcTicketingPresenter->panel($booking, $ticketingEligible);
            } catch (\Throwable $exception) {
                $presenterSafe = false;
                $this->error('  Admin show presenter unsafe: '.$exception->getMessage());
            }
            if ($presenterSafe) {
                $this->line('  Admin show presenter safe: yes');
            }
            $this->line('  Suggested next action: '.($booking->tickets->isEmpty() && PiaNdcPnrItinerarySyncMapper::piaNdcSupplierTicketingEvidence($meta)
                ? 'Reconcile supplier ticket evidence with local ticket rows.'
                : (($syncSidecar['status'] ?? '') !== 'synced'
                    ? 'Run PIA status refresh / retrieve for PNR itinerary sync.'
                    : 'Review ticketing panel actions.')));
        }

        return self::SUCCESS;
    }
}
