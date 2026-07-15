<?php

namespace App\Services\GroupTicketing;

use App\Models\AuditLog;
use App\Models\GroupBooking;
use App\Models\GroupBookingUserRestriction;
use App\Models\User;

/**
 * Tracks unpaid group booking releases and blocks users after 3 strikes.
 */
class GroupBookingRestrictionService
{
    public const BLOCK_THRESHOLD = 3;

    public function isBlocked(User $user): bool
    {
        $restriction = GroupBookingUserRestriction::query()
            ->where('user_id', $user->id)
            ->first();

        return $restriction !== null && $restriction->isBlocked();
    }

    public function unpaidReleaseCount(User $user): int
    {
        return (int) (GroupBookingUserRestriction::query()
            ->where('user_id', $user->id)
            ->value('unpaid_release_count') ?? 0);
    }

    public function recordUnpaidRelease(User $user, GroupBooking $booking): GroupBookingUserRestriction
    {
        $restriction = GroupBookingUserRestriction::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['unpaid_release_count' => 0],
        );

        $restriction->unpaid_release_count = (int) $restriction->unpaid_release_count + 1;
        $restriction->last_release_at = now();

        if ($restriction->unpaid_release_count >= self::BLOCK_THRESHOLD && $restriction->blocked_at === null) {
            $restriction->blocked_at = now();
        }

        $restriction->save();

        return $restriction->fresh();
    }

    public function reset(User $user, User $admin, ?string $note = null): GroupBookingUserRestriction
    {
        $restriction = GroupBookingUserRestriction::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['unpaid_release_count' => 0],
        );

        $oldCount = (int) $restriction->unpaid_release_count;

        $restriction->update([
            'unpaid_release_count' => 0,
            'blocked_at' => null,
            'reset_at' => now(),
            'reset_by' => $admin->id,
            'reset_note' => $note,
        ]);

        AuditLog::query()->create([
            'agency_id' => null,
            'user_id' => $admin->id,
            'action' => 'group_booking.restriction_reset',
            'auditable_type' => GroupBookingUserRestriction::class,
            'auditable_id' => $restriction->id,
            'properties' => [
                'old_values' => ['unpaid_release_count' => $oldCount],
                'new_values' => [
                    'unpaid_release_count' => 0,
                    'reset_note' => $note,
                    'target_user_id' => $user->id,
                ],
            ],
        ]);

        return $restriction->fresh();
    }
}
