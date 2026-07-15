<?php

namespace App\Http\Controllers\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentTransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Services\Customer\GuestBookingAccessService;
use App\Services\Payments\PaymentTransactionService;
use App\Support\PublicBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;

class AbhiPayPaymentController extends Controller
{
    public function __construct(
        protected PaymentTransactionService $paymentTransactionService,
        protected GuestBookingAccessService $guestAccessService,
    ) {}

    public function start(Request $request, Booking $booking, ?string $token = null): RedirectResponse
    {
        $this->authorizeBookingPaymentStart($request, $booking, $token);

        if ($booking->status === BookingStatus::Cancelled) {
            return redirect()->route('booking.confirmation')
                ->withErrors(['payment' => 'This booking is cancelled and cannot be paid online.']);
        }

        if (! $this->paymentTransactionService->isAbhiPayAvailableForBooking($booking)) {
            return $this->paymentStartErrorRedirect($request, $booking, $token, 'Online card payment is not available right now.');
        }

        try {
            $transaction = $this->paymentTransactionService->createAbhiPayTransaction(
                $booking,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return $this->paymentStartErrorRedirect($request, $booking, $token, $e->getMessage());
        }

        return redirect()->away((string) $transaction->gateway_payment_url);
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $transaction = $this->paymentTransactionService->processCallback($request);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('payments.decline')->with('payment_message', 'We could not verify your payment. Please contact support.');
        }

        return $this->redirectForTransaction($transaction);
    }

    public function success(Request $request): View
    {
        return $this->resultView('success', $request);
    }

    public function cancel(Request $request): View
    {
        return $this->resultView('cancel', $request);
    }

    public function decline(Request $request): View
    {
        return $this->resultView('decline', $request);
    }

    protected function authorizeBookingPaymentStart(Request $request, Booking $booking, ?string $routeToken = null): void
    {
        $user = $request->user();
        if ($user !== null) {
            Gate::authorize('view', $booking);

            return;
        }

        $token = (string) ($routeToken ?? $request->input('guest_token', ''));
        if ($token !== '' && $this->guestAccessService->validateToken($booking, $token)) {
            return;
        }

        abort(403);
    }

    protected function redirectForTransaction(PaymentTransaction $transaction): RedirectResponse
    {
        $booking = $transaction->booking;
        $query = [];
        if ($booking !== null) {
            $query['booking'] = $booking->booking_reference ?? $booking->id;
        }
        $query['reference'] = $transaction->client_transaction_id;

        if ($transaction->status === PaymentTransactionStatus::Paid) {
            return redirect()->route('payments.success', $query);
        }

        if (in_array($transaction->status, [
            PaymentTransactionStatus::Cancelled,
            PaymentTransactionStatus::Declined,
            PaymentTransactionStatus::Expired,
        ], true)) {
            return redirect()->route('payments.cancel', $query);
        }

        if (in_array($transaction->status, [
            PaymentTransactionStatus::Failed,
            PaymentTransactionStatus::VerificationFailed,
        ], true)) {
            return redirect()->route('payments.decline', $query);
        }

        return redirect()->route('payments.success', $query)
            ->with('payment_message', 'Payment received. Verification is still in progress.');
    }

    protected function resultView(string $type, Request $request): View
    {
        $reference = (string) $request->query('reference', '');
        $bookingReference = (string) $request->query('booking', '');
        $transaction = filled($reference)
            ? PaymentTransaction::query()->where('client_transaction_id', $reference)->first()
            : null;

        $title = match ($type) {
            'success' => 'Payment successful',
            'cancel' => 'Payment cancelled',
            default => 'Payment not completed',
        };

        $message = (string) ($request->session()->get('payment_message') ?? match ($type) {
            'success' => 'Thank you. Your payment has been received and will be reflected on your booking shortly.',
            'cancel' => 'Your payment was cancelled. You can try again from your booking page.',
            default => 'Your payment could not be completed. You may try again or use a manual payment method.',
        });

        return view('frontend.payments.result', [
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'bookingReference' => $bookingReference !== ''
                ? $bookingReference
                : ($transaction?->booking?->booking_reference),
            'paymentReference' => $reference !== '' ? $reference : $transaction?->client_transaction_id,
            'paymentStatus' => $transaction?->status?->value,
            'gatewayOrderId' => $transaction?->gateway_order_id,
            'paidAt' => $transaction?->paid_at?->format('j M Y, g:i A'),
        ]);
    }

    protected function paymentStartErrorRedirect(
        Request $request,
        Booking $booking,
        ?string $token,
        string $message,
    ): RedirectResponse {
        if ($request->session()->get(PublicBooking::SESSION_BOOKING_ID) === $booking->id) {
            return redirect()->route('booking.confirmation')->withErrors(['payment' => $message]);
        }

        if ($request->user() !== null) {
            return redirect()->route('customer.bookings.show', $booking)->withErrors(['payment' => $message]);
        }

        if ($token !== '' && $this->guestAccessService->validateToken($booking, $token)) {
            return redirect()->route('guest.bookings.show', ['booking' => $booking, 'token' => $token])
                ->withErrors(['payment' => $message]);
        }

        return back()->withErrors(['payment' => $message]);
    }
}
