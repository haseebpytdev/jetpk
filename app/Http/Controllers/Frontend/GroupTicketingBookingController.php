<?php

namespace App\Http\Controllers\Frontend;

use App\Enums\GroupBookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\GroupTicketingPassengersRequest;
use App\Http\Requests\Frontend\GroupTicketingPaymentRequest;
use App\Models\GroupBooking;
use App\Models\GroupInventory;
use App\Services\GroupTicketing\GroupBookingRestrictionService;
use App\Services\GroupTicketing\GroupInventoryAvailabilityService;
use App\Services\GroupTicketing\GroupInventorySearchService;
use App\Services\GroupTicketing\GroupReservationService;
use App\Support\Geo\CountryList;
use App\Support\GroupTicketing\GroupInventoryCardPresenter;
use App\Support\GroupTicketing\GroupTicketingJsonPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Auth-gated group ticketing checkout (separate from Sabre flight BookingController).
 */
class GroupTicketingBookingController extends Controller
{
    public function __construct(
        protected GroupInventorySearchService $searchService,
        protected GroupReservationService $reservationService,
        protected GroupInventoryCardPresenter $cardPresenter,
        protected GroupBookingRestrictionService $restrictionService,
        protected GroupInventoryAvailabilityService $availabilityService,
        protected GroupTicketingJsonPresenter $jsonPresenter,
    ) {}

