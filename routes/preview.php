<?php

use App\Http\Controllers\Preview\ClientPreviewController;
use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'preview.client'])
    ->prefix('{clientSlug}')
    ->where(['clientSlug' => ReservedClientPreviewSlugs::routeParameterConstraint()])
    ->name('client.preview.')
    ->group(function (): void {
        Route::get('/', [ClientPreviewController::class, 'clientRoot'])->name('root');

        if (! config('client_route_parity.enabled', true)) {
            Route::get('/home', [ClientPreviewController::class, 'home'])->name('home');
            Route::get('/login', [ClientPreviewController::class, 'login'])->name('login');
            Route::get('/admin', [ClientPreviewController::class, 'admin'])->name('admin');
            Route::get('/staff', [ClientPreviewController::class, 'staff'])->name('staff');
            Route::get('/agent', [ClientPreviewController::class, 'agent'])->name('agent');
        }
    });
