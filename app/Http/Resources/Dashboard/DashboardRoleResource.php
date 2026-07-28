<?php

namespace App\Http\Resources\Dashboard;

final class DashboardRoleResource
{
    /**
     * @param  array<string, mixed>  $role
     * @return array<string, mixed>
     */
    public static function fromCatalog(array $role): array
    {
        return [
            'id' => $role['id'],
            'key' => $role['key'],
            'name' => $role['name'],
            'description' => $role['description'],
            'category' => $role['category'],
            'categoryLabel' => ucfirst(str_replace('_', ' ', (string) $role['category'])),
            'isSystem' => $role['isSystem'],
            'isProtected' => $role['isProtected'],
            'assignedUserCount' => $role['assignedUserCount'],
            'permissionCount' => $role['permissionCount'],
            'highRiskPermissionCount' => $role['highRiskPermissionCount'],
            'scope' => $role['scope'],
            'scopeLabel' => self::scopeLabel((string) $role['scope']),
            'status' => $role['status'],
            'validationState' => $role['validationState'],
            'updatedAt' => $role['updatedAt'],
            'reviewFlags' => $role['reviewFlags'],
        ];
    }

    /**
     * @param  array<string, mixed>  $role
     * @return array<string, mixed>
     */
    public static function detail(array $role): array
    {
        return [
            ...self::fromCatalog($role),
            'permissionKeys' => $role['permissionKeys'],
            'permissionGroups' => $role['permissionGroups'],
            'createdAt' => $role['createdAt'],
        ];
    }

    private static function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'allRecords' => 'All records',
            'ownRecords' => 'Own records',
            'gdsOnly' => 'GDS only',
            'ndcOnly' => 'NDC only',
            default => ucfirst($scope),
        };
    }
}
