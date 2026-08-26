<?php

namespace App\Support\Dashboard;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\Bookings\AdminBookingSupplierActions;
use App\Support\Staff\StaffPermission;
use Illuminate\Support\Facades\Gate;

/**
 * Server-derived booking lifecycle capabilities for the Next Dashboard detail view.
 * Browser must not infer destructive actions from local status strings alone.
 */
final class BookingOperationalCapabilitiesPresenter
{
    public function __construct(
        protected ?BackOfficeCapabilitiesPresenter $backOffice = null,
        protected ?AdminBookingSupplierActions $supplierActions = null,
    ) {
        $this->backOffice ??= app(BackOfficeCapabilitiesPresenter::class);
        $this->supplierActions ??= app(AdminBookingSupplierActions::class);
    }

    /**
     * @return array{
     *     can_update_status: bool,
     *     can_prepare_pnr_context: bool,
     *     can_generate_pnr: bool,
     *     can_retry_pnr: bool,
     *     can_sync_pnr: bool,
     *     can_record_payment: bool,
     *     can_admin_mark_paid: bool,
     *     can_issue_ticket: bool,
     *     can_void_ticket: bool,
     *     can_request_cancellation: bool,
     *     can_cancel_supplier_booking: bool,
     *     can_request_refund: bool,
     *     can_generate_documents: bool,
     *     can_download_documents: bool,
     *     can_generate_receipt: bool,
     *     can_export_audit: bool,
     *     latest_payment_id: string|null,
     *     reasons: array<string, string|null>,
     *     sabre_void_support: string,
     *     allowed_status_values: list<string>
     * }
     */
    public function present(User $user, Booking $booking): array
    {
        $status = $booking->status instanceof BookingStatus
            ? $booking->status
            : BookingStatus::tryFrom((string) $booking->status);
        $isCancelled = $status === BookingStatus::Cancelled;
        $isFailed = $status === BookingStatus::Failed;
        $paymentStatus = (string) ($booking->payment_status ?? 'unpaid');
        $pnr = trim((string) ($booking->pnr ?? ''));
        $hasPnr = $pnr !== '';
        $supplierStatus = (string) ($booking->supplier_booking_status ?? 'not_started');
        $amountPaid = (float) ($booking->amount_paid ?? 0);
        $isAdmin = $user->isPlatformAdmin();

        $canView = Gate::forUser($user)->allows('view', $booking);
        $ticketing = $this->backOffice->presentBookingTicketingCapabilities($user, $booking);

        $canUpdateStatus = $canView && Gate::forUser($user)->allows('changeStatus', $booking);

        $canGeneratePnr = $canView
            && ! $isCancelled
            && ! $isFailed
            && ! $hasPnr
            && Gate::forUser($user)->allows('update', $booking);

        $canRetryPnr = $canView
            && ! $isCancelled
            && ! $isFailed
            && ! $hasPnr
            && in_array($supplierStatus, ['failed', 'manual_review', 'pending', 'in_progress', 'not_started'], true)
            && Gate::forUser($user)->allows('update', $booking);

        $canPreparePnrContext = $canView
            && Gate::forUser($user)->allows('createSupplierBooking', $booking)
            && $this->supplierActions->assertPrepareSupplierContextPostAllowed($booking) === null;

        $canSyncPnr = $canView
            && $hasPnr
            && Gate::forUser($user)->allows('createSupplierBooking', $booking)
            && $this->supplierActions->assertSyncPnrItineraryPostAllowed($booking) === null;

        $canRecordPayment = $canView
            && ! $isCancelled
            && ! $isFailed
            && in_array($paymentStatus, ['unpaid', 'partial', 'pending'], true);

        $outstanding = max(0.0, (float) ($booking->fareBreakdown?->total ?? $booking->total_amount ?? 0) - $amountPaid);
        $canAdminMarkPaid = $isAdmin
            && $canRecordPayment
            && $outstanding > 0;

        $canRequestCancellation = $canView && ! $isCancelled && ! $isFailed;

        $ticketCount = $booking->relationLoaded('tickets')
            ? $booking->tickets->count()
            : (int) $booking->tickets()->count();
        $paymentCaptured = $amountPaid > 0 || in_array($paymentStatus, ['paid', 'partial', 'captured'], true);
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $supplierProvider = strtolower(trim((string) ($booking->supplier ?? $meta['supplier_provider'] ?? '')));
        $supplierConnectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        $connection = $supplierConnectionId > 0
            ? \App\Models\SupplierConnection::query()->find($supplierConnectionId)
            : null;
        $connectionEnvironment = $connection?->environment?->value ?? null;
        $pendingCancellationCount = $booking->relationLoaded('cancellationRequests')
            ? $booking->cancellationRequests->whereIn('status', ['requested', 'approved'])->count()
            : (int) $booking->cancellationRequests()
                ->whereIn('status', ['requested', 'approved'])
                ->count();
        $cancellationStatus = strtolower((string) ($booking->cancellation_status ?? ''));
        $cancellationAmbiguous = in_array($cancellationStatus, ['manual_review', 'pending_reconciliation'], true)
            || $pendingCancellationCount > 1;
        $refundPending = in_array(strtolower((string) ($booking->refund_status ?? '')), ['pending', 'requested', 'processing'], true);
        $voidPending = false;

        $canCancelSupplier = $isAdmin
            && $canRequestCancellation
            && $hasPnr
            && $supplierProvider === 'sabre'
            && $ticketCount === 0
            && ! $paymentCaptured
            && ! $refundPending
            && ! $voidPending
            && ! $cancellationAmbiguous
            && $supplierConnectionId > 0
            && $connection !== null
            && in_array($connectionEnvironment, ['sandbox', 'demo', 'live'], true);

        $canRequestRefund = $canView
            && ! $isFailed
            && $amountPaid > 0
            && in_array($paymentStatus, ['paid', 'partial'], true);

        // Never enable production Sabre void from the Dashboard alone.
        $voidLiveEnabled = (bool) config('suppliers.sabre.void_live_call_enabled', false);
        $hasTickets = $booking->relationLoaded('tickets')
            ? $booking->tickets->isNotEmpty()
            : $booking->tickets()->exists();
        $sabreVoidServiceExists = class_exists(\App\Services\Suppliers\Sabre\Ticketing\SabreGdsVoidTicketService::class);
        $canVoidCapability = $isAdmin && $hasTickets && $sabreVoidServiceExists;
        $canVoidTicket = $canVoidCapability && $voidLiveEnabled;

        if (! $sabreVoidServiceExists || ! $isAdmin) {
            $sabreVoidSupport = 'BLOCKED';
        } elseif ($voidLiveEnabled) {
            $sabreVoidSupport = 'IMPLEMENTED_PRODUCTION_GATED';
        } else {
            // Adapter/service exists; production live void gate is off — UI must stay disabled.
            $sabreVoidSupport = 'SUPPORTED_EXISTING_LIVE_GATE_DISABLED';
        }

        $canGenerateDocuments = Gate::forUser($user)->allows('create', [BookingDocument::class, $booking]);
        $canDownloadDocuments = $isAdmin
            || ($user->isStaff()
                && $user->current_agency_id === $booking->agency_id
                && $user->hasStaffPermission(StaffPermission::DocumentsDownload))
            || $canGenerateDocuments;

        $latestPayment = $booking->relationLoaded('payments')
            ? $booking->payments->sortByDesc('id')->first()
            : $booking->payments()->latest('id')->first();
        $latestPaymentId = $latestPayment !== null ? (string) $latestPayment->id : null;
        $canGenerateReceipt = $canGenerateDocuments && $latestPaymentId !== null;
        $canExportAudit = $canView;

        $prepareBlock = $this->supplierActions->assertPrepareSupplierContextPostAllowed($booking);
        $syncBlock = $this->supplierActions->assertSyncPnrItineraryPostAllowed($booking);

        $reasons = [
            'can_update_status' => $canUpdateStatus ? null : 'not_permitted',
            'can_prepare_pnr_context' => $canPreparePnrContext ? null : ($prepareBlock ?? 'not_eligible_or_not_permitted'),
            'can_generate_pnr' => $canGeneratePnr ? null : ($hasPnr ? 'pnr_already_present' : ($isCancelled || $isFailed ? 'booking_not_actionable' : 'not_permitted')),
            'can_retry_pnr' => $canRetryPnr ? null : ($hasPnr ? 'pnr_already_present' : 'not_eligible_or_not_permitted'),
            'can_sync_pnr' => $canSyncPnr ? null : ($syncBlock ?? ($hasPnr ? 'not_permitted' : 'pnr_missing')),
            'can_record_payment' => $canRecordPayment ? null : 'payment_not_open',
            'can_admin_mark_paid' => $canAdminMarkPaid ? null : ($isAdmin ? 'nothing_outstanding' : 'admin_only'),
            'can_issue_ticket' => ($ticketing['can_issue_ticket'] ?? false) ? null : ($ticketing['denial_reason'] ?? 'not_eligible'),
            'can_void_ticket' => $canVoidTicket
                ? null
                : (! $hasTickets
                    ? 'no_tickets'
                    : (! $isAdmin
                        ? 'admin_only'
                        : (! $sabreVoidServiceExists
                            ? 'Void is not supported by the current Sabre servicing adapter.'
                            : 'Sabre ticket void is available but live void execution is currently disabled by the production safety gate.'))),
            'can_request_cancellation' => $canRequestCancellation ? null : 'booking_not_cancellable',
            'can_cancel_supplier_booking' => $this->cancelSupplierDenialReason(
                $canCancelSupplier,
                $isAdmin,
                $hasPnr,
                $supplierProvider,
                $ticketCount,
                $paymentCaptured,
                $cancellationAmbiguous,
                $supplierConnectionId,
            ),
            'can_request_refund' => $canRequestRefund ? null : 'refund_not_eligible',
            'can_generate_documents' => $canGenerateDocuments ? null : 'not_permitted',
            'can_download_documents' => $canDownloadDocuments ? null : 'not_permitted',
            'can_generate_receipt' => $canGenerateReceipt ? null : ($canGenerateDocuments ? 'no_payment' : 'not_permitted'),
            'can_export_audit' => $canExportAudit ? null : 'not_permitted',
        ];

        $safeReference = $this->maskBookingReference((string) ($booking->booking_reference ?? $booking->reference ?? $booking->id));
        $isSandboxEnv = in_array($connectionEnvironment, ['sandbox', 'demo'], true);

        return [
            'can_update_status' => $canUpdateStatus,
            'can_prepare_pnr_context' => $canPreparePnrContext,
            'can_generate_pnr' => $canGeneratePnr,
            'can_retry_pnr' => $canRetryPnr,
            'can_sync_pnr' => $canSyncPnr,
            'can_record_payment' => $canRecordPayment,
            'can_admin_mark_paid' => $canAdminMarkPaid,
            'can_issue_ticket' => (bool) ($ticketing['can_issue_ticket'] ?? false),
            'can_void_ticket' => $canVoidTicket,
            'can_request_cancellation' => $canRequestCancellation,
            'can_cancel_supplier_booking' => $canCancelSupplier,
            'can_request_refund' => $canRequestRefund,
            'can_generate_documents' => $canGenerateDocuments,
            'can_download_documents' => $canDownloadDocuments,
            'can_generate_receipt' => $canGenerateReceipt,
            'can_export_audit' => $canExportAudit,
            'latest_payment_id' => $latestPaymentId,
            'reasons' => $reasons,
            'sabre_void_support' => $sabreVoidSupport,
            'allowed_status_values' => array_map(
                static fn (BookingStatus $case): string => $case->value,
                BookingStatus::cases(),
            ),
            'cancel_pnr_context' => [
                'booking_reference_safe' => $safeReference,
                'supplier' => $supplierProvider !== '' ? ucfirst($supplierProvider) : 'Unknown',
                'environment' => $connectionEnvironment ?? 'unknown',
                'environment_is_sandbox' => $isSandboxEnv,
                'environment_label' => $isSandboxEnv ? 'TEST / SANDBOX' : ($connectionEnvironment === 'live' ? 'Live' : 'Unknown'),
                'payment_label' => $paymentCaptured ? 'Payment captured' : 'Unpaid',
                'ticket_label' => $ticketCount > 0 ? 'Ticket issued' : 'Not issued',
                'connection_alias_safe' => $connection?->name,
            ],
        ];
    }

