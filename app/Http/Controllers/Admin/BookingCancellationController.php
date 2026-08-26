<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingCancellationType;
use App\Http\Controllers\Concerns\RespondsWithBackOfficeJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Models\SupplierConnection;
use App\Services\Bookings\BookingCancellationService;
use App\Support\BackOffice\BackOfficeCancellationPresenter;
use App\Support\BackOffice\BackOfficeBookingPresenter;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\Sabre\SabreSandboxQaLifecycleGuard;
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

    public function store(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        Gate::authorize('request', [BookingCancellationRequest::class, $booking]);
        $validated = $this->validateBackOffice($request, [
            'reason' => ['nullable', 'string', 'max:5000'],
            'cancellation_type' => ['required', Rule::enum(BookingCancellationType::class)],
        ]);

        $cancellationRequest = $this->service->requestCancellation($booking, $request->user(), [
            ...$validated,
            'request_source' => $request->user()->account_type?->value ?? 'admin',
        ]);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'cancellation_request' => BackOfficeCancellationPresenter::present($cancellationRequest),
                'capabilities' => $this->capabilitiesPresenter->presentCancellationCapabilities($request->user(), $cancellationRequest),
            ]);
        }

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
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

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

        try {
            $processed = $this->service->processCancellation($cancellationRequest, $request->user(), true, 'admin');
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                $code = str_contains(strtolower($e->getMessage()), 'pending supplier reconciliation')
                    ? 'pending_reconciliation'
                    : 'already_processed';

                return $this->backOfficeJsonError($e->getMessage(), 409, $code);
            }

            return back()->withErrors(['cancellation' => $e->getMessage()]);
        }

        $processed->refresh();
        $booking = $processed->booking()->firstOrFail();
        $pendingReconciliation = ($processed->meta['sabre_cancel_manual_review'] ?? false) === true
            || filled($processed->meta['manual_warning'] ?? null);
        $message = $pendingReconciliation ? 'cancellation-processed-manual-review' : 'cancellation-processed';

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => $pendingReconciliation
                    ? 'Supplier cancellation requires manual reconciliation. Booking status was not promoted to cancelled.'
                    : 'Cancellation execution completed.',
                'execution_state' => $pendingReconciliation ? 'pending_reconciliation' : 'success',
                'cancellation_request' => BackOfficeCancellationPresenter::present($processed),
                'booking' => BackOfficeBookingPresenter::present($booking),
                'capabilities' => $this->capabilitiesPresenter->presentCancellationCapabilities($request->user(), $processed),
                'manual_warning' => $processed->meta['manual_warning'] ?? null,
            ]);
        }

        return back()
            ->with('status', $message)
            ->with('cancellation_warning', $processed->meta['manual_warning'] ?? null);
    }

    /**
     * Platform-admin one-shot: request → approve → process supplier cancellation.
     * Domain audit events remain separate for each step inside BookingCancellationService.
     */
    public function adminDirectCancel(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        if (! $request->user()?->isPlatformAdmin()) {
            abort(403);
        }

        Gate::authorize('request', [BookingCancellationRequest::class, $booking]);

        $validated = $this->validateBackOffice($request, [
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
            'cancellation_type' => ['nullable', Rule::enum(BookingCancellationType::class)],
        ]);

        try {
            $this->assertAdminDirectCancelConnectionGuard($booking);

            $cancellationRequest = $this->resolveOrCreateAdminDirectCancellationRequest(
                $booking,
                $request->user(),
                $validated,
            );
            if ($cancellationRequest->status === BookingCancellationStatus::Requested) {
                Gate::authorize('approve', $cancellationRequest);
                $cancellationRequest = $this->service->approveCancellation($cancellationRequest, $request->user());
            }
            Gate::authorize('process', $cancellationRequest);
            $processed = $this->service->processCancellation($cancellationRequest, $request->user(), true, 'admin');
        } catch (InvalidArgumentException $e) {
            if ($this->wantsBackOfficeJson($request)) {
                $message = strtolower($e->getMessage());
                $code = str_contains($message, 'pending supplier reconciliation')
                    ? 'pending_reconciliation'
                    : (str_contains($message, 'qa_lifecycle') || str_contains($message, 'production_host')
                        ? 'qa_lifecycle_production_host_guard'
                        : 'admin_direct_cancel_blocked');

                return $this->backOfficeJsonError($e->getMessage(), 409, $code);
            }

            return back()->withErrors(['cancellation' => $e->getMessage()]);
        }

        $processed->refresh();
        $booking->refresh();
        $pendingReconciliation = ($processed->meta['sabre_cancel_manual_review'] ?? false) === true
            || filled($processed->meta['manual_warning'] ?? null);

        if ($this->wantsBackOfficeJson($request)) {
            return $this->backOfficeJson([
                'ok' => true,
                'message' => $pendingReconciliation
                    ? 'Admin direct cancel requires manual reconciliation. Booking was not falsely marked cancelled.'
                    : 'Admin direct cancel workflow completed.',
                'execution_state' => $pendingReconciliation ? 'pending_reconciliation' : 'success',
                'cancellation_request' => BackOfficeCancellationPresenter::present($processed),
                'booking' => BackOfficeBookingPresenter::present($booking),
                'capabilities' => $this->capabilitiesPresenter->presentCancellationCapabilities($request->user(), $processed),
                'manual_warning' => $processed->meta['manual_warning'] ?? null,
            ]);
        }

        return back()->with('status', $pendingReconciliation
            ? 'admin-direct-cancel-manual-review'
            : 'admin-direct-cancel-completed');
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveOrCreateAdminDirectCancellationRequest(
        Booking $booking,
        $actor,
        array $validated,
    ): BookingCancellationRequest {
        $open = BookingCancellationRequest::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', [
                BookingCancellationStatus::Requested->value,
                BookingCancellationStatus::Approved->value,
            ])
            ->orderByDesc('id')
            ->first();

        if ($open !== null) {
            return $open;
        }

        return $this->service->requestCancellation($booking, $actor, [
            'reason' => $validated['reason'],
            'cancellation_type' => $validated['cancellation_type'] ?? BookingCancellationType::BookingCancel->value,
            'request_source' => 'admin_direct',
        ]);
    }

    private function assertAdminDirectCancelConnectionGuard(Booking $booking): void
    {
        $meta = is_array($booking->meta) ? $booking->meta : [];
        $connectionId = (int) ($meta['supplier_connection_id'] ?? 0);
        if ($connectionId <= 0) {
            throw new InvalidArgumentException('Supplier cancellation requires supplier_connection_id on the booking.');
        }

        $connection = SupplierConnection::query()->find($connectionId);
        if ($connection === null) {
            throw new InvalidArgumentException('Supplier connection for this booking was not found.');
        }

        if ($connection->isSandbox()) {
            $guard = SabreSandboxQaLifecycleGuard::assertSandboxQaAllowed($connection);
            if (! ($guard['allowed'] ?? false)) {
                throw new InvalidArgumentException(
                    'QA_LIFECYCLE_PRODUCTION_HOST_GUARD: '.($guard['block_reason'] ?? 'blocked')
                );
            }
        }
    }
}
