<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardRoleResource;
use App\Models\Role;
use App\Models\User;
use App\Services\Rbac\RbacRolePresenter;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DashboardRolesReadService
{
    public function __construct(
        protected RbacRolePresenter $presenter,
    ) {}

    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int>}
     */
    public function list(User $user, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($user, 'roles.view');

        $roles = $this->allPresented();
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

        $custom = count(array_filter($roles, static fn (array $role): bool => ! (bool) $role['isSystem']));

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
                'customRoles' => $custom,
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
        $role = $this->findRole($id);
        if ($role === null) {
            return null;
        }

        $presented = $this->presenter->present($role);
        $assignee = $role->users()->first();
        $effective = $assignee instanceof User
            ? DashboardPermissionResolver::effectivePermissionKeys($assignee)
            : [];

        return [
            ...DashboardRoleResource::detail($presented),
            'assignedUsers' => $presented['assignedUsers'],
            'audit' => $this->presenter->history($role),
            'catalogPermissions' => $this->presenter->catalogPermissions(),
            'authorization' => [
                'model' => 'dual-read',
                'rolePermissionKeys' => $presented['permissionKeys'],
                'rolePermissionCount' => count($presented['permissionKeys']),
                'accountTypeFallback' => 'AccountType baseline remains active when no matching role assignment applies.',
                'staffMetaOverrides' => 'users.meta.staff_permissions still apply as staff overrides.',
                'effectiveForFirstAssignee' => $effective,
                'effectiveCountForFirstAssignee' => count($effective),
            ],
        ];
    }

    public function findRole(string $id): ?Role
    {
        if (! Schema::hasTable('roles')) {
            return null;
        }

        if (ctype_digit($id)) {
            return Role::query()->with(['permissionRows', 'users'])->find((int) $id);
        }

        return Role::query()->with(['permissionRows', 'users'])
            ->where('slug', $id)
            ->whereNull('agency_id')
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function allPresented(): array
    {
        if (! Schema::hasTable('roles')) {
            return [];
        }

        return Role::query()
            ->with('permissionRows')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => $this->presenter->present($role))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalog(): array
    {
        return $this->presenter->catalogPermissions();
    }
}
