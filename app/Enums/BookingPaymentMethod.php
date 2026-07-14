<?php

namespace App\Enums;

enum BookingPaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case CardManual = 'card_manual';
    case Easypaisa = 'easypaisa';
    case Jazzcash = 'jazzcash';
    case AbhiPay = 'abhipay';
    case Other = 'other';
}
