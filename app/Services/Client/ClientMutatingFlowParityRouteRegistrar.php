<?php

namespace App\Services\Client;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\LoginOtpController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Frontend\BookingController;
use App\Support\Client\ReservedClientPreviewSlugs;
use Illuminate\Support\Facades\Route;

/**
 * Client-prefixed mutating auth/booking routes for JetPK preview (8F).
 *
 * GET/HEAD parity is handled by ClientPrefixedRouteRegistrar; POST forms need
 * matching handlers under /{clientSlug}/… so client_url() actions stay prefixed.
 */
final class ClientMutatingFlowParityRouteRegistrar
{
    /**
     * @return array{registered: int, skipped: int}
     */
    public function register(): array
    {
        if (! config('client_route_parity.enabled', true)) {
            return ['registered' => 0, 'skipped' => 0];
        }

        $registered = 0;
        $skipped = 0;
        $slug = ['clientSlug' => ReservedClientPreviewSlugs::routeParameterConstraint()];
        $preview = ['web', 'preview.client', 'preview.client.persist'];

        $guest = Route::middleware(array_merge($preview, ['guest']))
            ->prefix('{clientSlug}')
            ->where($slug)
            ->name('client.parity.');

        $registered += $this->registerRoute(
            $guest->post('login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:6,1'),
            'login.store',
            $skipped,
        );

        $registered += $this->registerRoute(
            $guest->post('login/otp', [LoginOtpController::class, 'store'])
                ->middleware('throttle:6,1'),
            'login.otp.verify',
            $skipped,
        );

        $registered += $this->registerRoute(
            $guest->post('login/otp/resend', [LoginOtpController::class, 'resend'])
                ->middleware('throttle:3,1'),
            'login.otp.resend',
            $skipped,
        );

        $registered += $this->registerRoute(
            Route::middleware(array_merge($preview, ['guest', 'platform.module:customer_registration']))
                ->prefix('{clientSlug}')
                ->where($slug)
                ->name('client.parity.')
                ->post('register', [RegisteredUserController::class, 'store'])
                ->middleware('throttle:6,1'),
            'register.store',
            $skipped,
        );

        $registered += $this->registerRoute(
            $guest->post('forgot-password', [PasswordResetLinkController::class, 'store']),
            'password.email',
            $skipped,
        );

        $registered += $this->registerRoute(
            $guest->post('reset-password', [NewPasswordController::class, 'store']),
            'password.store',
            $skipped,
        );

        $booking = Route::middleware(array_merge($preview, ['platform.module:customer_checkout']))
            ->prefix('{clientSlug}')
            ->where($slug)
            ->name('client.parity.');

        $registered += $this->registerRoute(
            $booking->post('booking/passengers', [BookingController::class, 'passengers'])
                ->middleware('throttle:public-booking-submit'),
            'booking.passengers.store',
            $skipped,
        );

        $registered += $this->registerRoute(
            $booking->post('booking/review', [BookingController::class, 'review'])
                ->middleware('throttle:public-booking-submit'),
            'booking.review.store',
            $skipped,
        );

        return ['registered' => $registered, 'skipped' => $skipped];
    }

    private function registerRoute(?object $route, string $paritySuffix, int &$skipped): int
    {
        if ($route === null) {
            $skipped++;

            return 0;
        }

        $parityName = 'client.parity.'.$paritySuffix;
        if (Route::has($parityName)) {
            $skipped++;

            return 0;
        }

        $route->name($paritySuffix);
        $route->setAction(array_merge($route->getAction(), [
            'client_parity_classification' => str_contains($paritySuffix, 'booking.') ? 'booking_flow' : 'auth_page',
            'client_parity_mutating_flow' => true,
        ]));

        return 1;
    }
}
