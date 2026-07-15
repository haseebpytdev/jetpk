<?php

namespace App\Support\Bookings;

use App\Enums\BookingDocumentType;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\BookingPayment;
use App\Support\Payments\BookingPayableResolver;
use Illuminate\Support\Collection;

/**
 * Portal-safe payment summary and document availability for booking detail views.
 */
class BookingPaymentSummaryPresenter
{
    /**
     * @return array{
     *     total: float,
     *     fare_total: float,
     *     customer_payable: float,
     *     promo_code: string|null,
     *     promo_discount: float,
     *     payable_before_promo: float,
     *     amount_paid: float,
     *     balance_due: float,
     *     currency: string,
     *     status_code: string,
     *     status_label: string,
     *     status_meaning: string,
     *     proof_status: string,
     *     can_upload_proof: bool,
     *     show_awaiting_review: bool,
     *     show_verified: bool,
     *     show_rejected_resubmit: bool,
     *     latest_proof: array<string, mixed>|null,
     *     last_activity_at: string|null,
     *     last_activity_label: string|null,
     *     documents: list<array{key: string, label: string, status: string, available: bool, document: BookingDocument|null, unavailable_message: string, agent_note: string|null}>
     * }
     */
    public static function forBooking(Booking $booking, bool $gateAllowsProofUpload = true, string $audience = 'customer'): array
    {
        $payments = $booking->relationLoaded('payments')
            ? $booking->payments
            : $booking->payments()->get();

        $total = BookingPayableResolver::fareTotal($booking);
        $customerPayable = BookingPayableResolver::customerPayableTotal($booking);
        $promoDiscount = BookingPayableResolver::promoDiscount($booking);
        $amountPaid = (float) ($booking->amount_paid ?? 0);
        $balanceDue = $booking->balance_due !== null
            ? (float) $booking->balance_due
            : BookingPayableResolver::balanceDue($booking);
        $currency = (string) ($booking->currency ?? 'PKR');

        $pendingProof = $payments->first(
            fn (BookingPayment $p) => in_array((string) $p->status->value, ['submitted', 'pending'], true)
        );
        $latestPayment = $payments->sortByDesc(fn (BookingPayment $p) => $p->created_at?->timestamp ?? 0)->first();
        $latestRejected = $payments
            ->filter(fn (BookingPayment $p) => (string) $p->status->value === 'rejected')
            ->sortByDesc(fn (BookingPayment $p) => $p->rejected_at?->timestamp ?? $p->updated_at?->timestamp ?? 0)
            ->first();

        $bookingPaymentStatus = strtolower((string) ($booking->payment_status ?? 'unpaid'));
        $refundStatus = strtolower((string) ($booking->refund_status ?? 'none'));

        $statusCode = self::resolveDisplayStatusCode(
            $bookingPaymentStatus,
            $refundStatus,
            $pendingProof !== null,
            $latestRejected,
            $balanceDue,
            $amountPaid,
            $customerPayable,
        );

        $operational = PaymentOperationalStatus::fromValue($statusCode === 'proof_under_review' ? 'submitted' : $statusCode);

        $canUpload = self::canUploadProof($booking, $gateAllowsProofUpload, $payments);
        $showAwaiting = $pendingProof !== null;
        $showVerified = $bookingPaymentStatus === 'paid'
            || ($customerPayable > 0 && $balanceDue <= 0 && $amountPaid >= $customerPayable);
        $showRejected = $latestRejected !== null
            && ! $showAwaiting
            && $canUpload;

        [$lastAt, $lastLabel] = self::lastPaymentActivity($payments);

        return [
            'total' => $total,
            'fare_total' => $total,
            'customer_payable' => $customerPayable,
            'promo_code' => filled($booking->promo_code) ? (string) $booking->promo_code : null,
            'promo_discount' => $promoDiscount,
            'payable_before_promo' => BookingPayableResolver::payableBeforePromo($booking),
            'amount_paid' => $amountPaid,
            'balance_due' => $balanceDue,
            'currency' => $currency,
            'status_code' => $statusCode,
            'status_label' => self::displayStatusLabel($statusCode),
            'status_meaning' => $operational['meaning'],
            'proof_status' => self::proofStatusLabel($pendingProof, $latestRejected, $showVerified),
            'can_upload_proof' => $canUpload,
            'show_awaiting_review' => $showAwaiting,
            'show_verified' => $showVerified,
            'show_rejected_resubmit' => $showRejected,
            'latest_proof' => self::safeProofMetadata($latestPayment),
            'last_activity_at' => $lastAt,
            'last_activity_label' => $lastLabel,
            'documents' => self::documentsForPortal($booking, $audience),
        ];
    }

    /**
     * @param  Collection<int, BookingPayment>  $payments
     */
    public static function canUploadProof(Booking $booking, bool $gateAllowsProofUpload = true, $payments = null): bool
    {
        if (! $gateAllowsProofUpload) {
            return false;
        }

        if ($booking->status === BookingStatus::Cancelled) {
            return false;
        }

        $payments ??= $booking->relationLoaded('payments')
            ? $booking->payments
            : $booking->payments()->get();

        $hasPendingProof = $payments->contains(
            fn (BookingPayment $p) => in_array((string) $p->status->value, ['submitted', 'pending'], true)
        );
        if ($hasPendingProof) {
            return false;
        }

        $paymentStatus = strtolower((string) ($booking->payment_status ?? 'unpaid'));

        return in_array($paymentStatus, ['unpaid', 'partial', 'rejected'], true)
            || (float) ($booking->balance_due ?? 0) > 0;
    }

