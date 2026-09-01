<?php

use App\Http\Controllers\Admin\ClientPageSettingsController;
use App\Http\Controllers\BackOffice\BackOfficeLegacyViewRedirectController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/visa-module', [\App\Http\Controllers\Admin\VisaModuleSettingsController::class, 'show'])
        ->name('visa-module.show');

    Route::prefix('page-settings')->name('page-settings.')->group(function (): void {
        Route::get('/', [BackOfficeLegacyViewRedirectController::class, 'adminPageSettingsIndex'])->name('index');
        Route::get('/catalog', [ClientPageSettingsController::class, 'catalog'])->name('catalog');
        Route::get('/palette', [BackOfficeLegacyViewRedirectController::class, 'adminPageSettingsIndex'])->name('palette');
        Route::post('/palette/generate', [ClientPageSettingsController::class, 'generatePalette'])->name('palette.generate');
        Route::post('/palette/apply', [ClientPageSettingsController::class, 'applyPalette'])->name('palette.apply');
        Route::get('/custom-pages', [BackOfficeLegacyViewRedirectController::class, 'adminPageSettingsIndex'])->name('custom-pages.index');
        Route::get('/custom-pages/create', [BackOfficeLegacyViewRedirectController::class, 'adminPageSettingsIndex'])->name('custom-pages.create');
        Route::post('/custom-pages', [\App\Http\Controllers\Admin\ClientCustomPageController::class, 'store'])->name('custom-pages.store');
        Route::post('/home/refresh-fares', [ClientPageSettingsController::class, 'refreshHomeRouteFares'])->name('home.refresh-fares');
        Route::get('/{pageKey}', [ClientPageSettingsController::class, 'edit'])->name('edit');
        Route::patch('/{pageKey}', [ClientPageSettingsController::class, 'update'])->name('update');
        Route::post('/{pageKey}/publish', [ClientPageSettingsController::class, 'publish'])->name('publish');
        Route::post('/{pageKey}/unpublish', [ClientPageSettingsController::class, 'unpublish'])->name('unpublish');
        Route::post('/{pageKey}/duplicate', [ClientPageSettingsController::class, 'duplicate'])->name('duplicate');
        Route::post('/{pageKey}/save-as-default', [ClientPageSettingsController::class, 'saveCurrentAsDefault'])->name('save-as-default');
        Route::post('/{pageKey}/reset/preview', [ClientPageSettingsController::class, 'previewReset'])->name('reset.preview');
        Route::post('/{pageKey}/reset/draft', [ClientPageSettingsController::class, 'resetDraft'])->name('reset.draft');
        Route::post('/{pageKey}/reset/publish', [ClientPageSettingsController::class, 'resetAndPublish'])->name('reset.publish');
        Route::post('/{pageKey}/preview', [ClientPageSettingsController::class, 'beginPreview'])->name('preview.begin');
        Route::post('/{pageKey}/assets', [ClientPageSettingsController::class, 'storeAsset'])->name('assets.store');
        Route::post('/{pageKey}/assets/attach', [ClientPageSettingsController::class, 'attachAsset'])->name('assets.attach');
        Route::delete('/{pageKey}/assets/{asset}', [ClientPageSettingsController::class, 'destroyAsset'])->name('assets.destroy');
    });
});
