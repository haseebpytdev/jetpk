<?php

namespace App\Http\Controllers\Staff;

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
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'cancellation_type' => ['required', Rule::enum(BookingCancellationType::class)],
        ]);

        $this->service->requestCancellation($booking, $request->user(), [
            ...$validated,
            'request_source' => 'staff',
        ]);

        return back()->with('status', 'cancellation-requested');
    }

    public function approve(Request $request, BookingCancellationRequest $cancellationRequest): RedirectResponse
    {
        Gate::authorize('approve', $cancellationRequest);
        $this->service->approveCancellation($cancellationRequest, $request->user());

        return back()->with('status', 'cancellation-approved');
    }

    public function reject(Request $request, BookingCancellationRequest $cancellationRequest): RedirectResponse
    {
        Gate::authorize('reject', $cancellationRequest);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->service->rejectCancellation($cancellationRequest, $request->user(), $validated['reason']);

        return back()->with('status', 'cancellation-rejected');
    }

    public function process(Request $request, BookingCancellationRequest $cancellationRequest): RedirectResponse
    {
        Gate::authorize('process', $cancellationRequest);
        $processed = $this->service->processCancellation($cancellationRequest, $request->user(), true, 'staff');
        $message = 'cancellation-processed';
        if (($processed->meta['manual_warning'] ?? null) !== null) {
            $message = 'cancellation-processed-manual-review';
        }

        return back()
            ->with('status', $message)
            ->with('cancellation_warning', $processed->meta['manual_warning'] ?? null);
    }
}
