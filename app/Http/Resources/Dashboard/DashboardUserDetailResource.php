<?php

namespace App\Http\Resources\Dashboard;

use App\Models\User;

final class DashboardUserDetailResource
{
    /**
     * @return array<string, mixed>
     */
    public static function fromModel(User $user): array
    {
        $base = DashboardUserResource::fromModel($user);
        $role = DashboardRoleCatalog::find((string) ($base['assignedRoleIds'][0] ?? 'JP-ROL-0002'));

        return [
            ...$base,
            'assignedRoles' => [[
                'roleId' => (string) ($role['id'] ?? 'JP-ROL-0002'),
                'roleName' => (string) ($role['name'] ?? 'Dashboard User'),
                'scope' => (string) ($role['scope'] ?? 'allRecords'),
                'assignedAt' => $user->created_at?->toIso8601String(),
                'assignedBy' => 'system',
            ]],
            'effectiveAccess' => [
                'roleLabels' => [(string) ($role['name'] ?? 'Dashboard User')],
                'permissionGroups' => $role['permissionGroups'] ?? ['dashboard'],
                'highRiskPermissions' => array_values(array_filter(
                    $role['permissionKeys'] ?? [],
                    static fn (string $key): bool => str_contains($key, 'assign') || str_contains($key, 'export'),
                )),
                'scope' => (string) ($role['scope'] ?? 'allRecords'),
                'previewOnly' => true,
            ],
            'validationIssues' => [],
        ];
    }
}
