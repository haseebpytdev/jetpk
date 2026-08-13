<?php

namespace App\Services\Rbac;

use App\Models\AuditLog;
use App\Models\Role;
use App\Support\Dashboard\DashboardPermissionCatalog;
use App\Support\Rbac\RbacPermissionRegistry;
use Illuminate\Support\Facades\Schema;

final class RbacRolePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Role $role): array
    {
        $keys = $role->grantedPermissionKeys();
        $highRisk = count(array_filter(
            $keys,
            static fn (string $key): bool => DashboardPermissionCatalog::isHighRisk($key),
        ));
        $groups = array_values(array_unique(array_filter(array_map(
            static function (string $key): string {
                $parts = explode('.', $key, 2);

                return $parts[0] === 'staff' ? 'staff' : $parts[0];
            },
            $keys,
        ))));

        return [
            'id' => (string) $role->id,
            'key' => $role->slug,
            'name' => $role->name,
            'description' => (string) ($role->description ?? ''),
            'category' => $role->is_system ? 'system' : 'operations',
            'status' => 'active',
            'isSystem' => $role->is_system,
            'isProtected' => $role->is_protected,
            'assignedUserCount' => $role->users()->count(),
            'permissionCount' => count($keys),
            'highRiskPermissionCount' => $highRisk,
            'permissionGroups' => $groups,
            'permissionKeys' => $keys,
            'scope' => $role->isPlatformScoped() ? 'allRecords' : 'ownRecords',
            'agencyId' => $role->agency_id,
            'scopeKey' => $role->scope_key,
            'createdAt' => $role->created_at?->toIso8601String(),
            'updatedAt' => $role->updated_at?->toIso8601String(),
            'validationState' => 'valid',
            'reviewFlags' => ['needsReview' => false],
            'assignedUsers' => $role->users()->get(['users.id', 'users.name', 'users.email'])->map(
                static fn ($user): array => [
                    'id' => (string) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                ],
            )->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogPermissions(): array
    {
        $rows = [];
        foreach (RbacPermissionRegistry::all() as $key) {
            $fromCatalog = DashboardPermissionCatalog::find($key);
            $group = str_starts_with($key, 'staff.') ? 'staff' : explode('.', $key, 2)[0];
            $rows[] = [
                'key' => $key,
                'label' => $fromCatalog['label'] ?? $key,
                'category' => $fromCatalog['category'] ?? $group,
                'highRisk' => DashboardPermissionCatalog::isHighRisk($key),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(Role $role, int $limit = 20): array
    {
        if (! Schema::hasTable('audit_logs')) {
            return [];
        }

        return AuditLog::query()
            ->where('auditable_type', Role::class)
            ->where('auditable_id', $role->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(static fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actorId' => $log->user_id,
                'createdAt' => $log->created_at?->toIso8601String(),
                'properties' => $log->properties,
            ])
            ->all();
    }
}
