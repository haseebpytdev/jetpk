<?php

namespace App\Enums;

enum BrandingAssetProcessStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Accepted = 'accepted';
    case Discarded = 'discarded';
}
