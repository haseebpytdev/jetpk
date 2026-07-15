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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
    ) {}

    public function passengers(GroupInventory $inventory, Request $request): View|RedirectResponse
    {
        if ($this->restrictionService->isBlocked($request->user())) {
            return redirect()->route('group-ticketing.search')->with('warning', GroupBookingRestrictionService::BLOCK_THRESHOLD.' unpaid group reservations expired without payment. Your group booking access is temporarily restricted. Please contact support.');
        }

        $availability = $this->availabilityService->revalidate($inventory, 1);
        $inventory = $availability['inventory'];

        if (! $availability['ok']) {
            return redirect()->route('group-ticketing.search')->with(
                'warning',
                GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE,
            );
        }

        $card = $this->cardPresenter->present($inventory);
        $seatCount = (int) old('seat_count', 1);

        return view('frontend.group-ticketing.passengers', [
            'inventory' => $inventory,
            'card' => $card,
            'seatCount' => $seatCount,
            'checkoutCountries' => CountryList::forSelect(),
            'checkoutSummary' => $this->cardPresenter->buildCheckoutSummary($card, $seatCount),
            'activeStep' => 'passengers',
        ]);
    }

    public function storePassengers(GroupInventory $inventory, GroupTicketingPassengersRequest $request): RedirectResponse
    {
        if ($this->restrictionService->isBlocked($request->user())) {
            return redirect()->route('group-ticketing.search')->with('warning', 'Your group booking access is temporarily restricted.');
        }

        $seatCount = (int) $request->input('seat_count', 1);
        $availability = $this->availabilityService->revalidate($inventory, $seatCount);
        $inventory = $availability['inventory'];

        if (! $availability['ok']) {
            if ($availability['unavailable']) {
                return redirect()->route('group-ticketing.search')->with(
                    'warning',
                    GroupInventoryAvailabilityService::UNAVAILABLE_MESSAGE,
                );
            }

            return back()->withInput()->withErrors([
                'seat_count' => GroupInventoryAvailabilityService::insufficientSeatsMessage($availability['available_seats']),
            ]);
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
            return back()->withInput()->withErrors(['seat_count' => $exception->getMessage()]);
        }

        return redirect()->route('group-ticketing.booking.review', $booking);
    }

    public function review(GroupBooking $groupBooking): View|RedirectResponse
    {
        $this->authorizeBooking($groupBooking);

        if ($groupBooking->isExpired() && $groupBooking->isReleasable()) {
            return redirect()->route('group-ticketing.search')->with('warning', 'Your reservation has expired.');
        }

        $groupBooking->load(['passengers', 'inventory']);

        $card = $this->cardPresenter->present($groupBooking->inventory);

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

    public function confirmReview(GroupBooking $groupBooking): RedirectResponse
    {
        $this->authorizeBooking($groupBooking);

        try {
            $this->reservationService->createReservation($groupBooking);
        } catch (\Throwable $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return redirect()->route('group-ticketing.booking.payment', $groupBooking);
    }

    public function payment(GroupBooking $groupBooking): View|RedirectResponse
    {
        $this->authorizeBooking($groupBooking);

        if ($groupBooking->status === GroupBookingStatus::ManualPaymentPendingReview) {
            return redirect()->route('group-ticketing.booking.confirmation', $groupBooking);
        }

        if ($groupBooking->isExpired() && $groupBooking->isReleasable()) {
            $this->reservationService->releaseUnpaidBooking($groupBooking, 'unpaid_timeout');

            return redirect()->route('group-ticketing.search')->with('warning', 'Your reservation has expired.');
        }

        try {
            $this->reservationService->markPaymentPending($groupBooking);
        } catch (\Throwable) {
            return redirect()->route('group-ticketing.search')->with('warning', 'Reservation is no longer valid.');
        }

        $groupBooking->load(['inventory', 'passengers']);

        $booking = $groupBooking->fresh(['inventory', 'passengers']);
        $card = $this->cardPresenter->present($booking->inventory);

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

    public function submitPayment(GroupBooking $groupBooking, GroupTicketingPaymentRequest $request): RedirectResponse
    {
        $this->authorizeBooking($groupBooking);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('group-payment-proofs', 'public');
        }

        try {
            $this->reservationService->submitManualPayment($groupBooking, [
                'payment_method' => $request->input('payment_method'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_proof_path' => $proofPath,
            ]);
        } catch (\Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('public')->delete($proofPath);
            }

            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()->route('group-ticketing.booking.confirmation', $groupBooking);
    }

    public function confirmation(GroupBooking $groupBooking): View
    {
        $this->authorizeBooking($groupBooking);

        $booking = $groupBooking->load(['inventory', 'passengers']);
        $card = $this->cardPresenter->present($booking->inventory);

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

    private function authorizeBooking(GroupBooking $groupBooking): void
    {
        if ((int) $groupBooking->user_id !== (int) auth()->id()) {
            abort(403);
        }
    }
}
