<?php

namespace App\Enums;

enum CustomerQueryStatus: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case CallbackRequired = 'callback_required';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Converted = 'converted';
    case Closed = 'closed';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Qualified => 'Qualified',
            self::CallbackRequired => 'Callback required',
            self::Contacted => 'Contacted',
            self::FollowUp => 'Follow up',
            self::Converted => 'Converted',
            self::Closed => 'Closed',
            self::Invalid => 'Invalid',
        };
    }
}
