<?php

namespace App\Enums;

enum HomepageFeaturedFareRefreshStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case NoResults = 'no_results';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Success => 'Success',
            self::NoResults => 'No results',
            self::Failed => 'Failed',
        };
    }
}
