<?php

use App\Http\Controllers\Customer\BookingCancellationController;
use App\Http\Controllers\Customer\CustomerBookingController;
use App\Http\Controllers\Customer\SavedTravelerController;
use App\Http\Controllers\Customer\SupportTicketController;
use App\Http\Controllers\Frontend\BookingCheckoutPromoController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->name('customer.')->group(function (): void {
    Route::middleware('platform.module:customer_portal')->group(function (): void {
        Route::get('/', [CustomerBookingController::class, 'dashboard'])->name('dashboard');
        Route::get('/bookings', [CustomerBookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/{booking}', [CustomerBookingController::class, 'show'])->name('bookings.show');
        Route::get('/documents/{bookingDocument}/download', [CustomerBookingController::class, 'downloadDocument'])->name('documents.download');
    });

    Route::post('/bookings/{booking}/payment-proof', [CustomerBookingController::class, 'submitPaymentProof'])
        ->middleware(['platform.module:payment_proofs', 'throttle:payment-proof-submit'])
        ->name('bookings.payment-proof');
    Route::post('/bookings/{booking}/promo/apply', [BookingCheckoutPromoController::class, 'apply'])
        ->middleware('throttle:promo-apply')
        ->name('bookings.promo.apply');
    Route::post('/bookings/{booking}/promo/remove', [BookingCheckoutPromoController::class, 'remove'])
        ->middleware('throttle:promo-apply')
        ->name('bookings.promo.remove');
    Route::post('/bookings/{booking}/cancellations', [BookingCancellationController::class, 'store'])->name('bookings.cancellations.store');

    Route::middleware('platform.module:saved_travelers')->group(function (): void {
        Route::get('/travelers', [SavedTravelerController::class, 'index'])->name('travelers.index');
        Route::get('/travelers/create', [SavedTravelerController::class, 'create'])->name('travelers.create');
        Route::post('/travelers', [SavedTravelerController::class, 'store'])->name('travelers.store');
        Route::get('/travelers/{traveler}/edit', [SavedTravelerController::class, 'edit'])->name('travelers.edit');
        Route::patch('/travelers/{traveler}', [SavedTravelerController::class, 'update'])->name('travelers.update');
        Route::delete('/travelers/{traveler}', [SavedTravelerController::class, 'destroy'])->name('travelers.destroy');
    });

    Route::middleware('platform.module:support_system')->group(function (): void {
        Route::get('/support', [SupportTicketController::class, 'supportHub'])->name('support.index');
        Route::get('/support/tickets', [SupportTicketController::class, 'index'])->name('support.tickets.index');
        Route::get('/support/tickets/create', [SupportTicketController::class, 'create'])->name('support.tickets.create');
        Route::post('/support/tickets', [SupportTicketController::class, 'store'])->name('support.tickets.store');
        Route::get('/support/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('support.tickets.show');
        Route::post('/support/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('support.tickets.reply');
        Route::patch('/support/tickets/{ticket}/close', [SupportTicketController::class, 'close'])->name('support.tickets.close');
    });
});
