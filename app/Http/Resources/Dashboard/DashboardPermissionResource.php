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
        $key = (string) ($permission['key'] ?? '');
        $category = (string) ($permission['category'] ?? 'dashboard');
        $action = (string) ($permission['action'] ?? (str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : 'view'));

        return [
            'id' => $permission['id'] ?? $key,
            'key' => $key,
            'label' => $permission['label'] ?? $key,
            'category' => $category,
            'domain' => $category,
            'domainLabel' => ucfirst(str_replace('_', ' ', $category)),
            'action' => $action,
            'scope' => $permission['scope'] ?? 'allRecords',
            'supportedScopes' => [(string) ($permission['scope'] ?? 'allRecords')],
            'channel' => $permission['channel'] ?? 'all',
            'highRisk' => (bool) ($permission['highRisk'] ?? false),
            'isHighRisk' => (bool) ($permission['highRisk'] ?? false),
            'description' => $permission['description'] ?? '',
            'risk' => $permission['risk'] ?? 'standard',
            'assignedRoleCount' => $permission['assignedRoleCount'] ?? 0,
            'prerequisiteKey' => $permission['prerequisiteKey'] ?? null,
            'validationState' => 'valid',
            'laravelPolicyHint' => $key,
            'applicableAccountTypes' => $permission['applicableAccountTypes'] ?? [],
            'createdAt' => '2025-01-10T08:00:00.000Z',
            'updatedAt' => now()->toIso8601String(),
            'reviewFlags' => ['needsReview' => false],
        ];
    }
}
