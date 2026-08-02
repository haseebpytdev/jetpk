<?php

namespace App\Http\Controllers\Agent;

use App\Enums\BookingPaymentMethod;
use App\Http\Controllers\Concerns\RespondsWithAgentPortalJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\Payments\BookingPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookingPaymentProofController extends Controller
{
    use RespondsWithAgentPortalJson;

    public function __construct(
        protected BookingPaymentService $paymentService,
    ) {}

    public function store(Request $request, Booking $booking): RedirectResponse|JsonResponse
    {
        Gate::authorize('submitPaymentProof', $booking);

        $agent = $request->user()?->agent();
        if ($agent === null || (int) $booking->agent_id !== (int) $agent->id) {
            abort(403);
        }

        $validated = $request->validate([
            'method' => ['required', Rule::enum(BookingPaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->paymentService->submitPaymentProof($booking, $request->user(), $validated);

        if ($this->wantsAgentPortalJson($request)) {
            return $this->agentPortalJson([
                'ok' => true,
                'message' => 'Payment proof submitted for review.',
            ], 201);
        }

        return back()->with('status', 'payment-proof-submitted');
    }
}
