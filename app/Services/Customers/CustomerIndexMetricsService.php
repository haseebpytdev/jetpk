<?php

namespace App\Services\Customers;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single-query KPI aggregates for admin customer index (avoids N separate COUNT scans).
 */
final class CustomerIndexMetricsService
{
    /**
     * @param  Builder<User>  $scopedQuery  Already scoped to account_type=customer (+ agency when applicable).
     * @return array{total: int, active: int, google_linked: int, with_bookings: int, profile_incomplete: int}
     */
    public function registeredKpis(Builder $scopedQuery): array
    {
        $active = UserAccountStatus::Active->value;

        $row = (clone $scopedQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN users.status = ? THEN 1 ELSE 0 END) as active_count', [$active])
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1 FROM social_accounts sa
                WHERE sa.user_id = users.id AND sa.provider = 'google'
                LIMIT 1
            ) THEN 1 ELSE 0 END) as google_linked_count")
            ->selectRaw('SUM(CASE WHEN EXISTS (
                SELECT 1 FROM bookings b
                WHERE b.customer_id = users.id
                LIMIT 1
            ) THEN 1 ELSE 0 END) as with_bookings_count')
            ->selectRaw($this->profileIncompleteCaseSql().' as profile_incomplete_count')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'google_linked' => (int) ($row->google_linked_count ?? 0),
            'with_bookings' => (int) ($row->with_bookings_count ?? 0),
            'profile_incomplete' => (int) ($row->profile_incomplete_count ?? 0),
        ];
    }

    private function profileIncompleteCaseSql(): string
    {
        return 'SUM(CASE WHEN NOT EXISTS (
                SELECT 1 FROM user_profiles up WHERE up.user_id = users.id LIMIT 1
            ) OR EXISTS (
                SELECT 1 FROM user_profiles up
                WHERE up.user_id = users.id
                  AND (
                    up.date_of_birth IS NULL
                    OR up.nationality IS NULL
                    OR up.gender IS NULL
                    OR (up.passport_number IS NULL AND up.national_id IS NULL)
                  )
                LIMIT 1
            ) THEN 1 ELSE 0 END)';
    }
}
