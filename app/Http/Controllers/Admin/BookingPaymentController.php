<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingPaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BookingPaymentController extends Controller
{
    public function __construct(
        protected BookingPaymentService $paymentService,
    ) {}

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('recordPayment', $booking);
        $validated = $request->validate([
            'method' => ['required', Rule::enum(BookingPaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_proof' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf,webp'],
            'admin_override' => ['nullable', 'boolean'],
            'verify_now' => ['nullable', 'boolean'],
        ]);

        if ($request->hasFile('payment_proof')) {
            $path = $request->file('payment_proof')->store('booking-payments/proofs', 'local');
            $validated['proof_path'] = $path;
        }

        try {
            $this->paymentService->recordManualPayment($booking, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-recorded');
    }

    public function verify(Request $request, BookingPayment $bookingPayment): RedirectResponse
    {
        Gate::authorize('verifyPayment', $bookingPayment->booking);
        try {
            $this->paymentService->verifyPayment($bookingPayment, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return back()->with('status', 'payment-verified');
    }

    public function reject(Request $request, BookingPayment $bookingPayment): RedirectResponse
    {
        Gate::authorize('rejectPayment', $bookingPayment->booking);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        $this->paymentService->rejectPayment($bookingPayment, $request->user(), $validated['reason']);

        return back()->with('status', 'payment-rejected');
    }
}
