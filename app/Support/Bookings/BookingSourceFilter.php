<?php

namespace App\Support\Bookings;

use Illuminate\Database\Eloquent\Builder;

/**
 * Guest / customer / agent classification for booking list queries.
 */
class BookingSourceFilter
{
    public static function resolve(?string $source, ?string $legacyAgentCustomer = null): ?string
    {
        $value = filled($source) ? $source : ($legacyAgentCustomer ?: null);

        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return in_array($value, ['guest', 'customer', 'agent'], true) ? $value : null;
    }

    public static function apply(Builder $q, ?string $source): void
    {
        if ($source === null) {
            return;
        }

        match ($source) {
            'guest' => $q->whereNull('customer_id')->whereNull('agent_id'),
            'customer' => $q->whereNotNull('customer_id')->whereNull('agent_id'),
            'agent' => $q->where(function (Builder $inner): void {
                $inner->whereNotNull('agent_id')->orWhere('source_channel', 'agent_portal');
            }),
            default => null,
        };
    }
}
