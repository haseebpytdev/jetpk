<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingCancellationType;
use App\Http\Controllers\Concerns\RespondsWithGuestBookingJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingCancellationRequest;
use App\Services\Bookings\BookingCancellationService;
use App\Services\Customer\GuestBookingAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestBookingCancellationController extends Controller
{
    use RespondsWithGuestBookingJson;

    public function __construct(
        protected GuestBookingAccessService $guestAccessService,
        protected BookingCancellationService $service,
    ) {}

    public function store(Request $request, Booking $booking, string $token): RedirectResponse|JsonResponse
    {
        if (! $this->guestAccessService->validateToken($booking, $token)) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->guestBookingAccessDenied();
            }

            abort(403);
        }

        $existing = $this->findOpenCancellationRequest($booking);
        if ($existing !== null) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->guestBookingJson([
                    'ok' => false,
                    'code' => 'cancellation_already_requested',
                    'message' => 'A cancellation request is already in progress.',
                    'cancellation_request' => $this->presentCancellationRequest($existing),
                ], 409);
            }

            return back()->withErrors(['cancellation' => 'A cancellation request is already in progress.']);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:5000'],
            'cancellation_type' => ['required', Rule::enum(BookingCancellationType::class)],
            'terms_acknowledged' => ['sometimes', 'boolean'],
        ]);

        $cancellationRequest = $this->service->requestCancellation($booking, null, [
            ...$validated,
            'request_source' => 'guest',
            'meta' => ['guest_token_used' => true],
        ]);

        if ($this->wantsGuestBookingJson($request)) {
            return $this->guestBookingJson([
                'ok' => true,
                'message' => 'Your cancellation request has been submitted. Our team will review it shortly.',
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
