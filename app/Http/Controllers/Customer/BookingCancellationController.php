<?php

namespace App\Http\Controllers\Customer;

use App\Enums\BookingCancellationType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingCancellationController extends Controller
{
    public function __construct(
        protected BookingCancellationService $service,
    ) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('request', [BookingCancellationRequest::class, $booking]);
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'cancellation_type' => ['required', Rule::enum(BookingCancellationType::class)],
        ]);
        $this->service->requestCancellation($booking, $request->user(), [
            ...$validated,
            'request_source' => 'customer',
        ]);

        return back()->with('status', 'cancellation-requested');
    }
}
