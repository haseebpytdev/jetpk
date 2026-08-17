<?php

namespace Tests\Support;

use App\Http\Controllers\Admin\AgencyBrandingController;
use App\Http\Controllers\Admin\AgencyCommunicationSettingsController;
use App\Http\Controllers\Admin\AgencyMessageTemplateController;
use App\Http\Controllers\Admin\AgencyNotificationSettingController;
use App\Http\Controllers\Admin\AgencyManagementController;
use App\Http\Controllers\Admin\AgentCommissionController;
use App\Http\Controllers\Admin\AccountingLedgerController;
use App\Http\Controllers\Admin\AccountingReconciliationController;
use App\Http\Controllers\Admin\AgentApplicationController;
use App\Http\Controllers\Admin\AdminSectionController;
use App\Http\Controllers\Admin\AdminSettingsHubController;
use App\Http\Controllers\Admin\AgentDepositController;
use App\Http\Controllers\Admin\BackgroundRemovalSettingsController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\ClientPageSettingsController;
use App\Http\Controllers\Admin\CustomerManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceAdjustmentController;
use App\Http\Controllers\Admin\FinanceDashboardController;
use App\Http\Controllers\Admin\FinanceStatementController;
use App\Http\Controllers\Admin\AdminGroupTicketingController;
use App\Http\Controllers\Admin\SupplierConnectionController;
use App\Http\Controllers\Admin\SystemSafetyController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\WalletAuditController;
use App\Http\Controllers\Agent\AccountingLedgerController as AgentAccountingLedgerController;
use App\Http\Controllers\Staff\AccountingLedgerController as StaffAccountingLedgerController;
use App\Http\Controllers\Staff\AccountingReconciliationController as StaffAccountingReconciliationController;
use App\Http\Controllers\Staff\BookingController as StaffBookingController;
use App\Http\Controllers\Staff\FinanceStatementController as StaffFinanceStatementController;
use App\Http\Controllers\Staff\SupportTicketController as StaffSupportTicketController;
use App\Http\Controllers\Admin\JetpkThemePaletteSettingsController;
use App\Http\Controllers\Admin\SupportTicketController as AdminSupportTicketController;
use App\Http\Controllers\Admin\AdminLedgerController;
use App\Http\Controllers\Staff\LedgerController as StaffLedgerController;
use App\Http\Controllers\Staff\ReportsController as StaffReportsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgentApplication;
use App\Models\AgentDepositRequest;
use App\Models\AgentWalletTransaction;
use App\Models\Booking;
use App\Models\LedgerTransaction;
use App\Models\SupplierConnection;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\GroupTicketing\GroupInventoryFacetService;
use App\Support\GroupTicketing\GroupHomepageTilePresenter;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

/**
 * Render retired Blade admin workspaces directly while legacy GET routes redirect to Next.
 */
