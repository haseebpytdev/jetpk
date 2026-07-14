<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\BookingCancellationType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Bookings\BookingCancellationService;
use App\Services\Customer\GuestBookingAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestBookingCancellationController extends Controller
{
    public function __construct(
        protected GuestBookingAccessService $guestAccessService,
        protected BookingCancellationService $service,
    ) {}

    public function store(Request $request, Booking $booking, string $token): RedirectResponse
    {
        if (! $this->guestAccessService->validateToken($booking, $token)) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'cancellation_type' => ['required', Rule::enum(BookingCancellationType::class)],
        ]);

        $this->service->requestCancellation($booking, null, [
            ...$validated,
            'request_source' => 'guest',
            'meta' => ['guest_token_used' => true],
        ]);

        return back()->with('status', 'cancellation-requested');
    }
}
