<?php

namespace App\Http\Resources\Dashboard;

final class DashboardPermissionResource
{
    /**
     * @param  array<string, mixed>  $permission
     * @return array<string, mixed>
     */
    public static function fromCatalog(array $permission): array
    {
        return [
            'id' => $permission['id'],
            'key' => $permission['key'],
            'label' => $permission['label'],
            'category' => $permission['category'],
            'scope' => $permission['scope'],
            'channel' => $permission['channel'],
            'highRisk' => (bool) $permission['highRisk'],
            'description' => $permission['description'],
            'risk' => $permission['risk'],
            'assignedRoleCount' => $permission['assignedRoleCount'] ?? 0,
            'applicableAccountTypes' => $permission['applicableAccountTypes'] ?? [],
            'createdAt' => '2025-01-10T08:00:00.000Z',
            'updatedAt' => now()->toIso8601String(),
            'reviewFlags' => ['needsReview' => false],
        ];
    }
}
