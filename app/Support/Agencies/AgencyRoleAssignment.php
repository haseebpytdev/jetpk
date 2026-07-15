<?php

namespace App\Support\Agencies;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Models\AgencyUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Assigns agency_users.agency_role only — does not change permissions, account_type, or legacy role pivot.
 */
final class AgencyRoleAssignment
{
    public static function assign(User $target, int $agencyId, AgencyRole $newRole, User $actor): void
    {
        self::assertTargetEligible($target, $agencyId);
        self::assertAssignmentAllowed($target, $agencyId, $newRole, $actor);
        self::persistAgencyRole($target, $agencyId, $newRole);
    }

    public static function countStoredOwners(int $agencyId): int
    {
        return AgencyUser::query()
            ->where('agency_id', $agencyId)
            ->where('agency_role', AgencyRole::Owner->value)
            ->count();
    }

    /**
     * @return array<string, string>
     */
    public static function roleOptionsForActor(User $actor): array
    {
        $options = AgencyRole::options();

        if (! $actor->isPlatformAdmin()) {
            unset($options[AgencyRole::Owner->value]);
        }

        return $options;
    }

    protected static function assertTargetEligible(User $target, int $agencyId): void
    {
        if (! in_array($target->account_type, [AccountType::Agent, AccountType::AgentStaff], true)) {
            throw ValidationException::withMessages([
                'agency_role' => 'Agency role can only be assigned to agency owners or staff.',
            ]);
        }

        if ((int) $target->current_agency_id !== $agencyId) {
            throw ValidationException::withMessages([
                'agency_role' => 'This user does not belong to the selected agency.',
            ]);
        }
    }

    protected static function assertAssignmentAllowed(
        User $target,
        int $agencyId,
        AgencyRole $newRole,
        User $actor,
    ): void {
        if (! $actor->isPlatformAdmin() && $newRole === AgencyRole::Owner) {
            throw ValidationException::withMessages([
                'agency_role' => 'Only platform administrators can assign the owner role.',
            ]);
        }

        $currentRole = AgencyRoleResolver::resolve($target, $agencyId);

        if ($newRole !== AgencyRole::Owner && $currentRole === AgencyRole::Owner) {
            self::assertNotLastOwnerDemotion($target, $agencyId);
        }
    }

    protected static function assertNotLastOwnerDemotion(User $target, int $agencyId): void
    {
        $storedOwners = self::countStoredOwners($agencyId);

        $membership = AgencyUser::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $target->id)
            ->first();

        $storedRole = AgencyRole::fromNullable($membership?->agency_role);

        if ($storedRole === AgencyRole::Owner && $storedOwners <= 1) {
            throw ValidationException::withMessages([
                'agency_role' => 'Cannot change role: this user is the only stored owner for the agency.',
            ]);
        }

        if (
            $storedRole === null
            && $target->account_type === AccountType::Agent
            && $storedOwners === 0
            && self::countAgentUsersInAgency($agencyId) <= 1
        ) {
            throw ValidationException::withMessages([
                'agency_role' => 'Cannot change role: this user is the only agency owner account for the agency.',
            ]);
        }
    }

    protected static function countAgentUsersInAgency(int $agencyId): int
    {
        return User::query()
            ->where('current_agency_id', $agencyId)
            ->where('account_type', AccountType::Agent)
            ->count();
    }

    protected static function persistAgencyRole(User $target, int $agencyId, AgencyRole $newRole): void
    {
        $membership = AgencyUser::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $target->id)
            ->first();

        if ($membership !== null) {
            $membership->update(['agency_role' => $newRole->value]);

            return;
        }

        AgencyUser::query()->create([
            'agency_id' => $agencyId,
            'user_id' => $target->id,
            'role' => $target->account_type->value,
            'agency_role' => $newRole->value,
        ]);
    }
}
