<?php

namespace App\Support\Booking;

use App\Models\Booking;
use App\Support\PublicBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

/**
 * Converts standard-booking checkout redirects into JSON for Next.js (additive).
 */
final class StandardBookingCheckoutJsonResponder
{
    public function __construct(
        private readonly StandardBookingJsonPresenter $presenter,
    ) {}

    public function maybeJson(Request $request, RedirectResponse $redirect): RedirectResponse|JsonResponse
    {
        if (! $this->wantsJson($request)) {
            return $redirect;
        }

        return $this->toJson($request, $redirect);
    }

    public function wantsJson(Request $request): bool
    {
        return $request->wantsJson() || $request->query('format') === 'json';
    }

    public function toJson(Request $request, RedirectResponse $redirect): JsonResponse
    {
        $path = (string) (parse_url($redirect->getTargetUrl(), PHP_URL_PATH) ?? '');
        $session = $redirect->getSession();
        $errors = $session?->get('errors');
        $errorBag = $errors instanceof ViewErrorBag ? $errors : null;
        $bookingMessage = $errorBag?->getBag('default')->first('booking')
            ?? $errorBag?->getBag('default')->first('flight_id')
            ?? $errorBag?->getBag('default')->first('payment');

        if (str_contains($path, '/booking/confirmation')) {
            $booking = $this->sessionBooking($request);

            return response()->json(
                $this->presenter->presentReviewSubmitSuccess($booking, $request),
            );
        }

        if (str_contains($path, '/booking/review')) {
            $booking = $this->sessionBooking($request);
            $fareChangePending = (bool) ($session?->get('show_offer_refresh_modal') ?? false);

            if ($fareChangePending && $booking !== null) {
                return response()->json(
                    $this->presenter->presentFareChangeRequired($booking, $errorBag),
                    409,
                );
            }

            return response()->json(
                $this->presenter->presentReviewBlocked(
                    $booking,
                    (string) ($bookingMessage ?? __('Unable to continue review.')),
                    $path,
                ),
                422,
            );
        }

        if (str_contains($path, '/booking/passengers')) {
            return response()->json(
                $this->presenter->presentError(
                    'incomplete_passengers',
                    (string) ($bookingMessage ?? __('Passenger details are incomplete.')),
                    $path,
                ),
                422,
            );
        }

        if (str_contains($path, '/flights/results') || str_contains($path, '/flights/search')) {
            $status = str_contains((string) ($bookingMessage ?? ''), 'no longer available') ? 'offer_unavailable' : 'redirect';

            return response()->json(
                $this->presenter->presentError(
                    $status,
                    (string) ($bookingMessage ?? __('This fare is no longer available. Please choose another flight.')),
                    $path,
                ),
                $status === 'offer_unavailable' ? 410 : 422,
            );
        }

        return response()->json(
            $this->presenter->presentError('redirect', (string) ($bookingMessage ?? __('Checkout could not continue.')), $path),
            422,
        );
    }

    private function sessionBooking(Request $request): ?Booking
    {
        $bookingId = $request->session()->get(PublicBooking::SESSION_BOOKING_ID);
        if ($bookingId === null) {
            return null;
        }

        return Booking::query()->find($bookingId);
    }
}
