<?php

namespace App\Services\Rbac;

use App\Enums\AccountType;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionCatalog;
use App\Support\Rbac\RbacPermissionRegistry;
use App\Support\Staff\StaffPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RbacInstallService
{
    /**
     * @return array{roles: int, assignments: int, staff_meta_preserved: int, drift: int}
     */
    public function seedAndBackfill(): array
    {
        if (! Schema::hasTable('roles')) {
            return ['roles' => 0, 'assignments' => 0, 'staff_meta_preserved' => 0, 'drift' => 0];
        }

        return DB::transaction(function (): array {
            $before = $this->staffPermissionFingerprint();
            $this->seedSystemRoles();
            $assignments = $this->backfillAccountTypeAssignments();
            $staffMeta = User::query()->where('account_type', AccountType::Staff)
                ->whereNotNull('meta')
                ->count();
            $after = $this->staffPermissionFingerprint();
            $drift = $before === $after ? 0 : 1;

            return [
                'roles' => Role::query()->where('is_system', true)->count(),
                'assignments' => $assignments,
                'staff_meta_preserved' => $staffMeta,
                'drift' => $drift,
            ];
        });
    }

    public function seedSystemRoles(): void
    {
        $platformKeys = array_map(
            static fn (array $row): string => (string) $row['key'],
            DashboardPermissionCatalog::all(),
        );
        $platformKeys = array_values(array_unique(array_merge(
            $platformKeys,
            RbacPermissionRegistry::dashboardWriteKeys(),
        )));

        foreach ($this->systemRoleDefinitions($platformKeys) as $definition) {
            $role = Role::query()->updateOrCreate(
                [
                    'scope_key' => Role::SCOPE_PLATFORM,
                    'slug' => $definition['slug'],
                ],
                [
                    'agency_id' => null,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_system' => true,
                    'is_protected' => true,
                ],
            );

            RolePermission::query()->where('role_id', $role->id)->delete();
            foreach ($definition['keys'] as $key) {
                RolePermission::query()->create([
                    'role_id' => $role->id,
                    'permission_key' => $key,
                    'granted' => true,
                ]);
            }
        }
    }

    public function backfillAccountTypeAssignments(): int
    {
        $roles = Role::query()->where('is_system', true)->whereNull('agency_id')->get()->keyBy('slug');
        $count = 0;

        User::query()->orderBy('id')->each(function (User $user) use ($roles, &$count): void {
            $slug = $user->account_type?->value;
            if ($slug === null || ! isset($roles[$slug])) {
                return;
            }
            $role = $roles[$slug];
            if ($role->users()->where('users.id', $user->id)->exists()) {
                return;
            }
            $role->users()->attach($user->id, ['assigned_by' => null]);
            $count++;
        });

        return $count;
    }

    /**
     * @param  list<string>  $platformKeys
     * @return list<array{slug: string, name: string, description: string, keys: list<string>}>
     */
    private function systemRoleDefinitions(array $platformKeys): array
    {
        return [
            [
                'slug' => AccountType::PlatformAdmin->value,
                'name' => 'Platform Admin',
                'description' => 'Protected platform administration role.',
                'keys' => $platformKeys,
            ],
            [
                'slug' => AccountType::AgencyAdmin->value,
                'name' => 'Agency Admin',
                'description' => 'Legacy agency admin account type mapping.',
                'keys' => ['dashboard.view', 'bookings.view', 'payments.view', 'customers.view', 'agents.view', 'reports.view'],
            ],
            [
                'slug' => AccountType::Staff->value,
                'name' => 'Staff',
                'description' => 'Staff portal. Operational keys remain in users.meta.staff_permissions during dual-read.',
                'keys' => ['dashboard.view'],
            ],
            [
                'slug' => AccountType::Agent->value,
                'name' => 'Agent',
                'description' => 'Agency owner portal access.',
                'keys' => ['dashboard.view', 'bookings.view', 'payments.view', 'reports.view'],
            ],
            [
                'slug' => AccountType::AgentStaff->value,
                'name' => 'Agent Staff',
                'description' => 'Agency-scoped staff portal access.',
                'keys' => ['dashboard.view', 'bookings.view'],
            ],
            [
                'slug' => AccountType::Customer->value,
                'name' => 'Customer',
                'description' => 'Customer portal access. No platform management permissions.',
                'keys' => [],
            ],
        ];
    }

    /**
     * @return array<int, list<string>>
     */
    private function staffPermissionFingerprint(): array
    {
        $map = [];
        User::query()->where('account_type', AccountType::Staff)->orderBy('id')->each(function (User $user) use (&$map): void {
            $map[$user->id] = $user->staffPermissions();
        });

        return $map;
    }
}
