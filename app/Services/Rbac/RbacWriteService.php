<?php

namespace App\Services\Rbac;

use App\Enums\AccountType;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\Rbac\RbacGuardException;
use App\Support\Rbac\RbacPermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RbacWriteService
{
    /**
     * @param  list<string>  $permissionKeys
     */
    public function createCustomRole(User $actor, string $name, string $slug, ?int $agencyId, array $permissionKeys, ?string $description = null): Role
    {
        $this->assertPlatformAdmin($actor);
        $slug = $this->normalizeSlug($slug !== '' ? $slug : $name);
        $this->assertCustomScope($agencyId);
        $keys = $this->validatedKeys($permissionKeys);

        return DB::transaction(function () use ($actor, $name, $slug, $agencyId, $keys, $description): Role {
            $role = Role::query()->create([
                'agency_id' => $agencyId,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'is_system' => false,
                'is_protected' => false,
                'created_by' => $actor->id,
            ]);
            $this->syncPermissions($role, $keys);
            $this->audit($actor, $role, 'rbac.role_created', [], $this->snapshot($role));

            return $role->fresh(['permissionRows']) ?? $role;
        });
    }

    public function cloneRole(User $actor, Role $source, string $name, string $slug, ?int $agencyId = null): Role
    {
        $this->assertPlatformAdmin($actor);
        $targetAgency = $agencyId ?? $source->agency_id;
        $this->assertCustomScope($targetAgency);

        return $this->createCustomRole(
            $actor,
            $name,
            $slug,
            $targetAgency,
            $source->grantedPermissionKeys(),
            $source->description,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateCustomRole(User $actor, Role $role, array $attributes): Role
    {
        $this->assertPlatformAdmin($actor);
        $this->assertMutable($role);
        $before = $this->snapshot($role);

        if (isset($attributes['name'])) {
            $role->name = (string) $attributes['name'];
        }
        if (isset($attributes['description'])) {
            $role->description = (string) $attributes['description'];
        }
        if (isset($attributes['slug'])) {
            $role->slug = $this->normalizeSlug((string) $attributes['slug']);
        }
        $role->save();
        $this->audit($actor, $role, 'rbac.role_edited', $before, $this->snapshot($role));

        return $role->fresh(['permissionRows']) ?? $role;
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    public function syncRolePermissions(User $actor, Role $role, array $permissionKeys): Role
    {
        $this->assertPlatformAdmin($actor);
        $this->assertMutable($role);
        $keys = $this->validatedKeys($permissionKeys);
        $before = $this->snapshot($role);
        $this->syncPermissions($role, $keys);
        $role = $role->fresh(['permissionRows']) ?? $role;
        $this->audit($actor, $role, 'rbac.permissions_updated', $before, $this->snapshot($role));

        return $role;
    }

    public function assignUser(User $actor, Role $role, User $target): void
    {
        $this->assertPlatformAdmin($actor);
        $this->assertAssignmentScope($role, $target);
        if ($role->users()->where('users.id', $target->id)->exists()) {
            return;
        }

        $role->users()->attach($target->id, ['assigned_by' => $actor->id]);
        $this->audit($actor, $role, 'rbac.role_assigned', [], [
            'user_id' => $target->id,
            'role_id' => $role->id,
            'slug' => $role->slug,
        ]);
    }

    public function unassignUser(User $actor, Role $role, User $target): void
    {
        $this->assertPlatformAdmin($actor);
        if ($role->slug === 'platform_admin' && $role->is_system) {
            $this->assertNotLastPlatformAdmin($target);
            $this->assertNotSelfLockout($actor, $target);
        }

        $role->users()->detach($target->id);
        $this->audit($actor, $role, 'rbac.role_unassigned', [
            'user_id' => $target->id,
            'role_id' => $role->id,
        ], []);
    }

    public function deleteCustomRole(User $actor, Role $role): void
    {
        $this->assertPlatformAdmin($actor);
        $this->assertMutable($role);
        $before = $this->snapshot($role);
        $roleId = $role->id;
        $role->delete();
        $this->audit($actor, null, 'rbac.role_deleted', $before, [], $roleId);
    }

    private function assertPlatformAdmin(User $actor): void
    {
        if (! $actor->isPlatformAdmin()) {
            throw new RbacGuardException('Only Platform Admin can manage roles.', 403, 'rbac_not_platform_admin');
        }
    }

    private function assertMutable(Role $role): void
    {
        if ($role->is_system || $role->is_protected) {
            throw new RbacGuardException('Protected system roles cannot be edited or deleted.', 403, 'rbac_protected_role');
        }
    }

    private function assertCustomScope(?int $agencyId): void
    {
        if ($agencyId === null) {
            throw new RbacGuardException('Custom roles must belong to an agency. Platform scope is reserved for system roles.', 422, 'rbac_platform_custom_denied');
        }
    }

    private function assertAssignmentScope(Role $role, User $target): void
    {
        if ($role->isPlatformScoped()) {
            if (! $role->is_system) {
                throw new RbacGuardException('Only system roles may be platform-scoped.', 422, 'rbac_invalid_platform_role');
            }

            return;
        }

        if ((int) $target->current_agency_id !== (int) $role->agency_id) {
            throw new RbacGuardException('Agency-scoped roles cannot be assigned outside their agency.', 403, 'rbac_agency_isolation');
        }
    }

    private function assertNotLastPlatformAdmin(User $target): void
    {
        if (! $target->isPlatformAdmin()) {
            return;
        }

        $remaining = User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->where('id', '!=', $target->id)
            ->count();

        if ($remaining < 1) {
            throw new RbacGuardException('Cannot remove the last Platform Admin.', 403, 'rbac_last_admin');
        }
    }

    private function assertNotSelfLockout(User $actor, User $target): void
    {
        if ((int) $actor->id === (int) $target->id) {
            throw new RbacGuardException('A Platform Admin cannot remove their own Platform Admin role.', 403, 'rbac_self_lockout');
        }
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function validatedKeys(array $keys): array
    {
        $normalized = array_values(array_unique(array_filter(array_map('strval', $keys))));
        $unknown = RbacPermissionRegistry::unknown($normalized);
        if ($unknown !== []) {
            throw new RbacGuardException('Unknown permission keys: '.implode(', ', $unknown), 422, 'rbac_unknown_permission');
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $keys
     */
    private function syncPermissions(Role $role, array $keys): void
    {
        RolePermission::query()->where('role_id', $role->id)->delete();
        foreach ($keys as $key) {
            RolePermission::query()->create([
                'role_id' => $role->id,
                'permission_key' => $key,
                'granted' => true,
            ]);
        }
    }

    private function normalizeSlug(string $value): string
    {
        $slug = Str::slug($value, '_');
        if ($slug === '') {
            throw new RbacGuardException('Role slug is required.', 422, 'rbac_invalid_slug');
        }

        return substr($slug, 0, 64);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Role $role): array
    {
        $fresh = $role->relationLoaded('permissionRows') ? $role : $role->fresh(['permissionRows']);

        return [
            'id' => $fresh?->id ?? $role->id,
            'name' => $fresh?->name ?? $role->name,
            'slug' => $fresh?->slug ?? $role->slug,
            'agency_id' => $fresh?->agency_id ?? $role->agency_id,
            'is_system' => (bool) ($fresh?->is_system ?? $role->is_system),
            'is_protected' => (bool) ($fresh?->is_protected ?? $role->is_protected),
            'permission_keys' => $fresh?->grantedPermissionKeys() ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private function audit(User $actor, ?Role $role, string $action, array $old, array $new, ?int $auditableId = null): void
    {
        AuditLog::query()->create([
            'agency_id' => $role?->agency_id ?? $actor->current_agency_id,
            'user_id' => $actor->id,
            'action' => $action,
            'auditable_type' => Role::class,
            'auditable_id' => $auditableId ?? $role?->id ?? 0,
            'properties' => [
                'old_values' => $old,
                'new_values' => $new,
                'scope_key' => $role?->scope_key,
            ],
        ]);
    }
}