    protected static function resolveDisplayStatusCode(
        string $bookingPaymentStatus,
        string $refundStatus,
        bool $hasPendingProof,
        ?BookingPayment $latestRejected,
        float $balanceDue,
        float $amountPaid,
        float $customerPayable,
    ): string {
        if (in_array($refundStatus, ['refunded', 'partial_refund', 'pending'], true)) {
            return $refundStatus === 'pending' ? 'refund_pending' : $refundStatus;
        }

        if ($hasPendingProof) {
            return 'proof_under_review';
        }

        if ($bookingPaymentStatus === 'paid' || ($customerPayable > 0 && $balanceDue <= 0 && $amountPaid >= $customerPayable)) {
            return 'paid';
        }

        if ($bookingPaymentStatus === 'partial' || ($amountPaid > 0 && $balanceDue > 0)) {
            return 'partial';
        }

        if ($latestRejected !== null && $balanceDue > 0) {
            return 'rejected';
        }

        return in_array($bookingPaymentStatus, ['rejected', 'refunded', 'partial_refund'], true)
            ? $bookingPaymentStatus
            : 'unpaid';
    }

    protected static function displayStatusLabel(string $code): string
    {
        return match ($code) {
            'proof_under_review' => 'Proof submitted — under review',
            'partial' => 'Partially paid',
            'paid' => 'Paid — verified',
            'rejected' => 'Payment proof rejected',
            'refunded' => 'Refunded',
            'partial_refund' => 'Partially refunded',
            'refund_pending' => 'Refund pending',
            default => 'Unpaid',
        };
    }

    protected static function proofStatusLabel(?BookingPayment $pending, ?BookingPayment $rejected, bool $verified): string
    {
        if ($verified) {
            return 'verified';
        }
        if ($pending !== null) {
            return 'under_review';
        }
        if ($rejected !== null) {
            return 'rejected';
        }

        return 'none';
    }

    /**
     * @param  Collection<int, BookingPayment>  $payments
     * @return array{0: string|null, 1: string|null}
     */
    protected static function lastPaymentActivity($payments): array
    {
        $latest = null;
        $label = null;

        foreach ($payments as $payment) {
            foreach ([
                ['at' => $payment->verified_at, 'label' => 'Payment verified'],
                ['at' => $payment->rejected_at, 'label' => 'Payment proof rejected'],
                ['at' => $payment->submitted_at, 'label' => 'Payment proof submitted'],
            ] as $candidate) {
                if ($candidate['at'] === null) {
                    continue;
                }
                if ($latest === null || $candidate['at']->gt($latest)) {
                    $latest = $candidate['at'];
                    $label = $candidate['label'];
                }
            }
        }

        return [$latest?->format('j M Y, g:i A'), $label];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function safeProofMetadata(?BookingPayment $payment): ?array
    {
        if ($payment === null) {
            return null;
        }

        return [
            'method' => str_replace('_', ' ', (string) $payment->method->value),
            'amount' => (float) $payment->amount,
            'currency' => (string) ($payment->currency ?? 'PKR'),
            'payment_reference' => filled($payment->payment_reference) ? (string) $payment->payment_reference : null,
            'status' => (string) $payment->status->value,
            'submitted_at' => $payment->submitted_at?->format('j M Y, g:i A'),
            'has_proof_file' => filled($payment->proof_path),
        ];
    }

    /**
     * Portal document rows for customer/agent/guest booking detail.
     * Only returns downloadable rows (no receipt/refund/cancellation slots).
     *
     * @return list<array{key: string, label: string, status: string, available: bool, document: BookingDocument|null, unavailable_message: string, agent_note: string|null}>
     */
    public static function documentsForPortal(Booking $booking, string $audience = 'customer'): array
    {
        $generated = $booking->relationLoaded('documents')
            ? $booking->documents->filter(fn ($d) => $d->status->value === 'generated' && $d->file_path !== null)
            : $booking->documents()->where('status', 'generated')->whereNotNull('file_path')->get();

        $pick = fn (BookingDocumentType $type): ?BookingDocument => $generated->first(
            fn ($d) => $d->document_type === $type
        );

        $eTicket = $pick(BookingDocumentType::BookingConfirmation);
        $itinerary = $pick(BookingDocumentType::TicketItinerary);
        $invoice = $pick(BookingDocumentType::Invoice);

        $rows = [];

        if ($eTicket !== null) {
            $rows[] = self::portalDocumentRow('e_ticket', 'E-ticket', $eTicket, $audience);
        } elseif ($itinerary !== null) {
            $rows[] = self::portalDocumentRow('itinerary', 'Itinerary', $itinerary, $audience);
        }

        if ($invoice !== null) {
            $rows[] = self::portalDocumentRow('invoice', 'Invoice', $invoice, $audience);
        }

        return $rows;
    }

    /**
     * @return array{key: string, label: string, status: string, available: bool, document: BookingDocument, unavailable_message: string, agent_note: string|null}
     */
    protected static function portalDocumentRow(
        string $key,
        string $label,
        BookingDocument $document,
        string $audience,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => 'available',
            'available' => true,
            'document' => $document,
            'unavailable_message' => '',
            'agent_note' => $audience === 'agent'
                ? 'Generated — customer can download from their booking portal.'
                : null,
        ];
    }
}
