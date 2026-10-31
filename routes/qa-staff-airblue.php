<?php

use App\Http\Controllers\Staff\QaStaffAirBlueSearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('staff/qa')->name('staff.qa.')->group(function (): void {
    Route::get('/airblue-search', [QaStaffAirBlueSearchController::class, 'show'])->name('airblue-search');
    Route::post('/airblue-search/activate', [QaStaffAirBlueSearchController::class, 'activate'])->name('airblue-search.activate');
    Route::post('/airblue-search/deactivate', [QaStaffAirBlueSearchController::class, 'deactivate'])->name('airblue-search.deactivate');
});
