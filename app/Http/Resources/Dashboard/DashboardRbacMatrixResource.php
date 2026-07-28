<?php

namespace App\Http\Resources\Dashboard;

use App\Support\Dashboard\DashboardPermissionCatalog;
use App\Support\Dashboard\DashboardRoleCatalog;

final class DashboardRbacMatrixResource
{
    /**
     * @return array<string, mixed>
     */
    public static function build(?string $domain = null): array
    {
        $roles = array_map(
            static fn (array $role): array => DashboardRoleResource::fromCatalog($role),
            DashboardRoleCatalog::all(),
        );
        $permissionKeys = array_map(
            static fn (array $permission): string => (string) $permission['key'],
            DashboardPermissionCatalog::all(),
        );

        return [
            'roles' => $roles,
            'permissionKeys' => $permissionKeys,
            'assignments' => DashboardRoleCatalog::matrix($domain),
            'protectedRoleMetadata' => array_values(array_map(
                static fn (array $role): array => [
                    'roleId' => $role['id'],
                    'isProtected' => $role['isProtected'],
                ],
                DashboardRoleCatalog::all(),
            )),
            'highRiskMarkers' => DashboardPermissionCatalog::highRiskKeys(),
            'scopeContext' => 'allRecords',
            'channelContext' => 'all',
        ];
    }
}
