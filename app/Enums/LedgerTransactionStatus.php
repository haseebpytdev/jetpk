<?php

namespace App\Enums;

enum LedgerTransactionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Posted = 'posted';
    case Voided = 'voided';
    case Reversed = 'reversed';
    case Failed = 'failed';
}
