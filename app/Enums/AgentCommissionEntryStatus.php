<?php

namespace App\Enums;

enum AgentCommissionEntryStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
