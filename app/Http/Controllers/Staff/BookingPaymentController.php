<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingPaymentMethod;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\Payments\BookingPaymentService;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\BackOffice\BackOfficePaymentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BookingPaymentController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected BookingPaymentService $paymentService,
        protected BackOfficeCapabilitiesPresenter $capabilitiesPresenter,
    ) {}

    public function store(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        Gate::authorize('recordPayment', $booking);
        $validated = $this->validateBackOffice($request, [
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
            $payment = $this->paymentService->recordManualPayment($booking, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'payment_blocked');
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'payment' => BackOfficePaymentPresenter::present($payment),
                'capabilities' => $this->capabilitiesPresenter->presentBookingPaymentCapabilities($request->user(), $payment),
            ]);
        }

        return back()->with('status', 'payment-recorded');
    }

    public function verify(Request $request, BookingPayment $bookingPayment): RedirectResponse|JsonResponse
    {
        Gate::authorize('verifyPayment', $bookingPayment->booking);
        try {
            $bookingPayment = $this->paymentService->verifyPayment($bookingPayment, $request->user());
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        $bookingPayment->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Payment verified.',
                'payment' => BackOfficePaymentPresenter::present($bookingPayment),
                'capabilities' => $this->capabilitiesPresenter->presentBookingPaymentCapabilities($request->user(), $bookingPayment),
            ]);
        }

        return back()->with('status', 'payment-verified');
    }

    public function reject(Request $request, BookingPayment $bookingPayment): RedirectResponse|JsonResponse
    {
        Gate::authorize('rejectPayment', $bookingPayment->booking);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $bookingPayment = $this->paymentService->rejectPayment($bookingPayment, $request->user(), $validated['reason']);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        $bookingPayment->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Payment rejected.',
                'payment' => BackOfficePaymentPresenter::present($bookingPayment),
                'capabilities' => $this->capabilitiesPresenter->presentBookingPaymentCapabilities($request->user(), $bookingPayment),
            ]);
        }

        return back()->with('status', 'payment-rejected');
    }
}
