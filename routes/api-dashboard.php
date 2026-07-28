<?php

use App\Http\Controllers\Api\Dashboard\DashboardAgentsController;
use App\Http\Controllers\Api\Dashboard\DashboardAuditController;
use App\Http\Controllers\Api\Dashboard\DashboardBookingsController;
use App\Http\Controllers\Api\Dashboard\DashboardCmsController;
use App\Http\Controllers\Api\Dashboard\DashboardCustomersController;
use App\Http\Controllers\Api\Dashboard\DashboardOverviewController;
use App\Http\Controllers\Api\Dashboard\DashboardPaymentsController;
use App\Http\Controllers\Api\Dashboard\DashboardPermissionsController;
use App\Http\Controllers\Api\Dashboard\DashboardPnrOrdersController;
use App\Http\Controllers\Api\Dashboard\DashboardReportsController;
use App\Http\Controllers\Api\Dashboard\DashboardRolesController;
use App\Http\Controllers\Api\Dashboard\DashboardSessionController;
use App\Http\Controllers\Api\Dashboard\DashboardSettingsController;
use App\Http\Controllers\Api\Dashboard\DashboardSuppliersController;
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
    });

    Route::middleware('dashboard.permission:audit.view')->group(function (): void {
        Route::get('/audit', [DashboardAuditController::class, 'index'])
            ->name('audit.index');
        Route::get('/audit/{event}', [DashboardAuditController::class, 'show'])
            ->where('event', '[^/]+')
            ->name('audit.show');
    });
});
