<?php

use App\Http\Controllers\Api\Dashboard\DashboardAgentsController;
use App\Http\Controllers\Api\Dashboard\DashboardAgentApplicationsController;
use App\Http\Controllers\Api\Dashboard\DashboardAuditController;
use App\Http\Controllers\Api\Dashboard\DashboardBookingsController;
use App\Http\Controllers\Api\Dashboard\DashboardCmsController;
use App\Http\Controllers\Api\Dashboard\DashboardCommissionsController;
use App\Http\Controllers\Api\Dashboard\DashboardCommunicationsController;
use App\Http\Controllers\Api\Dashboard\DashboardCustomersController;
use App\Http\Controllers\Api\Dashboard\DashboardDepositsController;
use App\Http\Controllers\Api\Dashboard\DashboardMarkupsController;
use App\Http\Controllers\Api\Dashboard\DashboardOverviewController;
use App\Http\Controllers\Api\Dashboard\DashboardOpsController;
use App\Http\Controllers\Api\Dashboard\DashboardPaymentsController;
use App\Http\Controllers\Api\Dashboard\DashboardPermissionsController;
use App\Http\Controllers\Api\Dashboard\DashboardPnrOrdersController;
use App\Http\Controllers\Api\Dashboard\DashboardReportsController;
use App\Http\Controllers\Api\Dashboard\DashboardRolesController;
use App\Http\Controllers\Api\Dashboard\DashboardSearchController;
use App\Http\Controllers\Api\Dashboard\DashboardSessionController;
use App\Http\Controllers\Api\Dashboard\DashboardSettingsController;
use App\Http\Controllers\Api\Dashboard\DashboardSuppliersController;
use App\Http\Controllers\Api\Dashboard\DashboardSupportTicketsController;
use App\Http\Controllers\Api\Dashboard\DashboardSystemHealthController;
use App\Http\Controllers\Api\Dashboard\DashboardTicketsController;
use App\Http\Controllers\Api\Dashboard\DashboardUsersController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboard read-only API (GET only) — JETPK-DASH-11
|--------------------------------------------------------------------------
|
| Session-authenticated JSON endpoints for the Next.js dashboard.
| All routes are GET-only; mutations remain on portal controllers.
|
*/

