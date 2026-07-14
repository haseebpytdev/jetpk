<?php

use App\Enums\AccountType;
use App\Http\Controllers\Admin\AccountingLedgerController;
use App\Http\Controllers\Admin\AccountingReconciliationController;
use App\Http\Controllers\Admin\AdminGroupTicketingController;
use App\Http\Controllers\Admin\AdminLedgerController;
use App\Http\Controllers\Admin\AdminSectionController;
use App\Http\Controllers\Admin\AdminSettingsHubController;
use App\Http\Controllers\Admin\AgencyAboutUsSettingsController;
use App\Http\Controllers\Admin\AgencyBrandingController;
use App\Http\Controllers\Admin\AgencyCommunicationSettingsController;
use App\Http\Controllers\Admin\AgencyFooterSettingsController;
use App\Http\Controllers\Admin\AgencyHomepageController;
use App\Http\Controllers\Admin\AgencyManagementController;
use App\Http\Controllers\Admin\AgencyMediaController;
use App\Http\Controllers\Admin\AgencyMessageTemplateController;
use App\Http\Controllers\Admin\AgencyNotificationSettingController;
use App\Http\Controllers\Admin\AgencyPaymentSettingsController;
use App\Http\Controllers\Admin\AgencyUserAgencyRoleController;
use App\Http\Controllers\Admin\AgencyUserAgentPermissionController;
use App\Http\Controllers\Admin\AgentApplicationController;
use App\Http\Controllers\Admin\AgentCommissionController;
use App\Http\Controllers\Admin\AgentDepositController;
use App\Http\Controllers\Admin\BookingCancellationController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\BookingPaymentController;
use App\Http\Controllers\Admin\BookingRefundController;
use App\Http\Controllers\Admin\CmsPageController;
use App\Http\Controllers\Admin\CommunicationDeliveryLogController;
use App\Http\Controllers\Admin\CustomerManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceAdjustmentController;
use App\Http\Controllers\Admin\FinanceDashboardController;
use App\Http\Controllers\Admin\FinanceStatementController;
use App\Http\Controllers\Admin\GroupBookingManagementController;
use App\Http\Controllers\Admin\HomepageFeaturedFareController;
use App\Http\Controllers\Admin\MarkupRuleController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\SupplierConnectionController;
use App\Http\Controllers\Admin\SupportTicketController;
use App\Http\Controllers\Admin\SystemSafetyController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WalletAuditController;
use App\Http\Controllers\BookingDocumentController;
use App\Http\Controllers\BookingTicketingController;
use App\Models\User;
use App\Support\Ui\UiVersionResolver;
use Illuminate\Support\Facades\Route;

