<?php

namespace App\Enums;

enum AgentCommissionEntryType: string
{
    case Earned = 'earned';
    case Adjustment = 'adjustment';
    case Payout = 'payout';
    case Reversal = 'reversal';
}