    public function passengers(GroupInventory $inventory, Request $request): View|RedirectResponse|JsonResponse
    {
        if ($this->restrictionService->isBlocked($request->user())) {
            if ($request->wantsJson() || $request->query('format') === 'json') {
                return response()->json([
                    'success' => false,
                    'status' => 'locked',
                    'message' => GroupBookingRestrictionService::BLOCK_THRESHOLD.' unpaid group reservations expired without payment. Your group booking access is temporarily restricted. Please contact support.',
                    'lock_state' => $this->jsonPresenter->presentLockState($request->user()),
                ], 403);
            }

            return redirect()->route('group-ticketing.search')->with('warning', GroupBookingRestrictionService::BLOCK_THRESHOLD.' unpaid group reservations expired without payment. Your group booking access is temporarily restricted. Please contact support.');
        }

        $availability = $this->availabilityService->revalidate($inventory, 1);
        $inventory = $availability['inventory'];

        if (! $availability['ok']) {
            if ($request->wantsJson() || $request->query('format') === 'json') {
                return response()->json([
                    'success' => false,
                    'status' => 'unavailable',
                    'message' => GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE,
                ], 410);
            }

            return redirect()->route('group-ticketing.search')->with(
                'warning',
                GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE,
            );
        }

        $card = $this->cardPresenter->present($inventory);
        $seatCount = (int) old('seat_count', 1);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                ...$this->jsonPresenter->presentPassengersContext($inventory, $card, $seatCount),
                'lock_state' => $this->jsonPresenter->presentLockState($request->user()),
            ]);
        }

        return view('frontend.group-ticketing.passengers', [
            'inventory' => $inventory,
            'card' => $card,
            'seatCount' => $seatCount,
            'checkoutCountries' => CountryList::forSelect(),
            'checkoutSummary' => $this->cardPresenter->buildCheckoutSummary($card, $seatCount),
            'activeStep' => 'passengers',
        ]);
    }

    public function storePassengers(GroupInventory $inventory, GroupTicketingPassengersRequest $request): RedirectResponse|JsonResponse
    {
        if ($this->restrictionService->isBlocked($request->user())) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'locked',
                    'message' => 'Your group booking access is temporarily restricted.',
                    'lock_state' => $this->jsonPresenter->presentLockState($request->user()),
                ], 403);
            }

            return redirect()->route('group-ticketing.search')->with('warning', 'Your group booking access is temporarily restricted.');
        }

        $seatCount = (int) $request->input('seat_count', 1);
        $availability = $this->availabilityService->revalidate($inventory, $seatCount);
        $inventory = $availability['inventory'];

        if (! $availability['ok']) {
            if ($availability['unavailable']) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'status' => 'unavailable',
                        'message' => GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE,
                    ], 410);
                }

                return redirect()->route('group-ticketing.search')->with(
                    'warning',
                    GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE,
                );
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'validation_error',
                    'message' => GroupInventoryAvailabilityService::insufficientSeatsMessage($availability['available_seats']),
                    'errors' => [
                        'seat_count' => [GroupInventoryAvailabilityService::insufficientSeatsMessage($availability['available_seats'])],
                    ],
                    'available_seats' => $availability['available_seats'],
                ], 422);
            }

            return back()->withInput()->withErrors([
                'seat_count' => GroupInventoryAvailabilityService::insufficientSeatsMessage($availability['available_seats']),
            ]);
        }

        $currentUnitPrice = round((float) $inventory->price, 2);
        $quotedRaw = $request->input('quoted_unit_price');
        $acceptPriceChange = $request->boolean('accept_price_change');
        if (is_numeric($quotedRaw) && ! $acceptPriceChange) {
            $quotedUnitPrice = round((float) $quotedRaw, 2);
            if (abs($quotedUnitPrice - $currentUnitPrice) > 0.009) {
                $payload = [
                    'success' => false,
                    'status' => 'price_changed',
                    'message' => 'The per-seat group fare changed. Please review the updated price before continuing.',
                    'price_change' => [
                        'currency' => (string) ($inventory->currency ?: 'PKR'),
                        'old_unit_price' => $quotedUnitPrice,
                        'new_unit_price' => $currentUnitPrice,
                        'available_seats' => $availability['available_seats'],
                    ],
                ];

                if ($request->wantsJson()) {
                    return response()->json($payload, 409);
                }

                return back()->withInput()->with('price_change', $payload['price_change'])->withErrors([
                    'quoted_unit_price' => $payload['message'],
                ]);
            }
        }

        try {
            $booking = $this->reservationService->startDraft(
                $inventory,
                (int) $request->user()->id,
                (int) $request->input('seat_count', 1),
                $request->passengerRows(),
                $request->contactDetails(),
            );
        } catch (\Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'validation_error',
                    'message' => $exception->getMessage(),
                    'errors' => ['seat_count' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withInput()->withErrors(['seat_count' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            $card = $this->cardPresenter->present($booking->inventory);

            return response()->json([
                'success' => true,
                'redirect_path' => '/groups/booking/'.$booking->reference.'/review',
                'booking' => $this->jsonPresenter->presentReview($booking, $card),
            ]);
        }

        return redirect()->route('group-ticketing.booking.review', $booking);
    }

    public function review(GroupBooking $groupBooking, Request $request): View|RedirectResponse|JsonResponse
    {
        $authResponse = $this->authorizeBookingResponse($groupBooking, $request);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if ($groupBooking->isExpired() && $groupBooking->isReleasable()) {
            if ($request->wantsJson() || $request->query('format') === 'json') {
                return response()->json([
                    'success' => false,
                    'status' => 'hold_expired',
                    'message' => 'Your reservation has expired.',
                ], 410);
            }

            return redirect()->route('group-ticketing.search')->with('warning', 'Your reservation has expired.');
        }

        $groupBooking->load(['passengers', 'inventory']);
        $card = $this->cardPresenter->present($groupBooking->inventory);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                ...$this->jsonPresenter->presentReview($groupBooking, $card),
            ]);
        }

        return view('frontend.group-ticketing.review', [
            'booking' => $groupBooking,
            'card' => $card,
            'holdMinutes' => $this->reservationService->holdMinutes(),
            'checkoutSummary' => $this->cardPresenter->buildCheckoutSummary(
                $card,
                (int) $groupBooking->seat_count,
                (float) $groupBooking->total_amount,
            ),
            'activeStep' => 'review',
        ]);
    }

    public function confirmReview(GroupBooking $groupBooking, Request $request): RedirectResponse|JsonResponse
    {
        $authResponse = $this->authorizeBookingResponse($groupBooking, $request);
        if ($authResponse !== null) {
            return $authResponse;
        }

        try {
            $booking = $this->reservationService->createReservation($groupBooking);
        } catch (\Throwable $exception) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'reservation_failed',
                    'message' => $exception->getMessage(),
                    'errors' => ['reservation' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            $card = $this->cardPresenter->present($booking->inventory);

            return response()->json([
                'success' => true,
                'redirect_path' => '/groups/booking/'.$booking->reference.'/payment',
                'booking' => $this->jsonPresenter->presentPayment($booking, $card),
            ]);
        }

        return redirect()->route('group-ticketing.booking.payment', $booking);
    }

    public function payment(GroupBooking $groupBooking, Request $request): View|RedirectResponse|JsonResponse
    {
        $authResponse = $this->authorizeBookingResponse($groupBooking, $request);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if ($groupBooking->status === GroupBookingStatus::ManualPaymentPendingReview) {
            if ($request->wantsJson() || $request->query('format') === 'json') {
                $groupBooking->load(['inventory', 'passengers']);
                $card = $this->cardPresenter->present($groupBooking->inventory);

                return response()->json([
                    'success' => true,
                    'redirect_path' => '/groups/booking/'.$groupBooking->reference.'/confirmation',
                    ...$this->jsonPresenter->presentConfirmation($groupBooking, $card),
                ]);
            }

            return redirect()->route('group-ticketing.booking.confirmation', $groupBooking);
        }

        if ($groupBooking->isExpired() && $groupBooking->isReleasable()) {
            $this->reservationService->releaseUnpaidBooking($groupBooking, 'unpaid_timeout');

            if ($request->wantsJson() || $request->query('format') === 'json') {
                return response()->json([
                    'success' => false,
                    'status' => 'hold_expired',
                    'message' => 'Your reservation has expired.',
                ], 410);
            }

            return redirect()->route('group-ticketing.search')->with('warning', 'Your reservation has expired.');
        }

        try {
            $this->reservationService->markPaymentPending($groupBooking);
        } catch (\Throwable) {
            if ($request->wantsJson() || $request->query('format') === 'json') {
                return response()->json([
                    'success' => false,
                    'status' => 'invalid_booking',
                    'message' => 'Reservation is no longer valid.',
                ], 410);
            }

            return redirect()->route('group-ticketing.search')->with('warning', 'Reservation is no longer valid.');
        }

        $groupBooking->load(['inventory', 'passengers']);
        $booking = $groupBooking->fresh(['inventory', 'passengers']);
        $card = $this->cardPresenter->present($booking->inventory);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                ...$this->jsonPresenter->presentPayment($booking, $card),
            ]);
        }

        return view('frontend.group-ticketing.payment', [
            'booking' => $booking,
            'card' => $card,
            'checkoutSummary' => $this->cardPresenter->buildCheckoutSummary(
                $card,
                (int) $booking->seat_count,
                (float) $booking->total_amount,
            ),
            'activeStep' => 'payment',
        ]);
    }

    public function submitPayment(GroupBooking $groupBooking, GroupTicketingPaymentRequest $request): RedirectResponse|JsonResponse
    {
        $authResponse = $this->authorizeBookingResponse($groupBooking, $request);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('group-payment-proofs', 'public');
        }

        try {
            $booking = $this->reservationService->submitManualPayment($groupBooking, [
                'payment_method' => $request->input('payment_method'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_proof_path' => $proofPath,
            ]);
        } catch (\Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('public')->delete($proofPath);
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status' => 'payment_failed',
                    'message' => $exception->getMessage(),
                    'errors' => ['payment' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        if ($request->wantsJson()) {
            $card = $this->cardPresenter->present($booking->inventory);

            return response()->json([
                'success' => true,
                'redirect_path' => '/groups/booking/'.$booking->reference.'/confirmation',
                'booking' => $this->jsonPresenter->presentConfirmation($booking, $card),
            ]);
        }

        return redirect()->route('group-ticketing.booking.confirmation', $groupBooking);
    }

    public function confirmation(GroupBooking $groupBooking, Request $request): View|JsonResponse
    {
        $authResponse = $this->authorizeBookingResponse($groupBooking, $request);
        if ($authResponse !== null) {
            return $authResponse;
        }

        $booking = $groupBooking->load(['inventory', 'passengers']);
        $card = $this->cardPresenter->present($booking->inventory);

        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json([
                'success' => true,
                ...$this->jsonPresenter->presentConfirmation($booking, $card),
            ]);
        }

        return view('frontend.group-ticketing.confirmation', [
            'booking' => $booking,
            'card' => $card,
            'checkoutSummary' => $this->cardPresenter->buildCheckoutSummary(
                $card,
                (int) $booking->seat_count,
                (float) $booking->total_amount,
            ),
            'activeStep' => 'confirmation',
        ]);
    }

    public function bookingStatus(GroupBooking $groupBooking, Request $request): JsonResponse
    {
        $authResponse = $this->authorizeBookingResponse($groupBooking, $request);
        if ($authResponse !== null) {
            return $authResponse;
        }

        if ($groupBooking->isExpired() && $groupBooking->isReleasable()) {
            $this->reservationService->releaseUnpaidBooking($groupBooking, 'unpaid_timeout');
            $groupBooking->refresh();
        }

        $groupBooking->load(['inventory', 'passengers']);
        $card = $this->cardPresenter->present($groupBooking->inventory);

        return response()->json([
            'success' => true,
            'booking' => $this->jsonPresenter->presentBooking($groupBooking, $card),
        ]);
    }

    private function authorizeBooking(GroupBooking $groupBooking): void
    {
        if ((int) $groupBooking->user_id !== (int) auth()->id()) {
            abort(403);
        }
    }

    private function authorizeBookingResponse(GroupBooking $groupBooking, Request $request): ?JsonResponse
    {
        if ((int) $groupBooking->user_id !== (int) auth()->id()) {
            if ($request->wantsJson() || $request->query('format') === 'json') {
                return response()->json([
                    'success' => false,
                    'status' => 'forbidden',
                    'message' => 'You do not have access to this booking session.',
                ], 403);
            }

            abort(403);
        }

        return null;
    }
}
