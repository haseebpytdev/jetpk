<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardRoleResource;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use App\Support\Dashboard\DashboardRoleCatalog;
use Illuminate\Http\Request;

class DashboardRolesReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int>}
     */
    public function list(User $user, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($user, 'roles.view');

        $roles = DashboardRoleCatalog::all();
        $search = strtolower(trim((string) $request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $roles = array_values(array_filter(
                $roles,
                static fn (array $role): bool => str_contains(strtolower((string) $role['name']), $search)
                    || str_contains(strtolower((string) $role['key']), $search),
            ));
        }

        $roleType = (string) $request->query('roleType', 'all');
        if ($roleType === 'system') {
            $roles = array_values(array_filter($roles, static fn (array $role): bool => (bool) $role['isSystem']));
        } elseif ($roleType === 'custom') {
            $roles = array_values(array_filter($roles, static fn (array $role): bool => ! (bool) $role['isSystem']));
        }

        $protected = (string) $request->query('protected', 'all');
        if ($protected === 'protected') {
            $roles = array_values(array_filter($roles, static fn (array $role): bool => (bool) $role['isProtected']));
        } elseif ($protected === 'unprotected') {
            $roles = array_values(array_filter($roles, static fn (array $role): bool => ! (bool) $role['isProtected']));
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $total = count($roles);
        $offset = ($page - 1) * $pageSize;
        $slice = array_slice($roles, $offset, $pageSize);
        $items = array_map(
            static fn (array $role): array => DashboardRoleResource::fromCatalog($role),
            $slice,
        );

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'pageCount' => (int) max(1, (int) ceil($total / max(1, $pageSize))),
            ],
            'filters' => array_filter([
                'q' => $request->query('q', $request->query('search')),
                'roleType' => $request->query('roleType'),
                'protected' => $request->query('protected'),
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all'),
            'summary' => [
                'totalRoles' => $total,
                'activeRoles' => $total,
                'protectedSystemRoles' => count(array_filter($roles, static fn (array $role): bool => (bool) $role['isProtected'])),
                'customRoles' => 0,
                'rolesWithHighRiskPermissions' => count(array_filter($roles, static fn (array $role): bool => ($role['highRiskPermissionCount'] ?? 0) > 0)),
                'rolesRequiringReview' => 0,
                'unusedRoles' => count(array_filter($roles, static fn (array $role): bool => ($role['assignedUserCount'] ?? 0) === 0)),
                'incompleteRoles' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        DashboardPermissionResolver::assertPermission($user, 'roles.view');
        $role = DashboardRoleCatalog::find($id);

        return $role ? DashboardRoleResource::detail($role) : null;
    }
}
