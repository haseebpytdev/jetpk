<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRefund;
use App\Services\Payments\BookingRefundService;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\BackOffice\BackOfficeBookingPresenter;
use App\Support\BackOffice\BackOfficeRefundPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BookingRefundController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected BookingRefundService $service,
        protected BackOfficeCapabilitiesPresenter $capabilitiesPresenter,
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

    public function approve(Request $request, BookingRefund $bookingRefund): RedirectResponse|JsonResponse
    {
        Gate::authorize('approve', $bookingRefund);

        try {
            $bookingRefund = $this->service->approveRefund($bookingRefund, $request->user());
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        $bookingRefund->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Refund approved for review. Settlement is not performed from this action.',
                'refund' => BackOfficeRefundPresenter::present($bookingRefund),
                'capabilities' => $this->capabilitiesPresenter->presentRefundCapabilities($request->user(), $bookingRefund),
            ]);
        }

        return back()->with('status', 'refund-approved');
    }

    public function markPaid(Request $request, BookingRefund $bookingRefund): RedirectResponse|JsonResponse
    {
        Gate::authorize('markPaid', $bookingRefund);

        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $bookingRefund = $this->service->markRefundPaid($bookingRefund, $request->user(), $validated);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        $bookingRefund->refresh();
        $booking = $bookingRefund->booking()->firstOrFail();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Refund settlement recorded.',
                'execution_state' => 'success',
                'refund' => BackOfficeRefundPresenter::present($bookingRefund),
                'booking' => BackOfficeBookingPresenter::present($booking),
                'capabilities' => $this->capabilitiesPresenter->presentRefundCapabilities($request->user(), $bookingRefund),
            ]);
        }

        return back()->with('status', 'refund-paid');
    }

    public function reject(Request $request, BookingRefund $bookingRefund): RedirectResponse|JsonResponse
    {
        Gate::authorize('reject', $bookingRefund);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $bookingRefund = $this->service->rejectRefund($bookingRefund, $request->user(), $validated['reason']);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['refund' => $e->getMessage()]);
        }

        $bookingRefund->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Refund rejected.',
                'refund' => BackOfficeRefundPresenter::present($bookingRefund),
                'capabilities' => $this->capabilitiesPresenter->presentRefundCapabilities($request->user(), $bookingRefund),
            ]);
        }

        return back()->with('status', 'refund-rejected');
    }
}
