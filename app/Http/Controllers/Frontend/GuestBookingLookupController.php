<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\BookingCancellationStatus;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Http\Controllers\Concerns\RespondsWithGuestBookingJson;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Services\Client\ClientPageRenderer;
use App\Services\Customer\GuestBookingAccessService;
use App\Support\Client\ClientPageKeys;
use App\Services\Payments\BookingPaymentService;
use App\Support\Bookings\BookingDetailTimelinePresenter;
use App\Support\Bookings\BookingItineraryOverviewPresenter;
use App\Support\Bookings\BookingPaymentSummaryPresenter;
use App\Support\Bookings\PaymentOperationalStatus;
use App\Support\Bookings\SupplierOperationalStatus;
use App\Support\Bookings\TicketingOperationalStatus;
use App\Support\GuestBooking\GuestBookingDetailPresenter;
use App\Support\Security\TurnstileVerifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class GuestBookingLookupController extends Controller
{
    use RespondsWithGuestBookingJson;

    public function __construct(
        protected GuestBookingAccessService $guestAccessService,
        protected BookingPaymentService $paymentService,
        protected ClientPageRenderer $pageRenderer,
        protected GuestBookingDetailPresenter $guestDetailPresenter,
    ) {}

    public function showLookupForm(Request $request): View
    {

        return view(client_view('frontend.booking.lookup', 'frontend'), $this->pageRenderer->viewModel(ClientPageKeys::BOOKING_LOOKUP));
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
        $this->guestAccessService->bindSession($request, $booking, $token);

        $redirectUrl = client_route('guest.bookings.show', ['booking' => $booking]);

        if ($request->wantsJson()) {
            return $this->withGuestAccessHeaders(response()->json([
                'ok' => true,
                'redirect_url' => $redirectUrl,
            ]));
        }

        return $this->withGuestAccessHeaders(redirect()->to($redirectUrl));
    }

    /**
     * Token-free guest detail (session cookie authorization).
     */
    public function showGuestBooking(Request $request, Booking $booking): View|JsonResponse|Response
    {
        if (! $this->guestAccessService->authorizeGuestAccess($request, $booking)) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->withGuestAccessHeaders($this->guestBookingAccessDenied());
            }

            abort(403);
        }

        return $this->renderGuestBooking($request, $booking);
    }

    /**
     * Legacy path bearer: validate once, bind session, redirect to token-free URL (OPS-DEF-0011).
     */
    public function exchangeGuestBookingToken(Request $request, Booking $booking, string $token): RedirectResponse|View|JsonResponse|Response
    {
        if (! $this->guestAccessService->validateToken($booking, $token)) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->withGuestAccessHeaders($this->guestBookingAccessDenied());
            }

            abort(403);
        }

        $this->guestAccessService->bindSession($request, $booking, $token);

        // JSON clients that still hit the legacy path get the detail after exchange bind
        // without returning the token in URLs.
        if ($this->wantsGuestBookingJson($request)) {
            return $this->renderGuestBooking($request, $booking);
        }

        $cleanUrl = client_route('guest.bookings.show', ['booking' => $booking]);

        return $this->withGuestAccessHeaders(redirect()->to($cleanUrl));
    }

    public function downloadGuestDocument(Request $request, BookingDocument $bookingDocument): BinaryFileResponse
    {
        $booking = $bookingDocument->booking;
        if ($booking === null || ! $this->guestAccessService->authorizeGuestAccess($request, $booking)) {
            abort(403);
        }

        if ($bookingDocument->file_path === null || ! Storage::disk('local')->exists($bookingDocument->file_path)) {
            abort(404);
        }

        return $this->withGuestAccessHeaders(
            response()->download(Storage::disk('local')->path($bookingDocument->file_path), basename((string) $bookingDocument->file_path))
        );
    }

    public function submitGuestPaymentProof(Request $request, Booking $booking, ?string $token = null): RedirectResponse|JsonResponse
    {
        if (! $this->guestAccessService->authorizeGuestAccess($request, $booking, $token)) {
            if ($this->wantsGuestBookingJson($request)) {
                return $this->withGuestAccessHeaders($this->guestBookingAccessDenied());
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
            return $this->withGuestAccessHeaders($this->guestBookingJson([
                'ok' => true,
                'message' => 'Payment proof submitted. Our team will review it shortly.',
            ], 201));
        }

        return $this->withGuestAccessHeaders(back()->with('status', 'payment-proof-submitted'));
    }

    /**
     * @return View|JsonResponse|Response
     */
    protected function renderGuestBooking(Request $request, Booking $booking): View|JsonResponse|Response
    {
        if ($this->wantsGuestBookingJson($request)) {
            return $this->withGuestAccessHeaders(
                $this->guestBookingJson($this->guestDetailPresenter->present($booking, $request))
            );
        }

        $booking->load([
            'passengers',
            'contact',
            'fareBreakdown',
            'statusLogs',
            'payments',
            'tickets',
            'documents',
            'communicationLogs',
            'cancellationRequests.requester',
            'refunds',
            'supplierBookings',
        ]);

        $meta = is_array($booking->meta) ? $booking->meta : [];
        $hasPnr = filled($booking->pnr)
            || $booking->supplierBookings->contains(fn ($sb) => filled($sb->pnr));
        $provider = (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? ''));
        $hasLinkedAccount = filled($booking->customer_id);
        $canUploadProof = ! $hasLinkedAccount
            && $booking->status !== BookingStatus::Cancelled
            && BookingPaymentSummaryPresenter::canUploadProof($booking, true);

        $openCancellation = $booking->cancellationRequests->contains(
            fn ($r) => in_array($r->status->value, [
                BookingCancellationStatus::Requested->value,
                BookingCancellationStatus::Approved->value,
            ], true)
        );
        $showGuestCancelForm = ! $hasLinkedAccount
            && $booking->status !== BookingStatus::Cancelled
            && ! $openCancellation;

        $viewData = [
            'booking' => $booking,
            // Do not embed raw token in HTML (OPS-DEF-0011). Mutations use session-auth routes.
            'guestToken' => null,
            'viewerMode' => 'guest',
            'hasLinkedAccount' => $hasLinkedAccount,
            'hasPnr' => $hasPnr,
            'loginUrl' => client_route('login', ['redirect' => '/customer/bookings/'.$booking->id]),
            'allowGuestProofUpload' => $canUploadProof,
            'showGuestCancelForm' => $showGuestCancelForm,
            'itineraryOverview' => BookingItineraryOverviewPresenter::fromBookingMeta($meta, $hasPnr),
            'paymentOperational' => PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid')),
            'supplierOperational' => SupplierOperationalStatus::fromValues(
                (string) ($booking->supplier_booking_status ?? 'not_started'),
                $provider,
                $hasPnr,
                $meta,
            ),
            'ticketingOperational' => TicketingOperationalStatus::fromValues(
                (string) ($booking->ticketing_status ?? 'not_started'),
                (string) ($booking->payment_status ?? 'unpaid'),
                $hasPnr,
                $booking->tickets->isNotEmpty(),
                $provider,
                (string) ($booking->cancellation_status ?? ''),
            ),
            'customerTimeline' => BookingDetailTimelinePresenter::forBooking($booking, $meta, $hasPnr),
            'paymentSummary' => BookingPaymentSummaryPresenter::forBooking($booking, $canUploadProof, 'customer'),
        ];

        return $this->withGuestAccessHeaders(response()->view('frontend.booking.guest-show', $viewData));
    }

    /**
     * @template T of Response
     *
     * @param  T  $response
     * @return T
     */
    protected function withGuestAccessHeaders(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