    private function cancelSupplierDenialReason(
        bool $canCancelSupplier,
        bool $isAdmin,
        bool $hasPnr,
        string $supplierProvider,
        int $ticketCount,
        bool $paymentCaptured,
        bool $cancellationAmbiguous,
        int $supplierConnectionId,
    ): ?string {
        if ($canCancelSupplier) {
            return null;
        }
        if (! $isAdmin) {
            return 'admin_only';
        }
        if (! $hasPnr) {
            return 'pnr_missing';
        }
        if ($supplierProvider !== 'sabre') {
            return 'supplier_not_sabre';
        }
        if ($ticketCount > 0) {
            return 'tickets_present';
        }
        if ($paymentCaptured) {
            return 'payment_captured';
        }
        if ($cancellationAmbiguous) {
            return 'cancellation_ambiguous';
        }
        if ($supplierConnectionId <= 0) {
            return 'supplier_connection_missing';
        }

        return 'admin_supplier_cancel_not_eligible';
    }

    private function maskBookingReference(string $reference): string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return 'N/A';
        }
        if (strlen($reference) <= 4) {
            return str_repeat('*', strlen($reference));
        }

        return substr($reference, 0, 2).str_repeat('*', max(2, strlen($reference) - 4)).substr($reference, -2);
    }
}
