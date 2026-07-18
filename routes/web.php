<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ClientUiPreviewController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\Frontend\AgentRegistrationController;
use App\Http\Controllers\Frontend\AirportSearchController;
use App\Http\Controllers\Frontend\BookingCheckoutPromoController;
use App\Http\Controllers\Frontend\BookingController;
use App\Http\Controllers\Frontend\CmsPageController;
use App\Http\Controllers\Frontend\FlightController;
use App\Http\Controllers\Frontend\GroupTicketingBookingController;
use App\Http\Controllers\Frontend\GroupTicketingSearchController;
use App\Http\Controllers\Frontend\GuestBookingCancellationController;
use App\Http\Controllers\Frontend\GuestBookingLookupController;
use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\RequestDemoController;
use App\Http\Controllers\Frontend\SupportController;
use App\Http\Controllers\Payments\AbhiPayPaymentController;
use App\Http\Controllers\ProfileController;
use App\Support\Ui\UiVersionResolver;
use Illuminate\Support\Facades\Route;

Route::get('/ui/v1', [ClientUiPreviewController::class, 'activateV1'])->name('client-ui.preview.v1');
Route::get('/ui/v2', [ClientUiPreviewController::class, 'activateV2'])
    ->middleware('client.ui.preview.protect')
    ->name('client-ui.preview.v2');
Route::get('/ui/reset', [ClientUiPreviewController::class, 'reset'])->name('client-ui.preview.reset');

