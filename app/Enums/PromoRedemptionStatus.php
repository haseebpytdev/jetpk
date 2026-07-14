<?php

namespace App\Enums;

enum PromoRedemptionStatus: string
{
    case Applied = 'applied';
    case Removed = 'removed';
    case Redeemed = 'redeemed';
    case Cancelled = 'cancelled';
}
