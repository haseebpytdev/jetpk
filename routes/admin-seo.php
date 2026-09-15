<?php

use App\Http\Controllers\Admin\SeoManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::prefix('seo')->name('seo.')->group(function (): void {
        Route::get('/', [SeoManagementController::class, 'overview'])->name('overview');
        Route::get('/pages', [SeoManagementController::class, 'pagesIndex'])->name('pages.index');
        Route::get('/pages/{sourceType}/{sourceId}', [SeoManagementController::class, 'pagesEdit'])->name('pages.edit');
        Route::patch('/pages/{sourceType}/{sourceId}', [SeoManagementController::class, 'pagesUpdate'])->name('pages.update');
        Route::post('/pages/{sourceType}/{sourceId}/publish', [SeoManagementController::class, 'pagesPublish'])->name('pages.publish');
        Route::get('/global', [SeoManagementController::class, 'globalSettings'])->name('global');
        Route::patch('/global', [SeoManagementController::class, 'globalUpdate'])->name('global.update');
        Route::post('/global/publish', [SeoManagementController::class, 'globalPublish'])->name('global.publish');
        Route::get('/social', [SeoManagementController::class, 'social'])->name('social');
        Route::patch('/social', [SeoManagementController::class, 'socialUpdate'])->name('social.update');
        Route::get('/schema', [SeoManagementController::class, 'schema'])->name('schema');
        Route::get('/sitemap', [SeoManagementController::class, 'sitemap'])->name('sitemap');
        Route::get('/verification', [SeoManagementController::class, 'verification'])->name('verification');
        Route::patch('/verification', [SeoManagementController::class, 'verificationUpdate'])->name('verification.update');
        Route::post('/verification/publish', [SeoManagementController::class, 'verificationPublish'])->name('verification.publish');
        Route::get('/audit', [SeoManagementController::class, 'audit'])->name('audit');
    });
});