Route::bind('customer', function (string $value): User {
    $user = User::query()->findOrFail($value);
    if ($user->account_type !== AccountType::Customer) {
        abort(404);
    }

    return $user;
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/customers', [CustomerManagementController::class, 'index'])->name('customers.index');
    Route::get('/customers/guests/show', [CustomerManagementController::class, 'showGuest'])->name('customers.guests.show');
    Route::get('/customers/{customer}', [CustomerManagementController::class, 'show'])->name('customers.show');

    Route::get('/bookings', [BookingManagementController::class, 'index'])->name('bookings');
    Route::get('/bookings/data', [BookingManagementController::class, 'data'])->name('bookings.data');
    Route::get('/bookings/suggestions', [BookingManagementController::class, 'suggestions'])->name('bookings.suggestions');
    Route::get('/bookings/{booking}/preview', [BookingManagementController::class, 'preview'])->name('bookings.preview');
    Route::get('/bookings/{booking}', [BookingManagementController::class, 'show'])->name('bookings.show');
    Route::patch('/bookings/{booking}/status', [BookingManagementController::class, 'updateStatus'])->name('bookings.status');
    Route::post('/bookings/{booking}/notes', [BookingManagementController::class, 'storeNote'])->name('bookings.notes');
    Route::patch('/bookings/{booking}/assign-staff', [BookingManagementController::class, 'assignStaff'])->name('bookings.assign-staff');
    Route::middleware('platform.module:supplier_booking')->group(function (): void {
        Route::post('/bookings/{booking}/supplier-booking', [BookingManagementController::class, 'createSupplierBooking'])->name('bookings.supplier-booking');
        Route::post('/bookings/{booking}/prepare-supplier-pnr-context', [BookingManagementController::class, 'prepareSupplierPnrContext'])->name('bookings.prepare-supplier-pnr-context');
        Route::post('/bookings/{booking}/manual-pnr', [BookingManagementController::class, 'markManualPnr'])->name('bookings.manual-pnr');
        Route::post('/bookings/{booking}/sync-pnr-itinerary', [BookingManagementController::class, 'syncPnrItinerary'])->name('bookings.sync-pnr-itinerary');
        Route::post('/bookings/{booking}/sync-iati-booking', [BookingManagementController::class, 'syncIatiBooking'])->name('bookings.sync-iati-booking');
        Route::post('/bookings/{booking}/sync-pia-ndc-booking', [BookingManagementController::class, 'syncPiaNdcBooking'])->name('bookings.sync-pia-ndc-booking');
        Route::post('/bookings/{booking}/create-pia-ndc-option-pnr', [BookingManagementController::class, 'createPiaNdcOptionPnr'])->name('bookings.create-pia-ndc-option-pnr');
        Route::post('/bookings/{booking}/release-pia-ndc-option-pnr', [BookingManagementController::class, 'releasePiaNdcOptionPnr'])->name('bookings.release-pia-ndc-option-pnr');
        Route::post('/bookings/{booking}/refresh-pia-ndc-status', [BookingManagementController::class, 'refreshPiaNdcStatus'])->name('bookings.refresh-pia-ndc-status');
        Route::post('/bookings/{booking}/preview-pia-ndc-ticket', [BookingManagementController::class, 'previewPiaNdcTicket'])->name('bookings.preview-pia-ndc-ticket');
        Route::post('/bookings/{booking}/void-pia-ndc-ticket', [BookingManagementController::class, 'voidPiaNdcTicket'])->name('bookings.void-pia-ndc-ticket');
        Route::post('/bookings/{booking}/resend-pia-ndc-eticket', [BookingManagementController::class, 'resendPiaNdcEticket'])->name('bookings.resend-pia-ndc-eticket');
        Route::post('/bookings/{booking}/sync-airblue-booking', [BookingManagementController::class, 'syncAirBlueBooking'])->name('bookings.sync-airblue-booking');
    });
    Route::post('/bookings/{booking}/communication/send', [BookingManagementController::class, 'sendCommunication'])->name('bookings.communication.send');
    Route::post('/bookings/{booking}/communication/{communicationLog}/resend', [BookingManagementController::class, 'resendFailedCommunication'])->name('bookings.communication.resend');
    Route::get('/bookings/{booking}/audit/export', [BookingManagementController::class, 'exportAudit'])->name('bookings.audit.export');
    Route::post('/bookings/{booking}/payments', [BookingPaymentController::class, 'store'])->name('bookings.payments.store');
    Route::patch('/bookings/payments/{bookingPayment}/verify', [BookingPaymentController::class, 'verify'])->name('bookings.payments.verify');
    Route::patch('/bookings/payments/{bookingPayment}/reject', [BookingPaymentController::class, 'reject'])->name('bookings.payments.reject');
    Route::post('/bookings/{booking}/cancellations', [BookingCancellationController::class, 'store'])->name('bookings.cancellations.store');
    Route::patch('/bookings/cancellations/{cancellationRequest}/approve', [BookingCancellationController::class, 'approve'])->name('bookings.cancellations.approve');
    Route::patch('/bookings/cancellations/{cancellationRequest}/reject', [BookingCancellationController::class, 'reject'])->name('bookings.cancellations.reject');
    Route::patch('/bookings/cancellations/{cancellationRequest}/process', [BookingCancellationController::class, 'process'])->name('bookings.cancellations.process');
    Route::post('/bookings/{booking}/refunds', [BookingRefundController::class, 'store'])->name('bookings.refunds.store');
    Route::patch('/bookings/refunds/{bookingRefund}/approve', [BookingRefundController::class, 'approve'])->name('bookings.refunds.approve');
    Route::patch('/bookings/refunds/{bookingRefund}/mark-paid', [BookingRefundController::class, 'markPaid'])->name('bookings.refunds.mark-paid');
    Route::patch('/bookings/refunds/{bookingRefund}/reject', [BookingRefundController::class, 'reject'])->name('bookings.refunds.reject');
    Route::post('/bookings/{booking}/issue-ticket', [BookingTicketingController::class, 'issue'])
        ->middleware('platform.module:ticketing')
        ->name('bookings.issue-ticket');
    Route::post('/bookings/{booking}/documents/confirmation', [BookingDocumentController::class, 'bookingConfirmation'])->name('bookings.documents.confirmation');
    Route::post('/bookings/{booking}/documents/invoice', [BookingDocumentController::class, 'invoice'])->name('bookings.documents.invoice');
    Route::post('/bookings/{booking}/documents/ticket-itinerary', [BookingDocumentController::class, 'ticketItinerary'])->name('bookings.documents.ticket-itinerary');
    Route::post('/bookings/{booking}/documents/refund-note', [BookingDocumentController::class, 'refundNote'])->name('bookings.documents.refund-note');
    Route::post('/bookings/{booking}/documents/cancellation-confirmation', [BookingDocumentController::class, 'cancellationConfirmation'])->name('bookings.documents.cancellation-confirmation');
    Route::post('/bookings/payments/{bookingPayment}/documents/receipt', [BookingDocumentController::class, 'paymentReceipt'])->name('bookings.payments.documents.receipt');
    Route::get('/bookings/documents/{bookingDocument}/download', [BookingDocumentController::class, 'download'])->name('bookings.documents.download');
    Route::get('/commissions', [AgentCommissionController::class, 'index'])->name('commissions.index');
    Route::get('/commissions/{agent}', [AgentCommissionController::class, 'show'])->name('commissions.show');
    Route::post('/commissions/entries/{entry}/approve', [AgentCommissionController::class, 'approve'])->name('commissions.entries.approve');
    Route::post('/commissions/entries/{entry}/reject', [AgentCommissionController::class, 'reject'])->name('commissions.entries.reject');
    Route::post('/commissions/{agent}/adjustments', [AgentCommissionController::class, 'adjustment'])->name('commissions.adjustments.store');
    Route::post('/commissions/{agent}/payouts', [AgentCommissionController::class, 'payout'])->name('commissions.payouts.store');
    Route::post('/commissions/{agent}/statements', [AgentCommissionController::class, 'statement'])->name('commissions.statements.store');
    Route::middleware('platform.module:agent_deposits')->group(function (): void {
        Route::get('/agent-deposits', [AgentDepositController::class, 'index'])->name('agent-deposits.index');
        Route::get('/agent-deposits/{deposit}', [AgentDepositController::class, 'show'])->name('agent-deposits.show');
        Route::get('/agent-deposits/{deposit}/proof', [AgentDepositController::class, 'proof'])->name('agent-deposits.proof');
        Route::patch('/agent-deposits/{deposit}/approve', [AgentDepositController::class, 'approve'])->name('agent-deposits.approve');
        Route::patch('/agent-deposits/{deposit}/reject', [AgentDepositController::class, 'reject'])->name('agent-deposits.reject');
    });
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/suspend', [UserManagementController::class, 'suspend'])->name('users.suspend');
    Route::patch('/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/send-invite', [UserManagementController::class, 'sendInvite'])->name('users.send-invite');
    Route::post('/users/{user}/reset-password-link', [UserManagementController::class, 'sendResetPasswordLink'])->name('users.reset-password-link');

    Route::get('/agencies', [AgencyManagementController::class, 'index'])->name('agencies.index');
    Route::get('/agencies/{agency}', [AgencyManagementController::class, 'show'])->name('agencies.show');
    Route::patch('/agencies/{agency}/prefix', [AgencyManagementController::class, 'updatePrefix'])->name('agencies.prefix.update');
    Route::patch('/agencies/{agency}/users/{user}/agency-role', [AgencyUserAgencyRoleController::class, 'update'])
        ->name('agencies.users.agency-role.update');
    Route::patch('/agencies/{agency}/users/{user}/agent-permissions', [AgencyUserAgentPermissionController::class, 'update'])
        ->name('agencies.users.agent-permissions.update');
    Route::post('/agencies/{agency}/users/{user}/agent-permissions/apply-template', [AgencyUserAgentPermissionController::class, 'applyTemplate'])
        ->name('agencies.users.agent-permissions.apply-template');

    Route::get('/agents', [AdminSectionController::class, 'agents'])->name('agents');
    Route::get('/agents/data', [AdminSectionController::class, 'agentsData'])->name('agents.data');
    Route::get('/agents/suggestions', [AdminSectionController::class, 'agentsSuggestions'])->name('agents.suggestions');
    // Phase 23B.7.1 — alias matching the documented endpoint name (admin.agents.search).
    // Same handler as 'agents.suggestions'; the URL path mirrors the spec wording.
    Route::get('/agents/search', [AdminSectionController::class, 'agentsSuggestions'])->name('agents.search');
    Route::get('/agents/export', [AdminSectionController::class, 'agentsExport'])->name('agents.export');
    Route::get('/agents/{agent}/preview', [AdminSectionController::class, 'agentPreview'])->name('agents.preview');
    Route::middleware('platform.module:agent_applications')->group(function (): void {
        Route::get('/agent-applications/data', [AgentApplicationController::class, 'data'])->name('agent-applications.data');
        Route::get('/agent-applications/suggestions', [AgentApplicationController::class, 'suggestions'])->name('agent-applications.suggestions');
        Route::get('/agent-applications', [AgentApplicationController::class, 'index'])->name('agent-applications.index');
        Route::get('/agent-applications/export', [AgentApplicationController::class, 'export'])->name('agent-applications.export');
        Route::get('/agent-applications/{application}', [AgentApplicationController::class, 'show'])->name('agent-applications.show');
        Route::patch('/agent-applications/{application}/approve', [AgentApplicationController::class, 'approve'])->name('agent-applications.approve');
        Route::patch('/agent-applications/{application}/reject', [AgentApplicationController::class, 'reject'])->name('agent-applications.reject');
        Route::patch('/agent-applications/{application}/needs-more-info', [AgentApplicationController::class, 'needsMoreInfo'])->name('agent-applications.needs-more-info');
    });
    Route::get('/staff', [AdminSectionController::class, 'staff'])->name('staff');
    Route::get('/cms-pages', [CmsPageController::class, 'index'])->name('cms-pages.index');
    Route::get('/cms-pages/create', [CmsPageController::class, 'create'])->name('cms-pages.create');
    Route::post('/cms-pages', [CmsPageController::class, 'store'])->name('cms-pages.store');
    Route::get('/cms-pages/{cmsPage}/edit', [CmsPageController::class, 'edit'])->name('cms-pages.edit');
    Route::patch('/cms-pages/{cmsPage}', [CmsPageController::class, 'update'])->name('cms-pages.update');
    Route::patch('/cms-pages/{cmsPage}/archive', [CmsPageController::class, 'archive'])->name('cms-pages.archive');
    Route::delete('/cms-pages/{cmsPage}', [CmsPageController::class, 'destroy'])->name('cms-pages.destroy');
    Route::get('/cms-pages/{cmsPage}/preview', [CmsPageController::class, 'preview'])->name('cms-pages.preview');
    Route::get('/promo-codes', [PromoCodeController::class, 'index'])->name('promo-codes.index');
    Route::get('/promo-codes/create', [PromoCodeController::class, 'create'])->name('promo-codes.create');
    Route::post('/promo-codes', [PromoCodeController::class, 'store'])->name('promo-codes.store');
    Route::get('/promo-codes/{promoCode}/edit', [PromoCodeController::class, 'edit'])->name('promo-codes.edit');
    Route::patch('/promo-codes/{promoCode}', [PromoCodeController::class, 'update'])->name('promo-codes.update');
    Route::patch('/promo-codes/{promoCode}/toggle-status', [PromoCodeController::class, 'toggleStatus'])->name('promo-codes.toggle-status');
    Route::middleware('platform.module:markup_settings')->group(function (): void {
        Route::get('/markups', [MarkupRuleController::class, 'index'])->name('markups');
        Route::get('/markups/create', [MarkupRuleController::class, 'create'])->name('markups.create');
        Route::post('/markups', [MarkupRuleController::class, 'store'])->name('markups.store');
        Route::get('/markups/{markupRule}/edit', [MarkupRuleController::class, 'edit'])->name('markups.edit');
        Route::patch('/markups/{markupRule}', [MarkupRuleController::class, 'update'])->name('markups.update');
        Route::patch('/markups/{markupRule}/toggle-status', [MarkupRuleController::class, 'toggleStatus'])->name('markups.toggle-status');
        Route::delete('/markups/{markupRule}', [MarkupRuleController::class, 'destroy'])->name('markups.destroy');
    });
    Route::middleware('platform.module:api_settings')->group(function (): void {
        Route::get('/api-settings', [SupplierConnectionController::class, 'index'])->name('api-settings');
        Route::get('/api-settings/create', [SupplierConnectionController::class, 'create'])->name('api-settings.create');
        Route::post('/api-settings', [SupplierConnectionController::class, 'store'])->name('api-settings.store');
        Route::get('/api-settings/{supplierConnection}/edit', [SupplierConnectionController::class, 'edit'])->name('api-settings.edit');
        Route::patch('/api-settings/{supplierConnection}', [SupplierConnectionController::class, 'update'])->name('api-settings.update');
        Route::delete('/api-settings/{supplierConnection}', [SupplierConnectionController::class, 'destroy'])->name('api-settings.destroy');
        Route::patch('/api-settings/{supplierConnection}/test', [SupplierConnectionController::class, 'test'])->name('api-settings.test');
        Route::patch('/api-settings/{supplierConnection}/toggle-status', [SupplierConnectionController::class, 'toggleStatus'])->name('api-settings.toggle-status');
    });
    Route::get('/roles-permissions', [AdminSectionController::class, 'rolesPermissions'])->name('roles-permissions');
    Route::middleware('platform.module:finance_reports')->group(function (): void {
        Route::get('/ledger', [AdminLedgerController::class, 'index'])->name('ledger.index');
        Route::get('/ledger/{transaction}', [AdminLedgerController::class, 'show'])->name('ledger.show');
        Route::get('/accounting/ledger', [AccountingLedgerController::class, 'index'])->name('accounting.ledger.index');
        Route::get('/accounting/ledger/export', [AccountingLedgerController::class, 'export'])->name('accounting.ledger.export');
        Route::get('/accounting/ledger/{ledgerTransaction}', [AccountingLedgerController::class, 'show'])->name('accounting.ledger.show');
        Route::get('/accounting/reconciliation', [AccountingReconciliationController::class, 'index'])->name('accounting.reconciliation.index');
        Route::get('/accounting/reconciliation/export', [AccountingReconciliationController::class, 'export'])->name('accounting.reconciliation.export');
        Route::get('/finance/dashboard', [FinanceDashboardController::class, 'index'])->name('finance.dashboard');
        Route::get('/finance/dashboard/export', [FinanceDashboardController::class, 'export'])->name('finance.dashboard.export');
        Route::get('/finance/wallet-audit', [WalletAuditController::class, 'index'])->name('finance.wallet-audit.index');
        Route::get('/finance/wallet-audit/export', [WalletAuditController::class, 'export'])->name('finance.wallet-audit.export');
        Route::get('/finance/wallet-audit/archive-preview', [WalletAuditController::class, 'archivePreview'])->name('finance.wallet-audit.archive-preview');
        Route::post('/finance/wallet-audit/archive', [WalletAuditController::class, 'archive'])->name('finance.wallet-audit.archive');
        Route::get('/finance/adjustments', [FinanceAdjustmentController::class, 'index'])->name('finance.adjustments.index');
        Route::get('/finance/adjustments/export', [FinanceAdjustmentController::class, 'export'])->name('finance.adjustments.export');
        Route::get('/finance/adjustments/create', [FinanceAdjustmentController::class, 'create'])->name('finance.adjustments.create');
        Route::post('/finance/adjustments', [FinanceAdjustmentController::class, 'store'])->name('finance.adjustments.store');
        Route::get('/finance/adjustments/{walletTransaction}', [FinanceAdjustmentController::class, 'show'])->name('finance.adjustments.show');
        Route::get('/finance/adjustments/{walletTransaction}/reverse', [FinanceAdjustmentController::class, 'reverseConfirm'])->name('finance.adjustments.reverse.confirm');
        Route::post('/finance/adjustments/{walletTransaction}/reverse', [FinanceAdjustmentController::class, 'reverse'])->name('finance.adjustments.reverse');
        Route::get('/finance/statements', [FinanceStatementController::class, 'index'])->name('finance.statements.index');
        Route::get('/finance/statements/{agency}', [FinanceStatementController::class, 'show'])->name('finance.statements.show');
        Route::get('/finance/statements/{agency}/export', [FinanceStatementController::class, 'export'])->name('finance.statements.export');
        Route::get('/reports', [AdminSectionController::class, 'reports'])->name('reports');
        Route::get('/reports/supplier-diagnostics', [AdminSectionController::class, 'supplierDiagnostics'])->name('reports.supplier-diagnostics');
        Route::get('/reports/export/{type}', [AdminSectionController::class, 'reportsExport'])
            ->where('type', 'sales|payments|bookings|agents|refunds|supplier_diagnostics|documents')
            ->name('reports.export');
    });
    Route::get('/settings', [AdminSettingsHubController::class, 'index'])->name('settings.index');

    Route::get('/settings/payments', [AgencyPaymentSettingsController::class, 'index'])->name('settings.payments.index');
    Route::patch('/settings/payments/abhipay', [AgencyPaymentSettingsController::class, 'updateAbhiPay'])->name('settings.payments.abhipay.update');
    Route::post('/settings/payments/abhipay/test', [AgencyPaymentSettingsController::class, 'testAbhiPay'])
        ->middleware('throttle:6,1')
        ->name('settings.payments.abhipay.test');
    Route::middleware('platform.module:branding_settings')->group(function (): void {
        Route::get('/settings/branding', [AgencyBrandingController::class, 'edit'])->name('settings.branding.edit');
        Route::patch('/settings/branding', [AgencyBrandingController::class, 'update'])->name('settings.branding.update');
        Route::get('/settings/theme-palette', [\App\Http\Controllers\Admin\JetpkThemePaletteSettingsController::class, 'edit'])->name('settings.theme-palette.edit');
        Route::patch('/settings/theme-palette', [\App\Http\Controllers\Admin\JetpkThemePaletteSettingsController::class, 'update'])->name('settings.theme-palette.update');
        Route::post('/settings/theme-palette/reset/{theme}', [\App\Http\Controllers\Admin\JetpkThemePaletteSettingsController::class, 'reset'])->name('settings.theme-palette.reset');
        Route::post('/settings/branding/logo-background/stage', [\App\Http\Controllers\Admin\BrandingLogoBackgroundController::class, 'stage'])->name('settings.branding.logo-background.stage');
        Route::post('/settings/branding/logo-background/{process:uuid}/run', [\App\Http\Controllers\Admin\BrandingLogoBackgroundController::class, 'run'])->name('settings.branding.logo-background.run');
        Route::get('/settings/branding/logo-background/{process:uuid}', [\App\Http\Controllers\Admin\BrandingLogoBackgroundController::class, 'show'])->name('settings.branding.logo-background.show');
        Route::post('/settings/branding/logo-background/{process:uuid}/accept', [\App\Http\Controllers\Admin\BrandingLogoBackgroundController::class, 'accept'])->name('settings.branding.logo-background.accept');
        Route::post('/settings/branding/logo-background/{process:uuid}/discard', [\App\Http\Controllers\Admin\BrandingLogoBackgroundController::class, 'discard'])->name('settings.branding.logo-background.discard');
        Route::get('/settings/branding/logo-background/{process:uuid}/preview/{variant}', [\App\Http\Controllers\Admin\BrandingLogoBackgroundController::class, 'preview'])->name('settings.branding.logo-background.preview');
        Route::get('/settings/media/background-removal', [\App\Http\Controllers\Admin\BackgroundRemovalSettingsController::class, 'edit'])->name('settings.background-removal.edit');
        Route::patch('/settings/media/background-removal', [\App\Http\Controllers\Admin\BackgroundRemovalSettingsController::class, 'update'])->name('settings.background-removal.update');
        Route::post('/settings/media/background-removal/test', [\App\Http\Controllers\Admin\BackgroundRemovalSettingsController::class, 'test'])
            ->middleware('throttle:6,1')
            ->name('settings.background-removal.test');
        Route::get('/settings/branding/footer', [AgencyFooterSettingsController::class, 'edit'])->name('settings.branding.footer.edit');
        Route::patch('/settings/branding/footer', [AgencyFooterSettingsController::class, 'update'])->name('settings.branding.footer.update');
        Route::get('/settings/branding/about-us', [AgencyAboutUsSettingsController::class, 'edit'])->name('settings.branding.about-us.edit');
        Route::patch('/settings/branding/about-us', [AgencyAboutUsSettingsController::class, 'update'])->name('settings.branding.about-us.update');
        Route::get('/settings/homepage', [AgencyHomepageController::class, 'edit'])->name('settings.homepage.edit');
        Route::patch('/settings/homepage/{section}', [AgencyHomepageController::class, 'update'])->name('settings.homepage.update');
        Route::get('/settings/homepage-featured-fares', [HomepageFeaturedFareController::class, 'index'])->name('settings.homepage-featured-fares.index');
        Route::post('/settings/homepage-featured-fares', [HomepageFeaturedFareController::class, 'store'])->name('settings.homepage-featured-fares.store');
        Route::get('/settings/homepage-featured-fares/{homepageFeaturedFare}/edit', [HomepageFeaturedFareController::class, 'edit'])->name('settings.homepage-featured-fares.edit');
        Route::patch('/settings/homepage-featured-fares/{homepageFeaturedFare}', [HomepageFeaturedFareController::class, 'update'])->name('settings.homepage-featured-fares.update');
        Route::delete('/settings/homepage-featured-fares/{homepageFeaturedFare}', [HomepageFeaturedFareController::class, 'destroy'])->name('settings.homepage-featured-fares.destroy');
        Route::post('/settings/homepage-featured-fares/{homepageFeaturedFare}/refresh', [HomepageFeaturedFareController::class, 'refresh'])->name('settings.homepage-featured-fares.refresh');
        Route::get('/settings/media', [AgencyMediaController::class, 'index'])->name('settings.media.index');
        Route::post('/settings/media', [AgencyMediaController::class, 'store'])->name('settings.media.store');
        Route::delete('/settings/media/{agencyMedia}', [AgencyMediaController::class, 'destroy'])->name('settings.media.destroy');
        Route::get('/branding', [AdminSectionController::class, 'branding'])->name('branding');
    });
    Route::middleware('platform.module:notifications')->group(function (): void {
        Route::get('/settings/communications', [AgencyCommunicationSettingsController::class, 'index'])->name('settings.communications.index');
        Route::patch('/settings/communications', [AgencyCommunicationSettingsController::class, 'update'])->name('settings.communications.update');
        Route::post('/settings/communications/test-email', [AgencyCommunicationSettingsController::class, 'testEmail'])
            ->middleware('throttle:communication-test-email')
            ->name('settings.communications.test-email');
        Route::post('/settings/communications/test-whatsapp', [AgencyCommunicationSettingsController::class, 'testWhatsapp'])->name('settings.communications.test-whatsapp');
        Route::get('/settings/communications/templates', [AgencyMessageTemplateController::class, 'index'])->name('settings.communications.templates.index');
        Route::get('/settings/communications/templates/preview/{registryKey}', [AgencyMessageTemplateController::class, 'preview'])->name('settings.communications.templates.preview');
        Route::get('/settings/communications/templates/{event}/{channel}/edit', [AgencyMessageTemplateController::class, 'edit'])->name('settings.communications.templates.edit');
        Route::patch('/settings/communications/templates/{event}/{channel}', [AgencyMessageTemplateController::class, 'update'])->name('settings.communications.templates.update');
        Route::delete('/settings/communications/templates/{event}/{channel}', [AgencyMessageTemplateController::class, 'reset'])->name('settings.communications.templates.reset');
        Route::get('/settings/communications/notification-events', [AgencyNotificationSettingController::class, 'index'])->name('settings.communications.notification-events.index');
        Route::patch('/settings/communications/notification-events', [AgencyNotificationSettingController::class, 'update'])->name('settings.communications.notification-events.update');
        Route::get('/settings/communications/delivery-log', [CommunicationDeliveryLogController::class, 'index'])->name('settings.communications.delivery-log.index');
        Route::post('/settings/communications/delivery-log/{communicationLog}/resend', [CommunicationDeliveryLogController::class, 'resend'])
            ->middleware('throttle:communication-resend')
            ->name('settings.communications.delivery-log.resend');
    });
    Route::get('/system-health', [SystemSafetyController::class, 'systemHealth'])->name('system-health');
    Route::get('/deployment-checklist', [SystemSafetyController::class, 'deploymentChecklist'])->name('deployment-checklist');
    Route::get('/go-live-checklist', [AdminSectionController::class, 'goLiveChecklist'])->name('go-live-checklist');

    Route::prefix('group-ticketing')->name('group-ticketing.')->group(function (): void {
        Route::get('/', [AdminGroupTicketingController::class, 'index'])->name('index');
        Route::get('/tiles', [AdminGroupTicketingController::class, 'tilesIndex'])->name('tiles.index');
        Route::post('/tiles/upsert', [AdminGroupTicketingController::class, 'tilesUpsert'])->name('tiles.upsert');
        Route::post('/tiles/batch-upsert', [AdminGroupTicketingController::class, 'tilesBatchUpsert'])->name('tiles.batch-upsert');
        Route::get('/tiles/create', [AdminGroupTicketingController::class, 'tilesCreate'])->name('tiles.create');
        Route::post('/tiles', [AdminGroupTicketingController::class, 'tilesStore'])->name('tiles.store');
        Route::get('/tiles/{groupHomepageTile}/edit', [AdminGroupTicketingController::class, 'tilesEdit'])->name('tiles.edit');
        Route::put('/tiles/{groupHomepageTile}', [AdminGroupTicketingController::class, 'tilesUpdate'])->name('tiles.update');
        Route::delete('/tiles/{groupHomepageTile}', [AdminGroupTicketingController::class, 'tilesDestroy'])->name('tiles.destroy');
        Route::get('/categories', [AdminGroupTicketingController::class, 'categoriesIndex'])->name('categories.index');
        Route::post('/categories', [AdminGroupTicketingController::class, 'categoriesStore'])->name('categories.store');
        Route::patch('/categories/{groupCategory}', [AdminGroupTicketingController::class, 'categoriesUpdate'])->name('categories.update');
        Route::delete('/categories/{groupCategory}', [AdminGroupTicketingController::class, 'categoriesDestroy'])->name('categories.destroy');
        Route::get('/inventory', [AdminGroupTicketingController::class, 'inventoryIndex'])->name('inventory.index');
        Route::post('/inventory/sync', [AdminGroupTicketingController::class, 'inventorySync'])->name('inventory.sync');
    });

    Route::prefix('group-bookings')->name('group-bookings.')->group(function (): void {
        Route::get('/', [GroupBookingManagementController::class, 'index'])->name('index');
        Route::get('/restrictions', [GroupBookingManagementController::class, 'restrictions'])->name('restrictions');
        Route::post('/restrictions/{user}/reset', [GroupBookingManagementController::class, 'resetRestriction'])->name('restrictions.reset');
        Route::get('/{groupBooking}', [GroupBookingManagementController::class, 'show'])->name('show');
        Route::post('/{groupBooking}/verify-payment', [GroupBookingManagementController::class, 'verifyPayment'])->name('verify-payment');
        Route::post('/{groupBooking}/reject-payment', [GroupBookingManagementController::class, 'rejectPayment'])->name('reject-payment');
    });

    Route::middleware('platform.module:support_system')->group(function (): void {
        Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
        Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
        Route::patch('/support/tickets/{ticket}/status', [SupportTicketController::class, 'updateStatus'])->name('support.tickets.status');
        Route::patch('/support/tickets/{ticket}/assign', [SupportTicketController::class, 'assign'])->name('support.tickets.assign');
        Route::patch('/support/tickets/{ticket}/forward', [SupportTicketController::class, 'forward'])->name('support.tickets.forward');
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
                'resolved_view' => $resolver->resolveViewName('dashboard.admin.index'),
            ]);
        })->name('test.ui-version');
    }
});