trait AdminLegacyViewTestHelpers
{
    protected function adminBookingShowHtml(User $admin, Booking $booking): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/bookings/'.$booking->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(BookingManagementController::class)->show($booking)->render();
    }

    protected function adminDashboardHtml(User $admin): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(DashboardController::class)->index()->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminBookingsIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/bookings', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(BookingManagementController::class)->index($request)->render();
    }

    protected function adminCustomersIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/customers', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(CustomerManagementController::class)->index($request)->render();
    }

    protected function assertLegacyAdminBookingsIndexRedirect(User $admin): void
    {
        $response = $this->actingAs($admin)->get(route('admin.bookings'));
        $response->assertRedirect();
        $this->assertStringContainsString('/admin/dashboard/bookings', (string) $response->headers->get('Location'));
    }

    protected function assertLegacyAdminCustomersIndexRedirect(User $admin): void
    {
        $response = $this->actingAs($admin)->get(route('admin.customers.index'));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($target, '/admin/dashboard/customers')
            || str_contains($target, '/admin/dashboard'),
            'Expected customers index GET to redirect into Next customers. Got: '.$target
        );
    }

    protected function assertLegacyAdminReportsRedirect(User $admin): void
    {
        $response = $this->actingAs($admin)->get(route('admin.reports'));
        $response->assertRedirect();
        $this->assertStringContainsString('/admin/dashboard/reports', (string) $response->headers->get('Location'));
    }

    protected function assertLegacyStaffBookingsIndexRedirect(User $staff): void
    {
        $response = $this->actingAs($staff)->get(route('staff.bookings.index'));
        $response->assertRedirect();
        $this->assertStringContainsString('/staff/dashboard/bookings', (string) $response->headers->get('Location'));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminReportsHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/reports', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminSectionController::class)->reports($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminDepositsIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/agent-deposits', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AgentDepositController::class)->index($request)->render();
    }

    protected function adminDepositShowHtml(User $admin, AgentDepositRequest $deposit): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/agent-deposits/'.$deposit->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgentDepositController::class)->show($deposit)->render();
    }

    protected function assertLegacyAdminDepositShowRedirect(User $admin, AgentDepositRequest $deposit): void
    {
        $response = $this->actingAs($admin)->get(route('admin.agent-deposits.show', $deposit));
        $response->assertRedirect();
        $this->assertStringContainsString('/admin/dashboard/deposits', (string) $response->headers->get('Location'));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminSettingsIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        $request = Request::create('/admin/settings', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminSettingsHubController::class)->index($request)->render();
    }

    protected function adminPageSettingsIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(ClientPageSettingsController::class)->index()->render();
    }

    protected function adminPageSettingsEditHtml(User $admin, string $pageKey = 'home'): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(ClientPageSettingsController::class)->editView($pageKey)->render();
    }

    protected function assertLegacyPageSettingsHomeRedirect(User $user): void
    {
        $response = $this->actingAs($user)->get('/admin/page-settings/home');
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($target, '/admin/dashboard/cms')
            || str_contains($target, 'page-settings'),
            'Expected page-settings home GET to redirect into Next CMS. Got: '.$target
        );
    }

    protected function adminBackgroundRemovalEditHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/settings/media/background-removal', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(BackgroundRemovalSettingsController::class)->edit($request)->render();
    }

    protected function adminNotificationEventsHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/settings/communications/notification-events', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgencyNotificationSettingController::class)->index($request)->render();
    }

    protected function adminEmailTemplatesIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/settings/communications/templates', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgencyMessageTemplateController::class)->index($request)->render();
    }

    protected function adminCommunicationsSettingsHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/settings/communications', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgencyCommunicationSettingsController::class)->index($request)->render();
    }

    protected function adminApiSettingsCreateHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/api-settings/create', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(SupplierConnectionController::class)->create($request)->render();
    }

    protected function adminApiSettingsEditHtml(User $admin, SupplierConnection $connection): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(SupplierConnectionController::class)->edit($connection)->render();
    }

    protected function adminUserShowHtml(User $admin, User $user): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/users/'.$user->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(UserManagementController::class)->show($user)->render();
    }

    protected function adminUserEditHtml(User $admin, User $user): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/users/'.$user->id.'/edit', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(UserManagementController::class)->edit($request, $user)->render();
    }

    protected function agentApplicationShowHtml(User $admin, AgentApplication $application): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/agent-applications/'.$application->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgentApplicationController::class)->show($application)->render();
    }

    protected function adminFinanceDashboardHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/dashboard', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceDashboardController::class)->index($request)->render();
    }

    protected function adminFinanceStatementsIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/statements', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceStatementController::class)->index($request)->render();
    }

    protected function adminFinanceStatementShowHtml(User $admin, Agency $agency): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/statements/'.$agency->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceStatementController::class)->show($request, $agency)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminFinanceStatementShowHtmlWithQuery(User $admin, Agency $agency, array $query): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/statements/'.$agency->id, 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(FinanceStatementController::class)->show($request, $agency)->render();
    }

    protected function staffFinanceStatementsIndexHtml(User $staff): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/finance/statements', 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffFinanceStatementController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function staffFinanceStatementShowHtml(User $staff, Agency $agency, array $query = []): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/finance/statements/'.$agency->id, 'GET', $query);
        $request->setUserResolver(fn () => $staff);

        return app(StaffFinanceStatementController::class)->show($request, $agency)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminMasterLedgerHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/ledger', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminLedgerController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function staffMasterLedgerHtml(User $staff, array $query = []): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/ledger', 'GET', $query);
        $request->setUserResolver(fn () => $staff);

        return app(StaffLedgerController::class)->index($request)->render();
    }

    protected function staffReportsIndexHtml(User $staff): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/reports', 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffReportsController::class)->index($request)->render();
    }

    protected function adminFinanceAdjustmentsIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/adjustments', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceAdjustmentController::class)->index($request)->render();
    }

    protected function adminFinanceAdjustmentsCreateHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/adjustments/create', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(FinanceAdjustmentController::class)->create($request)->render();
    }

    protected function adminFinanceAdjustmentsShowHtml(User $admin, AgentWalletTransaction $walletTransaction): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/adjustments/'.$walletTransaction->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceAdjustmentController::class)->show($request, $walletTransaction)->render();
    }

    protected function adminAccountingReconciliationIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/accounting/reconciliation', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AccountingReconciliationController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminWalletAuditIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/wallet-audit', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(WalletAuditController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function agentAccountingLedgerIndexHtml(User $agentUser, array $query = []): string
    {
        $this->actingAs($agentUser);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/agent/accounting/ledger', 'GET', $query);
        $request->setUserResolver(fn () => $agentUser);

        $response = app(AgentAccountingLedgerController::class)->index($request);
        $this->assertInstanceOf(\Illuminate\Contracts\View\View::class, $response);

        return $response->render();
    }

    protected function assertLegacyAgentAccountingRedirect(User $agentUser, string $uri = '/agent/accounting/ledger'): void
    {
        $response = $this->actingAs($agentUser)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($target, '/agent/dashboard') || str_contains($target, '/agent/accounting'),
            'Expected agent accounting GET to redirect into Next agent accounting. Got: '.$target
        );
    }

    /**
     * Agent accounting GETs may still render Blade until the portal route is fully Next-migrated.
     */
    protected function assertLegacyAgentAccountingRedirectOrOk(User $agentUser, string $uri = '/agent/accounting/ledger'): void
    {
        $response = $this->actingAs($agentUser)->get($uri);
        $this->assertContains(
            $response->getStatusCode(),
            [200, 301, 302],
            'Expected agent accounting GET to render or redirect. Got: '.$response->getStatusCode()
        );
        if ($response->isRedirect()) {
            $target = (string) $response->headers->get('Location');
            $this->assertTrue(
                str_contains($target, '/agent/dashboard') || str_contains($target, '/agent/accounting'),
                'Unexpected agent accounting redirect target: '.$target
            );
        }
    }

    protected function adminCommissionsIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/commissions', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AgentCommissionController::class)->index($request)->render();
    }

    protected function adminCommissionShowHtml(User $admin, Agent $agent): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(AgentCommissionController::class)->show($agent)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminAccountingLedgerIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/accounting/ledger', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AccountingLedgerController::class)->index($request)->render();
    }

    protected function adminAccountingLedgerShowHtml(User $admin, LedgerTransaction $transaction): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/accounting/ledger/'.$transaction->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AccountingLedgerController::class)->show($request, $transaction)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function staffAccountingLedgerIndexHtml(User $staff, array $query = []): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/accounting/ledger', 'GET', $query);
        $request->setUserResolver(fn () => $staff);

        return app(StaffAccountingLedgerController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminStaffIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/staff', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AdminSectionController::class)->staff($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminAgenciesIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/agencies', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AgencyManagementController::class)->index($request)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminAgencyShowHtml(User $admin, Agency $agency, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/agencies/'.$agency->id, 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(AgencyManagementController::class)->show($request, $agency)->render();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function adminUsersIndexHtml(User $admin, array $query = []): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/users', 'GET', $query);
        $request->setUserResolver(fn () => $admin);

        return app(UserManagementController::class)->index($request)->render();
    }

    protected function staffAccountingReconciliationIndexHtml(User $staff): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/accounting/reconciliation', 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffAccountingReconciliationController::class)->index($request)->render();
    }

    protected function assertLegacyAdminUsersRedirect(User $admin, string $uri): void
    {
        $response = $this->actingAs($admin)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($target, '/admin/dashboard/users')
                || str_contains($target, '/admin/dashboard/staff')
                || str_contains($target, '/admin/dashboard/agents')
                || str_contains($target, '/admin/dashboard/reports'),
            "Unexpected admin redirect target [{$target}] for {$uri}"
        );
    }

    protected function adminRolesPermissionsHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(AdminSectionController::class)->rolesPermissions()->render();
    }

    protected function adminGoLiveChecklistHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(AdminSectionController::class)->goLiveChecklist()->render();
    }

    protected function adminSystemHealthHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(SystemSafetyController::class)->systemHealth()->render();
    }

    protected function adminDeploymentChecklistHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(SystemSafetyController::class)->deploymentChecklist()->render();
    }

    protected function staffSupportTicketsIndexHtml(User $staff): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/support/tickets', 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffSupportTicketController::class)->index($request)->render();
    }

    protected function staffSupportTicketShowHtml(User $staff, SupportTicket $ticket): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/support/tickets/'.$ticket->id, 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffSupportTicketController::class)->show($request, $ticket)->render();
    }

    protected function adminSupportTicketsIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/support/tickets', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AdminSupportTicketController::class)->index($request)->render();
    }

    protected function adminSupportTicketShowHtml(User $admin, SupportTicket $ticket): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/support/tickets/'.$ticket->id, 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AdminSupportTicketController::class)->show($request, $ticket)->render();
    }

    protected function assertLegacyAdminSupportTicketShowRedirect(User $admin, SupportTicket $ticket): void
    {
        $response = $this->actingAs($admin)->get(route('admin.support.tickets.show', $ticket));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/support', $target);
    }

    protected function adminThemePaletteEditHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/settings/theme-palette', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(JetpkThemePaletteSettingsController::class)->edit($request)->render();
    }

    protected function assertLegacyThemePaletteRedirect(User $admin): void
    {
        $response = $this->actingAs($admin)->get(route('admin.settings.theme-palette.edit'));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertTrue(
            str_contains($target, '/admin/dashboard')
            || str_contains($target, 'theme')
            || str_contains($target, 'branding')
            || str_contains($target, 'settings'),
            'Expected theme-palette GET to redirect into Next settings. Got: '.$target
        );
    }

    /**
     * Portal list/show GETs may already be Next-migrated (302) or still Blade (200).
     */
    protected function assertPortalGetOkOrRedirect(User $user, string $uri, string $locationNeedle): void
    {
        $response = $this->actingAs($user)->get($uri);
        $this->assertContains(
            $response->getStatusCode(),
            [200, 301, 302],
            "Expected OK or redirect for {$uri}, got ".$response->getStatusCode()
        );
        if ($response->isRedirect()) {
            $this->assertStringContainsString(
                $locationNeedle,
                (string) $response->headers->get('Location'),
                "Unexpected redirect for {$uri}"
            );
        }
    }

    protected function assertLegacyStaffAccountingRedirect(User $staff, string $uri = '/staff/accounting/ledger'): void
    {
        $response = $this->actingAs($staff)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/staff/dashboard/accounting', $target);
    }

    protected function assertLegacyAccountingRedirect(User $admin, string $uri = '/admin/finance/dashboard'): void
    {
        $response = $this->actingAs($admin)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/accounting', $target);
    }

    protected function assertLegacyBookingShowRedirect(User $admin, Booking $booking): void
    {
        $response = $this->actingAs($admin)->get(route('admin.bookings.show', $booking));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/bookings', $target);
    }

    protected function staffBookingShowHtml(User $staff, Booking $booking): string
    {
        $this->actingAs($staff);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/staff/bookings/'.$booking->id, 'GET');
        $request->setUserResolver(fn () => $staff);

        return app(StaffBookingController::class)->show($booking)->render();
    }

    protected function assertLegacyStaffBookingShowRedirect(User $staff, Booking $booking): void
    {
        $response = $this->actingAs($staff)->get(route('staff.bookings.show', $booking));
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/staff/dashboard/bookings', $target);
    }

    protected function assertLegacyGroupTicketingRedirect(User $admin, string $uri): void
    {
        $response = $this->actingAs($admin)->get($uri);
        $response->assertRedirect();
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('/admin/dashboard/group-ticketing', $target);
    }

    protected function adminGroupTicketingIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(AdminGroupTicketingController::class)
            ->index(app(GroupInventoryFacetService::class))
            ->render();
    }

    protected function adminGroupTicketingTilesIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(AdminGroupTicketingController::class)
            ->tilesIndex(app(GroupHomepageTilePresenter::class))
            ->render();
    }

    protected function adminGroupTicketingCategoriesIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);

        return app(AdminGroupTicketingController::class)
            ->categoriesIndex(app(GroupInventoryFacetService::class))
            ->render();
    }
}
