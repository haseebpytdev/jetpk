<?php

namespace App\Enums;

enum AgentWalletTransactionType: string
{
    case DepositRequest = 'deposit_request';
    case DepositApproved = 'deposit_approved';
    case DepositRejected = 'deposit_rejected';
    case AdminCredit = 'admin_credit';
    case AdminDebit = 'admin_debit';
    case BookingHold = 'booking_hold';
    case BookingRelease = 'booking_release';
    case Adjustment = 'adjustment';
    case ManualCredit = 'manual_credit';
    case ManualDebit = 'manual_debit';
}
