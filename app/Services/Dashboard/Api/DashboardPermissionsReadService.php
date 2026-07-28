<?php

namespace App\Services\Dashboard\Api;

use App\Http\Resources\Dashboard\DashboardPermissionResource;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionCatalog;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Http\Request;

class DashboardPermissionsReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int>}
     */
    public function list(User $user, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($user, 'permissions.view');

        $permissions = DashboardPermissionCatalog::all();
        $search = strtolower(trim((string) $request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $permissions = array_values(array_filter(
                $permissions,
                static fn (array $permission): bool => str_contains(strtolower((string) $permission['key']), $search)
                    || str_contains(strtolower((string) $permission['label']), $search),
            ));
        }

        $category = (string) $request->query('category', $request->query('domain', 'all'));
        if ($category !== '' && $category !== 'all') {
            $permissions = array_values(array_filter(
                $permissions,
                static fn (array $permission): bool => ($permission['category'] ?? '') === $category,
            ));
        }

        $risk = (string) $request->query('risk', 'all');
        if ($risk === 'highRisk') {
            $permissions = array_values(array_filter(
                $permissions,
                static fn (array $permission): bool => (bool) ($permission['highRisk'] ?? false),
            ));
        }

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(100, (int) $request->query('pageSize', 25)));
        $total = count($permissions);
        $offset = ($page - 1) * $pageSize;
        $slice = array_slice($permissions, $offset, $pageSize);
        $items = array_map(
            static fn (array $permission): array => DashboardPermissionResource::fromCatalog($permission),
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
                'category' => $request->query('category', $request->query('domain')),
                'risk' => $request->query('risk'),
            ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all'),
            'summary' => [
                'totalPermissions' => $total,
                'viewPermissions' => count(array_filter($permissions, static fn (array $p): bool => str_ends_with((string) $p['key'], '.view'))),
                'requestPermissions' => 0,
                'approvalPermissions' => count(array_filter($permissions, static fn (array $p): bool => str_contains((string) $p['key'], 'approve'))),
                'managePermissions' => 0,
                'exportPermissions' => count(array_filter($permissions, static fn (array $p): bool => str_ends_with((string) $p['key'], '.export'))),
                'highRiskPermissions' => count(array_filter($permissions, static fn (array $p): bool => (bool) ($p['highRisk'] ?? false))),
                'permissionsRequiringPrerequisiteReview' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $key): ?array
    {
        DashboardPermissionResolver::assertPermission($user, 'permissions.view');
        $permission = DashboardPermissionCatalog::find($key);

        return $permission ? DashboardPermissionResource::fromCatalog($permission) : null;
    }
}
