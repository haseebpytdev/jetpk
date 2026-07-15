<?php

namespace App\Enums;

enum LedgerTransactionType: string
{
    case AgencyDepositApproved = 'agency_deposit_approved';
    case BookingPaymentVerified = 'booking_payment_verified';
    case BookingRefundApproved = 'booking_refund_approved';
    case BookingRefundPaid = 'booking_refund_paid';
    case AgencyCommissionEarned = 'agency_commission_earned';
    case MarkupRevenueRecognized = 'markup_revenue_recognized';
    case WalletAdminCredit = 'wallet_admin_credit';
    case WalletAdminDebit = 'wallet_admin_debit';
    case WalletBookingHold = 'wallet_booking_hold';
    case WalletBookingRelease = 'wallet_booking_release';
    case WalletAdjustment = 'wallet_adjustment';
    case ManualWalletCredit = 'manual_wallet_credit';
    case ManualWalletDebit = 'manual_wallet_debit';
    case ManualWalletCreditReversal = 'manual_wallet_credit_reversal';
    case ManualWalletDebitReversal = 'manual_wallet_debit_reversal';
    case Reversal = 'reversal';
}
