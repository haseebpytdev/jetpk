<?php

namespace App\Support\Agencies;

use App\Enums\AccountType;
use App\Enums\AgencyRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Access\RolePermissionMatrix;
use Illuminate\Validation\ValidationException;

/**
 * Updates users.meta.agent_permissions only — never account_type or agency_users pivot fields.
 */
final class AgencyStaffPermissionAssignment
{
    public const SourceManual = 'manual';

    public const SourceRoleTemplate = 'role_template';

    /**
     * @param  list<mixed>|null  $permissions
     * @return list<string>
     */
    public static function assignManual(
        User $target,
        ?array $permissions,
        User $actor,
        int $agencyId,
    ): array {
        self::assertCanAssign($target, $actor, $agencyId);

        $normalized = RolePermissionMatrix::normalizeAgentPermissions($permissions);
        $old = self::currentPermissions($target);

        if ($normalized === $old) {
            return $normalized;
        }

        self::persistPermissions($target, $normalized);
        self::writeAudit($actor, $target, $agencyId, $old, $normalized, self::SourceManual);

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public static function assignFromTemplate(User $target, User $actor, int $agencyId): array
    {
        self::assertCanAssign($target, $actor, $agencyId);

        $agencyRole = AgencyRoleResolver::resolve($target, $agencyId);
        $normalized = AgencyRolePermissionMatrix::templatePermissionsForAgentStaff($agencyRole);

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'confirm_template_apply' => $agencyRole === AgencyRole::Owner
                    ? 'Owner role uses account-level access and is not applied as a staff permission template.'
                    : 'No staff permission template is available for this agency role.',
            ]);
        }

        $old = self::currentPermissions($target);

        if ($normalized === $old) {
            return $normalized;
        }

        self::persistPermissions($target, $normalized);
        self::writeAudit(
            $actor,
            $target,
            $agencyId,
            $old,
            $normalized,
            self::SourceRoleTemplate,
            $agencyRole->value,
        );

        return $normalized;
    }

    protected static function assertCanAssign(User $target, User $actor, int $agencyId): void
    {
        if ((int) $actor->id === (int) $target->id) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot update your own permission matrix.',
            ]);
        }

        if ($target->account_type !== AccountType::AgentStaff) {
            throw ValidationException::withMessages([
                'permissions' => 'Permission matrix can only be updated for agency staff accounts.',
            ]);
        }

        if ((int) $target->current_agency_id !== $agencyId) {
            throw ValidationException::withMessages([
                'permissions' => 'This user does not belong to the selected agency.',
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected static function currentPermissions(User $target): array
    {
        $permissions = $target->meta['agent_permissions'] ?? [];

        return is_array($permissions)
            ? RolePermissionMatrix::normalizeAgentPermissions($permissions)
            : [];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected static function persistPermissions(User $target, array $permissions): void
    {
        $meta = is_array($target->meta) ? $target->meta : [];
        $meta['agent_permissions'] = $permissions;
        $target->forceFill(['meta' => $meta])->save();
    }

    /**
     * @param  list<string>  $oldPermissions
     * @param  list<string>  $newPermissions
     */
    protected static function writeAudit(
        User $actor,
        User $target,
        int $agencyId,
        array $oldPermissions,
        array $newPermissions,
        string $source,
        ?string $agencyRole = null,
    ): void {
        $newValues = [
            'user_id' => $target->id,
            'agency_id' => $agencyId,
            'old_permissions' => $oldPermissions,
            'new_permissions' => $newPermissions,
            'source' => $source,
        ];

        if ($agencyRole !== null) {
            $newValues['agency_role'] = $agencyRole;
        }

        AuditLog::query()->create([
            'agency_id' => $agencyId,
            'user_id' => $actor->id,
            'action' => 'agent_permissions.updated',
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'properties' => [
                'old_values' => ['agent_permissions' => $oldPermissions],
                'new_values' => $newValues,
            ],
        ]);
    }
}
