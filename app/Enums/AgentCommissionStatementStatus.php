<?php

namespace App\Enums;

enum AgentCommissionStatementStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
