<?php

namespace App\Http\Controllers\Agent;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingCancellationType;
use App\Enums\BookingStatus;
use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Services\Bookings\BookingCancellationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingCancellationController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected BookingCancellationService $service,
    ) {}

    public function store(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        Gate::authorize('request', [BookingCancellationRequest::class, $booking]);

        $agent = $request->user()?->agent();
        if ($agent === null || (int) $booking->agent_id !== (int) $agent->id) {
            abort(403);
        }

        $existing = $this->findOpenCancellationRequest($booking);
        if ($existing !== null) {
            if ($this->wantsAgentPortalJson($request)) {
                return $this->agentPortalJson([
                    'ok' => false,
                    'code' => 'cancellation_already_requested',
                    'message' => 'A cancellation request is already in progress.',
                    'cancellation_request' => $this->presentCancellationRequest($existing),
                ], 409);
            }

            return back()->withErrors(['cancellation' => 'A cancellation request is already in progress.']);
        }

        if ($booking->status === BookingStatus::Cancelled) {
            if ($this->wantsAgentPortalJson($request)) {
                return $this->agentPortalJsonError(
                    'This booking has already been cancelled.',
                    422,
                    'booking_not_cancellable',
                );
            }

            return back()->withErrors(['cancellation' => 'This booking has already been cancelled.']);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'cancellation_type' => ['required', Rule::enum(BookingCancellationType::class)],
        ]);

        $cancellationRequest = $this->service->requestCancellation($booking, $request->user(), [
            ...$validated,
            'request_source' => 'agent',
        ]);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson([
                'ok' => true,
                'message' => 'Your cancellation request has been submitted for review.',
                'cancellation_request' => $this->presentCancellationRequest($cancellationRequest),
            ], 201);
        }

        return back()->with('status', 'cancellation-requested');
    }

    private function findOpenCancellationRequest(Booking $booking): ?BookingCancellationRequest
    {
        return $booking->cancellationRequests()
            ->whereIn('status', [
                BookingCancellationStatus::Requested,
                BookingCancellationStatus::Approved,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCancellationRequest(BookingCancellationRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => (string) ($request->status->value ?? $request->status),
            'status_label' => ucfirst(str_replace('_', ' ', (string) ($request->status->value ?? $request->status))),
            'cancellation_type' => (string) ($request->cancellation_type->value ?? $request->cancellation_type),
            'reason' => $request->reason,
            'requested_at' => $request->created_at?->toIso8601String(),
            'message' => 'Your cancellation request has been received and is under review.',
        ];
    }
}
