<?php

use App\Http\Controllers\Agent\AccountingLedgerController;
use App\Http\Controllers\Agent\AgentAgencyController;
use App\Http\Controllers\Agent\AgentBookingController;
use App\Http\Controllers\Agent\AgentCommissionController;
use App\Http\Controllers\Agent\AgentDepositController;
use App\Http\Controllers\Agent\AgentLedgerController;
use App\Http\Controllers\Agent\AgentReportsController;
use App\Http\Controllers\Agent\AgentStaffAgencyRoleController;
use App\Http\Controllers\Agent\AgentStaffController;
use App\Http\Controllers\Agent\AgentStaffPermissionController;
use App\Http\Controllers\Agent\AgentWalletController;
use App\Http\Controllers\Agent\BookingCancellationController;
use App\Http\Controllers\Agent\BookingPaymentProofController;
use App\Http\Controllers\Agent\DashboardController;
use App\Http\Controllers\Agent\FinanceStatementController;
use App\Http\Controllers\Agent\SavedTravelerController;
use App\Http\Controllers\Agent\SupportTicketController;
use App\Support\Agents\AgentPermission;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->name('agent.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('agent.permission:'.AgentPermission::AgencyView)->group(function (): void {
        Route::get('/agency', [AgentAgencyController::class, 'show'])->name('agency.show');
    });

    Route::middleware('agent.permission:'.AgentPermission::AgencyEdit)->group(function (): void {
        Route::get('/agency/edit', [AgentAgencyController::class, 'edit'])->name('agency.edit');
        Route::patch('/agency', [AgentAgencyController::class, 'update'])->name('agency.update');
    });

    Route::middleware(['agent.permission:'.AgentPermission::StaffManage, 'platform.module:agent_staff'])->group(function (): void {
        Route::get('/staff', [AgentStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [AgentStaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [AgentStaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [AgentStaffController::class, 'edit'])->name('staff.edit');
        Route::patch('/staff/{staff}', [AgentStaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{staff}/agency-role', [AgentStaffAgencyRoleController::class, 'update'])
            ->name('staff.agency-role.update');
        Route::patch('/staff/{staff}/permissions', [AgentStaffPermissionController::class, 'update'])
            ->name('staff.permissions.update');
        Route::post('/staff/{staff}/permissions/apply-template', [AgentStaffPermissionController::class, 'applyTemplate'])
            ->name('staff.permissions.apply-template');
        Route::delete('/staff/{staff}', [AgentStaffController::class, 'destroy'])->name('staff.destroy');
    });

    Route::middleware('agent.permission:'.AgentPermission::BookingsCreate)->group(function (): void {
        Route::get('/bookings/create', [AgentBookingController::class, 'create'])->name('bookings.create');
        Route::get('/bookings/exit-mode', [AgentBookingController::class, 'exitBookingMode'])->name('bookings.exit-mode');
        Route::post('/bookings', [AgentBookingController::class, 'store'])->name('bookings.store');
    });

    Route::middleware('agent.permission:'.AgentPermission::BookingsView)->group(function (): void {
        Route::get('/bookings', [AgentBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [AgentBookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking}/cancellations', [BookingCancellationController::class, 'store'])->name('bookings.cancellations.store');
    });

    Route::middleware(['agent.permission:'.AgentPermission::PaymentsUpload, 'platform.module:payment_proofs'])->group(function (): void {
        Route::post('/bookings/{booking}/payment-proof', [BookingPaymentProofController::class, 'store'])
            ->middleware('throttle:payment-proof-submit')
            ->name('bookings.payment-proof');
    });

    Route::middleware('agent.admin')->group(function (): void {
        Route::get('/commissions', [AgentCommissionController::class, 'index'])->name('commissions.index');
        Route::get('/commissions/statements/{statement}', [AgentCommissionController::class, 'showStatement'])->name('commissions.statements.show');
    });

    Route::middleware(['agent.permission:'.AgentPermission::WalletView, 'platform.module:agent_wallet'])->group(function (): void {
        Route::get('/wallet', [AgentWalletController::class, 'show'])->name('wallet.show');
    });

    Route::middleware(['agent.permission:'.AgentPermission::WalletView, 'platform.module:agent_deposits'])->group(function (): void {
        Route::get('/deposits', [AgentDepositController::class, 'index'])->name('deposits.index');
    });

    Route::middleware(['agent.permission:'.AgentPermission::LedgerView, 'platform.module:agent_ledger'])->group(function (): void {
        Route::get('/ledger', [AgentLedgerController::class, 'index'])->name('ledger.index');
        Route::get('/accounting/ledger', [AccountingLedgerController::class, 'index'])->name('accounting.ledger.index');
        Route::get('/accounting/ledger/{ledgerTransaction}', [AccountingLedgerController::class, 'show'])->name('accounting.ledger.show');
    });

    Route::middleware(['agent.permission:'.AgentPermission::ReportsView, 'platform.module:agent_reports'])->group(function (): void {
        Route::get('/reports', [AgentReportsController::class, 'index'])->name('reports.index');
    });

    Route::get('/finance/statement', [FinanceStatementController::class, 'show'])->name('finance.statement.show');
    Route::get('/finance/statement/export', [FinanceStatementController::class, 'export'])->name('finance.statement.export');

    Route::middleware(['agent.permission:'.AgentPermission::PaymentsUpload, 'platform.module:agent_deposits'])->group(function (): void {
        Route::get('/deposits/create', [AgentDepositController::class, 'create'])->name('deposits.create');
        Route::post('/deposits', [AgentDepositController::class, 'store'])->name('deposits.store');
    });

    Route::middleware(['agent.permission:'.AgentPermission::TravelersManage, 'platform.module:saved_travelers'])->group(function (): void {
        Route::get('/travelers', [SavedTravelerController::class, 'index'])->name('travelers.index');
        Route::get('/travelers/create', [SavedTravelerController::class, 'create'])->name('travelers.create');
        Route::post('/travelers', [SavedTravelerController::class, 'store'])->name('travelers.store');
        Route::get('/travelers/{traveler}/edit', [SavedTravelerController::class, 'edit'])->name('travelers.edit');
        Route::patch('/travelers/{traveler}', [SavedTravelerController::class, 'update'])->name('travelers.update');
        Route::delete('/travelers/{traveler}', [SavedTravelerController::class, 'destroy'])->name('travelers.destroy');
    });

    Route::middleware(['agent.permission:'.AgentPermission::SupportManage, 'platform.module:agent_support'])->group(function (): void {
        Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
        Route::get('/support/tickets/create', [SupportTicketController::class, 'create'])->name('support.tickets.create');
        Route::post('/support/tickets', [SupportTicketController::class, 'store'])->name('support.tickets.store');
        Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
    });
});