Route::middleware(['throttle:120,1'])->group(function (): void {
    Route::get('/session', [DashboardSessionController::class, 'show'])
        ->name('session');

    Route::middleware('dashboard.permission:dashboard.view')->group(function (): void {
        Route::get('/overview', [DashboardOverviewController::class, 'show'])
            ->name('overview');
        Route::get('/search', [DashboardSearchController::class, 'index'])
            ->name('search');

        // JP-OPS-08 cross-portal ops surfaces (EVENT_POLLING)
        Route::get('/ops/inbox', [DashboardOpsController::class, 'inbox'])
            ->name('ops.inbox');
        Route::get('/ops/inbox/unread-summary', [DashboardOpsController::class, 'unreadSummary'])
            ->name('ops.inbox.unread');
        Route::post('/ops/inbox/read', [DashboardOpsController::class, 'markRead'])
            ->name('ops.inbox.read');
        Route::post('/ops/inbox/read-all', [DashboardOpsController::class, 'markAllRead'])
            ->name('ops.inbox.read-all');
        Route::get('/ops/events', [DashboardOpsController::class, 'events'])
            ->name('ops.events');
        Route::get('/ops/work-queue', [DashboardOpsController::class, 'workQueue'])
            ->name('ops.work-queue');
    });

    Route::middleware('dashboard.permission:bookings.view')->group(function (): void {
        Route::get('/bookings', [DashboardBookingsController::class, 'index'])
            ->name('bookings.index');
        Route::get('/bookings/{booking}', [DashboardBookingsController::class, 'show'])
            ->where('booking', '[^/]+')
            ->name('bookings.show');
    });

    Route::middleware('dashboard.permission:payments.view')->group(function (): void {
        Route::get('/payments', [DashboardPaymentsController::class, 'index'])
            ->name('payments.index');
        Route::get('/payments/{payment}', [DashboardPaymentsController::class, 'show'])
            ->where('payment', '[^/]+')
            ->name('payments.show');
    });

    Route::middleware('dashboard.permission:customers.view')->group(function (): void {
        Route::get('/customers', [DashboardCustomersController::class, 'index'])
            ->name('customers.index');
        Route::get('/customers/{customer}', [DashboardCustomersController::class, 'show'])
            ->where('customer', '[^/]+')
            ->name('customers.show');
    });

    Route::middleware('dashboard.permission:suppliers.view')->group(function (): void {
        Route::get('/suppliers', [DashboardSuppliersController::class, 'index'])
            ->name('suppliers.index');
        Route::get('/suppliers/{supplier}', [DashboardSuppliersController::class, 'show'])
            ->where('supplier', '[^/]+')
            ->name('suppliers.show');
    });

    Route::middleware('dashboard.permission:agents.view')->group(function (): void {
        Route::get('/agents', [DashboardAgentsController::class, 'index'])
            ->name('agents.index');
        Route::get('/agents/{agent}', [DashboardAgentsController::class, 'show'])
            ->where('agent', '[^/]+')
            ->name('agents.show');
        Route::get('/deposits', [DashboardDepositsController::class, 'index'])
            ->name('deposits.index');
        Route::get('/deposits/{deposit}', [DashboardDepositsController::class, 'show'])
            ->where('deposit', '[^/]+')
            ->name('deposits.show');
        Route::get('/agent-applications', [DashboardAgentApplicationsController::class, 'index'])
            ->name('agent-applications.index');
        Route::get('/commissions', [DashboardCommissionsController::class, 'index'])
            ->name('commissions.index');
    });

    Route::middleware('dashboard.permission:pnrs.view')->group(function (): void {
        Route::get('/pnrs', [DashboardPnrOrdersController::class, 'index'])
            ->name('pnrs.index');
        Route::get('/pnrs/{record}', [DashboardPnrOrdersController::class, 'show'])
            ->where('record', '[^/]+')
            ->name('pnrs.show');
    });

    Route::middleware('dashboard.permission:tickets.view')->group(function (): void {
        Route::get('/tickets', [DashboardTicketsController::class, 'index'])
            ->name('tickets.index');
        Route::get('/tickets/{ticket}', [DashboardTicketsController::class, 'show'])
            ->where('ticket', '[^/]+')
            ->name('tickets.show');
    });

    Route::middleware('dashboard.permission:support.view')->group(function (): void {
        Route::get('/support/tickets', [DashboardSupportTicketsController::class, 'index'])
            ->name('support.tickets.index');
        Route::get('/support/tickets/{ticket}', [DashboardSupportTicketsController::class, 'show'])
            ->where('ticket', '[^/]+')
            ->name('support.tickets.show');
    });

    Route::middleware('dashboard.permission:reports.view')->group(function (): void {
        Route::get('/reports/summary', [DashboardReportsController::class, 'summary'])
            ->name('reports.summary');
        Route::get('/reports/bookings', [DashboardReportsController::class, 'bookings'])
            ->name('reports.bookings');
        Route::get('/reports/payments', [DashboardReportsController::class, 'payments'])
            ->name('reports.payments');
        Route::get('/reports/suppliers', [DashboardReportsController::class, 'suppliers'])
            ->name('reports.suppliers');
        Route::get('/reports/agents', [DashboardReportsController::class, 'agents'])
            ->name('reports.agents');
    });

    Route::middleware('dashboard.permission:cms.view')->group(function (): void {
        Route::get('/cms/pages', [DashboardCmsController::class, 'index'])
            ->name('cms.pages.index');
        Route::get('/cms/pages/{page}', [DashboardCmsController::class, 'show'])
            ->where('page', '[^/]+')
            ->name('cms.pages.show');
        Route::get('/cms/pages/{page}/sections', [DashboardCmsController::class, 'sections'])
            ->where('page', '[^/]+')
            ->name('cms.pages.sections');
    });

    Route::middleware('dashboard.permission:users.view')->group(function (): void {
        Route::get('/users', [DashboardUsersController::class, 'index'])
            ->name('users.index');
        Route::get('/users/{user}', [DashboardUsersController::class, 'show'])
            ->where('user', '[^/]+')
            ->name('users.show');
    });

    Route::middleware('dashboard.permission:roles.view')->group(function (): void {
        Route::get('/roles', [DashboardRolesController::class, 'index'])
            ->name('roles.index');
        Route::get('/roles/{role}', [DashboardRolesController::class, 'show'])
            ->where('role', '[^/]+')
            ->name('roles.show');
        Route::get('/rbac/matrix', [DashboardPermissionsController::class, 'matrix'])
            ->name('rbac.matrix');
    });

    Route::middleware('dashboard.permission:permissions.view')->group(function (): void {
        Route::get('/permissions', [DashboardPermissionsController::class, 'index'])
            ->name('permissions.index');
        Route::get('/permissions/{permission}', [DashboardPermissionsController::class, 'show'])
            ->where('permission', '[^/]+')
            ->name('permissions.show');
    });

    Route::middleware('dashboard.permission:settings.view')->group(function (): void {
        Route::get('/settings', [DashboardSettingsController::class, 'index'])
            ->name('settings.index');
        Route::get('/settings/general', [DashboardSettingsController::class, 'general'])
            ->name('settings.general');
        Route::get('/settings/security', [DashboardSettingsController::class, 'security'])
            ->name('settings.security');
        Route::get('/settings/notifications', [DashboardSettingsController::class, 'notifications'])
            ->name('settings.notifications');
        Route::get('/settings/integrations', [DashboardSettingsController::class, 'integrations'])
            ->name('settings.integrations');
        Route::get('/communications/failures', [DashboardCommunicationsController::class, 'index'])
            ->name('communications.failures');
        Route::get('/markups', [DashboardMarkupsController::class, 'index'])
            ->name('markups.index');
        Route::get('/system/health', [DashboardSystemHealthController::class, 'show'])
            ->name('system.health');
        Route::get('/system/go-live', [DashboardSystemHealthController::class, 'goLive'])
            ->name('system.go-live');
    });

    Route::middleware('dashboard.permission:audit.view')->group(function (): void {
        Route::get('/audit', [DashboardAuditController::class, 'index'])
            ->name('audit.index');
        Route::get('/audit/{event}', [DashboardAuditController::class, 'show'])
            ->where('event', '[^/]+')
            ->name('audit.show');
    });
});
