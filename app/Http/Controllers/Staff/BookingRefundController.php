<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Services\Payments\BookingRefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BookingRefundController extends Controller
{
    public function __construct(
        protected BookingRefundService $service,
    ) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [BookingRefund::class, $booking]);
        $validated = $request->validate([
            'booking_payment_id' => ['nullable', 'integer', 'exists:booking_payments,id'],
            'cancellation_request_id' => ['nullable', 'integer', 'exists:booking_cancellation_requests,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'currency' => ['nullable', 'string', 'max:12'],
            'method' => ['required', Rule::in(['bank_transfer', 'cash', 'card_manual', 'easypaisa', 'jazzcash', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            $this->service->createRefund($booking, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', 'refund-created');
    }

    public function approve(Request $request, BookingRefund $bookingRefund): RedirectResponse
    {
        Gate::authorize('approve', $bookingRefund);
        $this->service->approveRefund($bookingRefund, $request->user());

        return back()->with('status', 'refund-approved');
    }

    public function markPaid(Request $request, BookingRefund $bookingRefund): RedirectResponse
    {
        Gate::authorize('markPaid', $bookingRefund);
        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        try {
            $this->service->markRefundPaid($bookingRefund, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        return back()->with('status', 'refund-paid');
    }

    public function reject(Request $request, BookingRefund $bookingRefund): RedirectResponse
    {
        Gate::authorize('reject', $bookingRefund);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->service->rejectRefund($bookingRefund, $request->user(), $validated['reason']);

        return back()->with('status', 'refund-rejected');
    }
}
