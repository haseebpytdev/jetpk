<?php

use App\Http\Controllers\Admin\ClientPageSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::prefix('page-settings')->name('page-settings.')->group(function (): void {
        Route::get('/', [ClientPageSettingsController::class, 'index'])->name('index');
        Route::get('/palette', [ClientPageSettingsController::class, 'palette'])->name('palette');
        Route::post('/palette/generate', [ClientPageSettingsController::class, 'generatePalette'])->name('palette.generate');
        Route::post('/palette/apply', [ClientPageSettingsController::class, 'applyPalette'])->name('palette.apply');
        Route::get('/{pageKey}', [ClientPageSettingsController::class, 'edit'])->name('edit');
        Route::patch('/{pageKey}', [ClientPageSettingsController::class, 'update'])->name('update');
        Route::post('/home/refresh-fares', [ClientPageSettingsController::class, 'refreshHomeRouteFares'])->name('home.refresh-fares');
        Route::post('/{pageKey}/publish', [ClientPageSettingsController::class, 'publish'])->name('publish');
        Route::post('/{pageKey}/preview', [ClientPageSettingsController::class, 'beginPreview'])->name('preview.begin');
        Route::post('/{pageKey}/assets', [ClientPageSettingsController::class, 'storeAsset'])->name('assets.store');
        Route::delete('/{pageKey}/assets/{asset}', [ClientPageSettingsController::class, 'destroyAsset'])->name('assets.destroy');
    });
});
