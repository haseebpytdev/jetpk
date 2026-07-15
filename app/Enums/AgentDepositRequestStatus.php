<?php

namespace App\Enums;

enum AgentDepositRequestStatus: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
