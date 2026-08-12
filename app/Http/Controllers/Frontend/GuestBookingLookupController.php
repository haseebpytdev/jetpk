<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\BookingPaymentMethod;
use App\Http\Controllers\Concerns\RespondsWithGuestBookingJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Services\Customer\GuestBookingAccessService;
use App\Services\Payments\BookingPaymentService;
use App\Support\GuestBooking\GuestBookingDetailPresenter;
use App\Support\Security\TurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GuestBookingLookupController extends Controller
{
    use RespondsWithGuestBookingJson;

    public function __construct(
        protected GuestBookingAccessService $guestAccessService,
        protected BookingPaymentService $paymentService,
        protected GuestBookingDetailPresenter $guestDetailPresenter,
    ) {}

    public function showLookupForm(Request $request): RedirectResponse
    {
        // Modern Manage Booking presentation is Next /lookup-booking.
        // Keep POST lookup + guest JSON APIs on Laravel; retire Blade form presentation.
        return redirect()->to('/lookup-booking', 302);
    }

    public function lookup(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate(array_merge([
            'booking_reference' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], TurnstileVerifier::validationRules()), TurnstileVerifier::validationMessages());

        $booking = $this->guestAccessService->findBookingForLookup(
            $validated['booking_reference'],
            $validated['email'],
            $validated['phone'] ?? null,
        );

        if ($booking === null) {
            return back()->withErrors(['lookup' => 'Booking not found for the provided reference and email.']);
        }

        $token = $this->guestAccessService->createTokenForBooking($booking, $validated['email'], $validated['phone'] ?? null);

        $redirectUrl = client_route('guest.bookings.show', ['booking' => $booking, 'token' => $token]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl);
    }

    public function showGuestBooking(Request $request, Booking $booking, string $token): JsonResponse|RedirectResponse
    {
        if (! $this->guestAccessService->validateToken($booking, $token)) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->guestBookingAccessDenied();
            }

            abort(403);
        }

        if ($this->wantsGuestBookingJson($request)) {
            return $this->guestBookingJson($this->guestDetailPresenter->present($booking, $token, $request));
        }

        // HTML presentation is owned by Next /guest/bookings/{id}/access/{token}.
        return redirect()->to('/guest/bookings/'.$booking->getKey().'/access/'.$token, 302);
    }

    public function downloadGuestDocument(Request $request, BookingDocument $bookingDocument): BinaryFileResponse
    {
        $token = (string) $request->query('token', '');
        $booking = $bookingDocument->booking;
        if ($booking === null || $token === '' || ! $this->guestAccessService->validateToken($booking, $token)) {
            abort(403);
        }

        if ($bookingDocument->file_path === null || ! Storage::disk('local')->exists($bookingDocument->file_path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($bookingDocument->file_path), basename((string) $bookingDocument->file_path));
    }

    public function submitGuestPaymentProof(Request $request, Booking $booking, string $token): RedirectResponse|JsonResponse
    {
        if (! $this->guestAccessService->validateToken($booking, $token)) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->guestBookingAccessDenied();
            }

            abort(403);
        }

        $validated = $request->validate([
            'method' => ['required', Rule::enum(BookingPaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->paymentService->submitPaymentProof($booking, null, $validated);

        if ($this->wantsGuestBookingJson($request)) {
            return $this->guestBookingJson([
                'ok' => true,
                'message' => 'Payment proof submitted. Our team will review it shortly.',
            ], 201);
        }

        return back()->with('status', 'payment-proof-submitted');
    }
}
