<?php

namespace App\Enums;

enum AgentWalletTransactionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Posted = 'posted';
    case Void = 'void';
}
