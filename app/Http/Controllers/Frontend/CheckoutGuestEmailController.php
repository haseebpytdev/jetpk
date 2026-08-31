<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingDraftService;
use App\Support\Auth\CheckoutGuestEmailMatcher;
use App\Support\PublicBooking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Context-bound guest checkout email recognition (not a public email-exists API).
 */
class CheckoutGuestEmailController extends Controller
{
    public function show(
        Request $request,
        BookingDraftService $draftService,
        CheckoutGuestEmailMatcher $matcher,
    ): JsonResponse {
        if (Auth::check()) {
            return response()->json(['match' => false]);
        }

        if (! $this->hasActiveCheckoutContext($request, $draftService)) {
            return response()->json(['message' => __('Checkout session required.')], 403);
        }

        $email = strtolower(trim((string) $request->input('email', '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['match' => false]);
        }

        return response()->json([
            'match' => $matcher->customerMatch($email),
        ]);
    }

    protected function hasActiveCheckoutContext(Request $request, BookingDraftService $draftService): bool
    {
        $draft = $draftService->current();
        $hasDraft = trim((string) ($draft['offer_id'] ?? $draft['flight_id'] ?? '')) !== ''
            || trim((string) ($draft['search_id'] ?? '')) !== '';
        $hasBooking = $request->session()->get(PublicBooking::SESSION_BOOKING_ID) !== null;

        return $hasDraft || $hasBooking;
    }
}
