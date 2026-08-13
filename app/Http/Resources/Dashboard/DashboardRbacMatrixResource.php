<?php

namespace App\Http\Resources\Dashboard;

use App\Models\Role;
use App\Services\Rbac\RbacRolePresenter;
use App\Support\Dashboard\DashboardPermissionCatalog;
use Illuminate\Support\Facades\Schema;

final class DashboardRbacMatrixResource
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?string $domain = null): array
    {
        $presenter = app(RbacRolePresenter::class);
        $roles = [];
        if (Schema::hasTable('roles')) {
            $roles = Role::query()->with('permissionRows')->orderBy('name')->get()
                ->map(static fn (Role $role): array => $presenter->present($role))
                ->all();
        }

        $catalog = $presenter->catalogPermissions();
        if ($domain !== null && $domain !== '' && $domain !== 'all') {
            $catalog = array_values(array_filter(
                $catalog,
                static fn (array $row): bool => ($row['category'] ?? '') === $domain,
            ));
        }
        $permissionKeys = array_map(static fn (array $row): string => (string) $row['key'], $catalog);

        $assignments = [];
        foreach ($roles as $role) {
            foreach ($permissionKeys as $key) {
                $assignments[] = [
                    'roleId' => (string) $role['id'],
                    'roleKey' => (string) $role['key'],
                    'roleName' => (string) $role['name'],
                    'permissionKey' => $key,
                    'assigned' => in_array($key, $role['permissionKeys'] ?? [], true),
                    'highRisk' => DashboardPermissionCatalog::isHighRisk($key),
                    'protected' => (bool) $role['isProtected'],
                    'scope' => (string) $role['scope'],
                    'channel' => 'all',
                ];
            }
        }

        return [
            'roles' => array_map(
                static fn (array $role): array => DashboardRoleResource::fromCatalog($role),
                $roles,
            ),
            'permissionKeys' => $permissionKeys,
            'assignments' => $assignments,
            'protectedRoleMetadata' => array_values(array_map(
                static fn (array $role): array => [
                    'roleId' => $role['id'],
                    'isProtected' => $role['isProtected'],
                ],
                $roles,
            )),
            'highRiskMarkers' => DashboardPermissionCatalog::highRiskKeys(),
            'scopeContext' => 'allRecords',
            'channelContext' => 'all',
        ];
    }
}
