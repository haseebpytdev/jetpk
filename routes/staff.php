<?php

use App\Http\Controllers\Admin\BookingManagementController as AdminBookingManagementController;
use App\Http\Controllers\BookingDocumentController;
use App\Http\Controllers\BookingTicketingController;
use App\Http\Controllers\Staff\AccountingLedgerController;
use App\Http\Controllers\Staff\AccountingReconciliationController;
use App\Http\Controllers\Staff\BookingCancellationController;
use App\Http\Controllers\Staff\BookingController;
use App\Http\Controllers\Staff\BookingPaymentController;
use App\Http\Controllers\Staff\BookingRefundController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Controllers\Staff\FinanceStatementController;
use App\Http\Controllers\Staff\LedgerController;
use App\Http\Controllers\Staff\ReportsController;
use App\Http\Controllers\Staff\SupportTicketController;
use App\Support\Staff\StaffPermission;
use App\Support\Ui\UiVersionResolver;
use Illuminate\Support\Facades\Route;

Route::prefix('staff')->name('staff.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [BookingController::class, 'updateStatus'])->name('bookings.status');
    Route::post('/bookings/{booking}/notes', [BookingController::class, 'storeNote'])->name('bookings.notes');
    Route::middleware([
        'staff.permission:'.StaffPermission::BookingsUpdateStatus,
        'platform.module:supplier_booking',
    ])->group(function (): void {
        Route::post('/bookings/{booking}/supplier-booking', [BookingController::class, 'createSupplierBooking'])->name('bookings.supplier-booking');
        Route::post('/bookings/{booking}/prepare-supplier-pnr-context', [AdminBookingManagementController::class, 'prepareSupplierPnrContext'])->name('bookings.prepare-supplier-pnr-context');
        Route::post('/bookings/{booking}/manual-pnr', [BookingController::class, 'markManualPnr'])->name('bookings.manual-pnr');
        Route::post('/bookings/{booking}/sync-pnr-itinerary', [BookingController::class, 'syncPnrItinerary'])->name('bookings.sync-pnr-itinerary');
    });
    Route::post('/bookings/{booking}/payments', [BookingPaymentController::class, 'store'])->name('bookings.payments.store');
    Route::patch('/bookings/payments/{bookingPayment}/verify', [BookingPaymentController::class, 'verify'])
        ->middleware('staff.permission:'.StaffPermission::PaymentsVerify)
        ->name('bookings.payments.verify');
    Route::patch('/bookings/payments/{bookingPayment}/reject', [BookingPaymentController::class, 'reject'])
        ->middleware('staff.permission:'.StaffPermission::PaymentsReject)
        ->name('bookings.payments.reject');
    Route::post('/bookings/{booking}/cancellations', [BookingCancellationController::class, 'store'])->name('bookings.cancellations.store');
    Route::patch('/bookings/cancellations/{cancellationRequest}/approve', [BookingCancellationController::class, 'approve'])
        ->middleware('staff.permission:'.StaffPermission::CancellationsApprove)
        ->name('bookings.cancellations.approve');
    Route::patch('/bookings/cancellations/{cancellationRequest}/reject', [BookingCancellationController::class, 'reject'])
        ->middleware('staff.permission:'.StaffPermission::CancellationsApprove)
        ->name('bookings.cancellations.reject');
    Route::patch('/bookings/cancellations/{cancellationRequest}/process', [BookingCancellationController::class, 'process'])
        ->middleware('staff.permission:'.StaffPermission::CancellationsProcess)
        ->name('bookings.cancellations.process');
    Route::post('/bookings/{booking}/refunds', [BookingRefundController::class, 'store'])->name('bookings.refunds.store');
    Route::patch('/bookings/refunds/{bookingRefund}/approve', [BookingRefundController::class, 'approve'])
        ->middleware('staff.permission:'.StaffPermission::RefundsApprove)
        ->name('bookings.refunds.approve');
    Route::patch('/bookings/refunds/{bookingRefund}/mark-paid', [BookingRefundController::class, 'markPaid'])
        ->middleware('staff.permission:'.StaffPermission::RefundsMarkPaid)
        ->name('bookings.refunds.mark-paid');
    Route::patch('/bookings/refunds/{bookingRefund}/reject', [BookingRefundController::class, 'reject'])
        ->middleware('staff.permission:'.StaffPermission::RefundsReject)
        ->name('bookings.refunds.reject');
    Route::post('/bookings/{booking}/issue-ticket', [BookingTicketingController::class, 'issue'])
        ->middleware([
            'staff.permission:'.StaffPermission::TicketingIssue,
            'platform.module:ticketing',
        ])
        ->name('bookings.issue-ticket');
    Route::post('/bookings/{booking}/documents/confirmation', [BookingDocumentController::class, 'bookingConfirmation'])->name('bookings.documents.confirmation');
    Route::post('/bookings/{booking}/documents/invoice', [BookingDocumentController::class, 'invoice'])->name('bookings.documents.invoice');
    Route::post('/bookings/{booking}/documents/ticket-itinerary', [BookingDocumentController::class, 'ticketItinerary'])->name('bookings.documents.ticket-itinerary');
    Route::post('/bookings/{booking}/documents/refund-note', [BookingDocumentController::class, 'refundNote'])->name('bookings.documents.refund-note');
    Route::post('/bookings/{booking}/documents/cancellation-confirmation', [BookingDocumentController::class, 'cancellationConfirmation'])->name('bookings.documents.cancellation-confirmation');
    Route::post('/bookings/payments/{bookingPayment}/documents/receipt', [BookingDocumentController::class, 'paymentReceipt'])->name('bookings.payments.documents.receipt');
    Route::get('/bookings/documents/{bookingDocument}/download', [BookingDocumentController::class, 'download'])->name('bookings.documents.download');

    Route::middleware('platform.module:finance_reports')->group(function (): void {
        Route::middleware('staff.permission:'.StaffPermission::LedgerView)->group(function (): void {
            Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
            Route::get('/ledger/{transaction}', [LedgerController::class, 'show'])->name('ledger.show');
            Route::get('/accounting/ledger', [AccountingLedgerController::class, 'index'])->name('accounting.ledger.index');
            Route::get('/accounting/ledger/{ledgerTransaction}', [AccountingLedgerController::class, 'show'])->name('accounting.ledger.show');
            Route::get('/accounting/reconciliation', [AccountingReconciliationController::class, 'index'])->name('accounting.reconciliation.index');
        });

        Route::middleware('staff.permission:'.StaffPermission::ReportsView)->group(function (): void {
            Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
            Route::get('/finance/statements', [FinanceStatementController::class, 'index'])->name('finance.statements.index');
            Route::get('/finance/statements/{agency}', [FinanceStatementController::class, 'show'])->name('finance.statements.show');
        });

        Route::get('/finance/statements/{agency}/export', [FinanceStatementController::class, 'export'])
            ->middleware('staff.permission:'.StaffPermission::ReportsExport)
            ->name('finance.statements.export');

        Route::get('/reports/export/{type}', [ReportsController::class, 'export'])
            ->middleware('staff.permission:'.StaffPermission::ReportsExport)
            ->where('type', 'sales|payments|bookings|agents|refunds|supplier_diagnostics|documents')
            ->name('reports.export');
    });

    Route::middleware('platform.module:support_system')->group(function (): void {
        Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
        Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
        Route::patch('/support/tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.tickets.status');
    });

    if (app()->environment('testing')) {
        Route::get('/_test/ui-version', static function () {
            $resolver = app(UiVersionResolver::class);
            $resolver->resolve();

            return response()->json([
                'channel' => $resolver->channel(),
                'version' => $resolver->effectiveVersion(),
                'preview' => $resolver->previewVersion(),
                'preview_active' => $resolver->isPreviewActive(),
                'resolved_view' => $resolver->resolveViewName('dashboard.staff.index'),
            ]);
        })->name('test.ui-version');
    }
});
