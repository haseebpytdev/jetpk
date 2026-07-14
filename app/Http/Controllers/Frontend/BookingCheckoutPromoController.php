<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Customer\GuestBookingAccessService;
use App\Services\Promos\PromoCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class BookingCheckoutPromoController extends Controller
{
    public function __construct(
        protected PromoCodeService $promoCodeService,
        protected GuestBookingAccessService $guestAccessService,
    ) {}

    public function apply(Request $request, Booking $booking, ?string $token = null): RedirectResponse
    {
        $this->authorizeBookingPromo($request, $booking, $token);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->promoCodeService->applyToBooking(
            $validated['code'],
            $booking,
            $request->user(),
            $request->session()->getId(),
        );

        if (! $result->success) {
            return back()->withErrors(['promo_code' => $result->errors[0] ?? 'Unable to apply promo code.']);
        }

        return back()->with('promo_status', $result->message);
    }

    public function remove(Request $request, Booking $booking, ?string $token = null): RedirectResponse
    {
        $this->authorizeBookingPromo($request, $booking, $token);

        try {
            $this->promoCodeService->removeFromBooking($booking, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['promo_code' => $e->getMessage()]);
        }

        return back()->with('promo_status', 'Promo code removed.');
    }

    protected function authorizeBookingPromo(Request $request, Booking $booking, ?string $routeToken = null): void
    {
        $user = $request->user();
        if ($user !== null) {
            Gate::authorize('view', $booking);

            return;
        }

        $token = (string) ($routeToken ?? '');
        if ($token !== '' && $this->guestAccessService->validateToken($booking, $token)) {
            return;
        }

        abort(403);
    }
}