Route::redirect('/devcp', '/dev/cp');
Route::get('/devcp/{path}', static function (string $path) {
    return redirect('/dev/cp/'.ltrim($path, '/'));
})->where('path', '.*')->name('devcp.alias');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/request-demo', RequestDemoController::class)->name('request-demo');
Route::middleware('platform.module:support_system')->group(function (): void {
    Route::get('/support', [SupportController::class, 'support'])->name('support');
    Route::post('/support', [SupportController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('support.store');
    Route::get('/support/submitted', [SupportController::class, 'submitted'])->name('support.submitted');
});
Route::get('/about-us', [SupportController::class, 'about'])->name('about');
Route::get('/pages/{slug}', [CmsPageController::class, 'show'])->name('pages.show');
Route::permanentRedirect('/contact', '/about-us');
Route::middleware('platform.module:agent_applications')->group(function (): void {
    Route::get('/agent/register', [AgentRegistrationController::class, 'landing'])->name('agent.register');
    Route::get('/agent/register/apply', [AgentRegistrationController::class, 'create'])->name('agent.register.form');
    Route::post('/agent/register/validate-field', [AgentRegistrationController::class, 'validateField'])
        ->middleware('throttle:register-validate-field')
        ->name('agent.register.validate-field');
    Route::post('/agent/register', [AgentRegistrationController::class, 'store'])->middleware('throttle:6,1')->name('agent.register.store');
    Route::get('/agent/register/submitted', [AgentRegistrationController::class, 'submitted'])->name('agent.register.submitted');
});
Route::redirect('/agent-network', '/agent/register')->name('agent-network');
Route::redirect('/register/customer', '/register');
Route::redirect('/register/agent', '/agent/register/apply');
Route::middleware(['guest', 'platform.module:customer_registration'])->group(function (): void {
    Route::post('/register/customer/validate-field', [RegisteredUserController::class, 'validateField'])
        ->middleware('throttle:register-validate-field')
        ->name('register.customer.validate-field');
});

Route::redirect('/password/forgot', '/forgot-password');
Route::redirect('/booking-lookup', '/lookup-booking');
Route::redirect('/flights', '/');
Route::redirect('/flights/search', '/')->name('flights.search');
Route::middleware('platform.module:public_flight_search')->group(function (): void {
    Route::get('/flights/results', [FlightController::class, 'results'])->name('flights.results');
    Route::get('/flights/results/search', [FlightController::class, 'resultsSearchData'])->middleware('throttle:public-flight-results-search')->name('flights.results.search');
    Route::get('/flights/results/data', [FlightController::class, 'resultsData'])->middleware('throttle:public-flight-results-data')->name('flights.results.data');
    Route::get('/flights/results/nearby-dates', [FlightController::class, 'resultsNearbyDates'])->middleware('throttle:public-flight-results-data')->name('flights.results.nearby-dates');
    Route::post('/flights/results/revalidate-offer', [FlightController::class, 'revalidateSelectedOffer'])->middleware('throttle:public-flight-results-data')->name('flights.results.revalidate-offer');
    Route::get('/flights/return-options', [FlightController::class, 'returnOptions'])->name('flights.return-options');
    Route::get('/flights/return-options/data', [FlightController::class, 'returnOptionsData'])->middleware('throttle:public-flight-results-data')->name('flights.return-options.data');
    Route::post('/flights/select-return-combo', [FlightController::class, 'selectReturnCombo'])->name('flights.select-return-combo');
    Route::post('/flights/multicity/inquiry', [FlightController::class, 'storeMulticityInquiry'])->middleware('throttle:public-flight-results-data')->name('flights.multicity.inquiry');
    Route::get('/flights/results/offer', [FlightController::class, 'resultsOfferDetails'])->name('flights.results.offer');
    Route::get('/flights/details/{id}', [FlightController::class, 'details'])->name('flights.details');
});
Route::get('/airports/search', AirportSearchController::class)->middleware('throttle:60,1')->name('airports.search');

Route::middleware('platform.module:public_umrah_groups')->group(function (): void {
    Route::get('/groups/search', [GroupTicketingSearchController::class, 'index'])->name('group-ticketing.search');
    Route::get('/groups/search/results', [GroupTicketingSearchController::class, 'results'])->name('group-ticketing.search.results');
    Route::get('/groups/facets', [GroupTicketingSearchController::class, 'facets'])->name('group-ticketing.facets');
    Route::get('/groups/package/{inventory}', [GroupTicketingSearchController::class, 'show'])->name('group-ticketing.show');
    Route::middleware('auth')->group(function (): void {
        Route::get('/groups/{inventory}/passengers', [GroupTicketingBookingController::class, 'passengers'])->name('group-ticketing.booking.passengers');
        Route::post('/groups/{inventory}/passengers', [GroupTicketingBookingController::class, 'storePassengers'])->name('group-ticketing.booking.passengers.store');
        Route::get('/groups/booking/{groupBooking}/review', [GroupTicketingBookingController::class, 'review'])->name('group-ticketing.booking.review');
        Route::post('/groups/booking/{groupBooking}/review', [GroupTicketingBookingController::class, 'confirmReview'])->name('group-ticketing.booking.review.confirm');
        Route::get('/groups/booking/{groupBooking}/payment', [GroupTicketingBookingController::class, 'payment'])->name('group-ticketing.booking.payment');
        Route::post('/groups/booking/{groupBooking}/payment', [GroupTicketingBookingController::class, 'submitPayment'])->name('group-ticketing.booking.payment.submit');
        Route::get('/groups/booking/{groupBooking}/confirmation', [GroupTicketingBookingController::class, 'confirmation'])->name('group-ticketing.booking.confirmation');
    });
    Route::get('/umrah-groups', fn () => redirect()->route('group-ticketing.search'))->name('umrah-groups.index');
    Route::get('/umrah-groups/{package}', fn (string $package) => redirect()->route('group-ticketing.show', $package))->name('umrah-groups.show');
});

Route::middleware('platform.module:customer_checkout')->group(function (): void {
    Route::match(['get', 'post'], '/booking/passengers', [BookingController::class, 'passengers'])->middleware('throttle:public-booking-submit')->name('booking.passengers');
    Route::match(['get', 'post'], '/booking/review', [BookingController::class, 'review'])->middleware('throttle:public-booking-submit')->name('booking.review');
    Route::post('/booking/{booking}/accept-updated-fare', [BookingController::class, 'acceptUpdatedFare'])->middleware('throttle:public-booking-submit')->name('booking.accept-updated-fare');
    Route::post('/booking/{booking}/decline-updated-fare', [BookingController::class, 'declineUpdatedFare'])->middleware('throttle:public-booking-submit')->name('booking.decline-updated-fare');
});
Route::get('/booking/confirmation', [BookingController::class, 'confirmation'])->name('booking.confirmation');
Route::middleware('platform.module:customer_booking_lookup')->group(function (): void {
    Route::get('/lookup-booking', [GuestBookingLookupController::class, 'showLookupForm'])->name('booking.lookup');
    Route::post('/lookup-booking', [GuestBookingLookupController::class, 'lookup'])->middleware('throttle:lookup-booking')->name('lookup-booking.submit');
    Route::get('/guest/bookings/{booking}/access/{token}', [GuestBookingLookupController::class, 'showGuestBooking'])->name('guest.bookings.show');
    Route::get('/guest/documents/{bookingDocument}/download', [GuestBookingLookupController::class, 'downloadGuestDocument'])->name('guest.documents.download');
});
Route::post('/guest/bookings/{booking}/access/{token}/payment-proof', [GuestBookingLookupController::class, 'submitGuestPaymentProof'])
    ->middleware(['platform.module:payment_proofs', 'throttle:payment-proof-submit'])
    ->name('guest.bookings.payment-proof');
Route::post('/guest/bookings/{booking}/access/{token}/promo/apply', [BookingCheckoutPromoController::class, 'apply'])
    ->middleware('throttle:promo-apply')
    ->name('guest.bookings.promo.apply');
Route::post('/guest/bookings/{booking}/access/{token}/promo/remove', [BookingCheckoutPromoController::class, 'remove'])
    ->middleware('throttle:promo-apply')
    ->name('guest.bookings.promo.remove');
Route::post('/guest/bookings/{booking}/access/{token}/abhipay/start', [AbhiPayPaymentController::class, 'start'])
    ->middleware('throttle:abhipay-payment-start')
    ->name('guest.bookings.abhipay.start');
Route::post('/payments/abhipay/start/{booking}', [AbhiPayPaymentController::class, 'start'])
    ->middleware(['auth', 'throttle:abhipay-payment-start'])
    ->name('payments.abhipay.start');
Route::any('/payments/abhipay/callback', [AbhiPayPaymentController::class, 'callback'])
    ->middleware('throttle:abhipay-payment-callback')
    ->name('payments.abhipay.callback');
Route::get('/payment/success', [AbhiPayPaymentController::class, 'success'])->name('payments.success');
Route::get('/payment/cancel', [AbhiPayPaymentController::class, 'cancel'])->name('payments.cancel');
Route::get('/payment/decline', [AbhiPayPaymentController::class, 'decline'])->name('payments.decline');
Route::post('/guest/bookings/{booking}/access/{token}/cancellations', [GuestBookingCancellationController::class, 'store'])->middleware('throttle:guest-token')->name('guest.bookings.cancellations.store');

Route::get('/dashboard', DashboardRedirectController::class)->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->get('/account/legacy', function () {
    return response()->view('errors.403', [
        'message' => 'This legacy account type is no longer supported. Contact your platform administrator to migrate your account.',
    ], 403);
})->name('account.legacy');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

if (app()->environment('testing')) {
    Route::get('/_test/ui-version', static function () {
        $resolver = app(UiVersionResolver::class);
        $resolver->resolve();

        return response()->json([
            'channel' => $resolver->channel(),
            'version' => $resolver->effectiveVersion(),
            'preview' => $resolver->previewVersion(),
            'preview_active' => $resolver->isPreviewActive(),
            'resolved_view' => $resolver->resolveViewName('frontend.home'),
        ]);
    })->name('test.ui-version');

    Route::get('/_test/error/{code}', static function (string $code) {
        $status = (int) $code;
        if ($status === 500) {
            throw new RuntimeException('JetPK test error 500');
        }

        abort($status);
    })->where('code', '403|404|419|429|500|503');
}
