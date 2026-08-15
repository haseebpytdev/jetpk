<?php

namespace Tests\Support;

use App\Http\Controllers\Admin\AccountingLedgerController;
use App\Http\Controllers\Admin\AccountingReconciliationController;
use App\Http\Controllers\Admin\AgentApplicationController;
use App\Http\Controllers\Admin\AdminSectionController;
use App\Http\Controllers\Admin\AdminSettingsHubController;
use App\Http\Controllers\Admin\AgentDepositController;
use App\Http\Controllers\Admin\BookingManagementController;
use App\Http\Controllers\Admin\ClientPageSettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinanceAdjustmentController;
use App\Http\Controllers\Admin\FinanceDashboardController;
use App\Http\Controllers\Admin\FinanceStatementController;
use App\Http\Controllers\Admin\SupplierConnectionController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Models\Agency;
use App\Models\AgentApplication;
use App\Models\Booking;
use App\Models\SupplierConnection;
use App\Models\User;
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

    protected function adminFinanceAdjustmentsIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/adjustments', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceAdjustmentController::class)->index($request)->render();
    }

    protected function adminFinanceAdjustmentsCreateHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/finance/adjustments/create', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(FinanceAdjustmentController::class)->create($request)->render();
    }

    protected function adminAccountingLedgerIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/accounting/ledger', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AccountingLedgerController::class)->index($request)->render();
    }

    protected function adminAccountingReconciliationIndexHtml(User $admin): string
    {
        $this->actingAs($admin);
        view()->share('errors', new ViewErrorBag);
        $request = Request::create('/admin/accounting/reconciliation', 'GET');
        $request->setUserResolver(fn () => $admin);

        return app(AccountingReconciliationController::class)->index($request)->render();
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
}
