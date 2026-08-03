<?php

namespace App\Http\Controllers\Staff;

use App\Enums\BookingCancellationType;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Services\Bookings\BookingCancellationService;
use App\Support\BackOffice\BackOfficeCancellationPresenter;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BookingCancellationController extends Controller
{
    use RespondsWithBackOfficeJson;

    public function __construct(
        protected BookingCancellationService $service,
        protected BackOfficeCapabilitiesPresenter $capabilitiesPresenter,
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

    public function approve(Request $request, BookingCancellationRequest $cancellationRequest): RedirectResponse|JsonResponse
    {
        Gate::authorize('approve', $cancellationRequest);

        try {
            $cancellationRequest = $this->service->approveCancellation($cancellationRequest, $request->user());
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['cancellation' => $e->getMessage()]);
        }

        $cancellationRequest->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Cancellation approved for review. Supplier execution is not performed from this action.',
                'cancellation_request' => BackOfficeCancellationPresenter::present($cancellationRequest),
                'capabilities' => $this->capabilitiesPresenter->presentCancellationCapabilities($request->user(), $cancellationRequest),
            ]);
        }

        return back()->with('status', 'cancellation-approved');
    }

    public function reject(Request $request, BookingCancellationRequest $cancellationRequest): RedirectResponse|JsonResponse
    {
        Gate::authorize('reject', $cancellationRequest);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        try {
            $cancellationRequest = $this->service->rejectCancellation($cancellationRequest, $request->user(), $validated['reason']);
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                return $this->backOfficeJsonError($e->getMessage(), 409, 'already_processed');
            }

            return back()->withErrors(['cancellation' => $e->getMessage()]);
        }

        $cancellationRequest->refresh();

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => 'Cancellation request rejected.',
                'cancellation_request' => BackOfficeCancellationPresenter::present($cancellationRequest),
                'capabilities' => $this->capabilitiesPresenter->presentCancellationCapabilities($request->user(), $cancellationRequest),
            ]);
        }

        return back()->with('status', 'cancellation-rejected');
    }

    public function process(Request $request, BookingCancellationRequest $cancellationRequest): RedirectResponse|JsonResponse
    {
        Gate::authorize('process', $cancellationRequest);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJsonError(
                'Supplier cancellation execution is deferred to JP-OPS-06.',
                403,
                'external_execution_required',
            );
        }

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
