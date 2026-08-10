<?php

namespace App\Http\Resources\Dashboard;

use App\Models\BookingPayment;
use App\Models\User;
use App\Support\BackOffice\BackOfficeCapabilitiesPresenter;
use App\Support\Dashboard\DashboardMoneyPresenter;
use App\Support\Bookings\BookingListPresenter;

final class DashboardPaymentResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(BookingPayment $payment, ?User $viewer = null): array
    {
        $payment->loadMissing(['booking.passengers', 'booking.contact', 'booking.fareBreakdown', 'booking.agent.user', 'booking.verifiedPayments', 'payer']);
        $booking = $payment->booking;
        $row = $booking ? BookingListPresenter::toListRow($booking) : [];
        $bookingSummary = $booking ? DashboardBookingResource::fromModel($booking) : null;

        $method = strtolower((string) ($payment->method?->value ?? 'cash'));
        $status = strtolower((string) ($payment->status?->value ?? 'pending'));
        $storedPaymentCurrency = DashboardMoneyPresenter::normalizeIsoCurrency($payment->currency);
        $fareCurrency = DashboardMoneyPresenter::normalizeIsoCurrency($booking?->fareBreakdown?->currency);
        $bookingCurrency = DashboardMoneyPresenter::normalizeIsoCurrency($booking?->currency);
        $paymentCurrency = $storedPaymentCurrency !== ''
            ? $storedPaymentCurrency
            : ($fareCurrency !== '' ? $fareCurrency : $bookingCurrency);
        $currencySource = match (true) {
            $storedPaymentCurrency !== '' => 'payment.currency',
            $fareCurrency !== '' => 'fareBreakdown.currency',
            $bookingCurrency !== '' => 'booking.currency',
            default => null,
        };
        $grossMinor = (int) round((float) $payment->amount);
        $grossMoney = DashboardMoneyPresenter::presentMinorUnits($grossMinor, $paymentCurrency ?: null, $currencySource);
        $paidMinor = $status === 'verified' ? $grossMinor : 0;
        $paidMoney = DashboardMoneyPresenter::presentMinorUnits($paidMinor, $paymentCurrency ?: null, $currencySource);
        $outstandingMinor = $status === 'verified' ? 0 : $grossMinor;
        $outstandingMoney = DashboardMoneyPresenter::presentMinorUnits($outstandingMinor, $paymentCurrency ?: null, $currencySource);

        return [
            'transactionId' => 'TXN-'.$payment->id,
            'paymentId' => 'PAY-'.$payment->id,
            'laravelPaymentId' => (string) $payment->id,
            'bookingId' => $booking ? DashboardBookingResource::publicId($booking) : (string) $payment->booking_id,
            'pnr' => (string) ($row['pnr'] ?? ''),
            'supplierReference' => (string) ($row['supplier_reference'] ?? '') ?: null,
            'customerName' => (string) ($row['customer_name'] ?? ($payment->payer?->name ?? 'Guest')),
            'customerEmail' => $bookingSummary['customerEmail'] ?? '—',
            'customerPhone' => $bookingSummary['customerPhone'] ?? '—',
            'transactionDate' => ($payment->submitted_at ?? $payment->created_at)?->toIso8601String() ?? '',
            'bookingDate' => $booking?->created_at?->format('Y-m-d') ?? '',
            'currency' => $grossMoney['currency'] ?? '',
            'currencyStatus' => $grossMoney['currencyStatus'],
            'currencySource' => $grossMoney['currencySource'],
            'grossAmount' => $grossMoney['amountMinor'],
            'grossMoney' => $grossMoney,
            'paidAmount' => $paidMoney['amountMinor'],
            'paidMoney' => $paidMoney,
            'outstandingAmount' => $outstandingMoney['amountMinor'],
            'outstandingMoney' => $outstandingMoney,
            'refundedAmount' => 0,
            'feeAmount' => 0,
            'netAmount' => $grossMoney['amountMinor'],
            'netMoney' => $grossMoney,
            'paymentMethod' => self::mapMethod($method),
            'paymentChannel' => self::mapChannel($booking),
            'transactionType' => 'payment',
            'paymentStatus' => self::mapLedgerStatus($status, $booking),
            'transactionStatus' => self::mapTransactionStatus($status),
            'reconciliationStatus' => self::mapReconciliation($status),
            'gatewayReference' => null,
            'bankReference' => self::safeReference($payment->payment_reference),
            'manualReference' => self::safeReference($payment->payment_reference),
            'sourceOrAgent' => $bookingSummary['agentOrSource'] ?? '—',
            'createdAt' => $payment->created_at?->toIso8601String() ?? '',
            'updatedAt' => $payment->updated_at?->toIso8601String() ?? '',
            'auditNote' => '',
            'capabilities' => $viewer !== null
                ? app(BackOfficeCapabilitiesPresenter::class)->presentBookingPaymentCapabilities($viewer, $payment)
                : null,
        ];
    }

    protected static function mapMethod(string $method): string
    {
        return match ($method) {
            'card', 'gateway' => 'card',
            'bank_transfer', 'bank' => 'bank_transfer',
            'wallet' => 'wallet',
            'office', 'cash' => 'cash',
            default => 'cash',
        };
    }

    protected static function mapChannel(?\App\Models\Booking $booking): string
    {
        if ($booking === null) {
            return 'web';
        }

        return filled($booking->agent_id) ? 'agent' : 'web';
    }

    protected static function mapLedgerStatus(string $status, ?\App\Models\Booking $booking): string
    {
        if ($booking !== null) {
            return DashboardBookingResource::fromModel($booking)['paymentStatus'];
        }

        return match ($status) {
            'verified' => 'paid',
            'rejected' => 'failed',
            'pending', 'submitted' => 'pending',
            default => 'unpaid',
        };
    }

    protected static function mapTransactionStatus(string $status): string
    {
        return match ($status) {
            'verified' => 'succeeded',
            'rejected' => 'failed',
            'pending', 'submitted' => 'pending',
            default => 'cancelled',
        };
    }

    protected static function mapReconciliation(string $status): string
    {
        return match ($status) {
            'verified' => 'reconciled',
            'rejected' => 'disputed',
            'submitted', 'pending' => 'pending_review',
            default => 'unreconciled',
        };
    }

    protected static function safeReference(?string $reference): ?string
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        if (preg_match('/\d{12,}/', $reference) === 1) {
            return 'REF-***'.substr($reference, -4);
        }

        return $reference;
    }
}
