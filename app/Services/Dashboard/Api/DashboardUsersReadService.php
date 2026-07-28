<?php

namespace App\Services\Dashboard\Api;

use App\Enums\AccountType;
use App\Http\Resources\Dashboard\DashboardUserDetailResource;
use App\Http\Resources\Dashboard\DashboardUserResource;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardUsersReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int>}
     */
    public function paginate(User $actor, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($actor, 'users.view');

        $query = $this->scopedQuery($actor);
        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (User $user): array => DashboardUserResource::fromModel($user))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'pageSize' => $paginator->perPage(),
                'total' => $paginator->total(),
                'pageCount' => $paginator->lastPage(),
            ],
            'filters' => $this->activeFilters($request),
            'summary' => $this->summary($items),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $actor, string $id): ?array
    {
        DashboardPermissionResolver::assertPermission($actor, 'users.view');
        $user = $this->resolveUser($actor, $id);
        if ($user === null) {
            return null;
        }

        return DashboardUserDetailResource::fromModel($user);
    }

    /**
     * @return Builder<User>
     */
    protected function scopedQuery(User $actor): Builder
    {
        return User::query()->whereIn('account_type', [
            AccountType::PlatformAdmin,
            AccountType::Staff,
            AccountType::Agent,
            AccountType::AgentStaff,
        ]);
    }

    protected function resolveUser(User $actor, string $id): ?User
    {
        $query = $this->scopedQuery($actor);
        if (preg_match('/^JP-USR-(\d+)$/i', $id, $matches) === 1) {
            return (clone $query)->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->whereKey((int) $id)->first();
        }

        return null;
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $accountType = (string) $request->query('accountType', $request->query('userType', 'all'));
        if ($accountType !== '' && $accountType !== 'all') {
            $mapped = match ($accountType) {
                'superAdministrator' => AccountType::PlatformAdmin,
                'operationsManager', 'administrator' => AccountType::Staff,
                'bookingAgent' => AccountType::Agent,
                default => $accountType,
            };
            if ($mapped instanceof AccountType) {
                $query->where('account_type', $mapped);
            }
        }

        $verification = (string) $request->query('verification', 'all');
        if ($verification === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verification === 'unverified') {
            $query->whereNull('email_verified_at');
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'createdAt');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'fullName', 'name' => $query->orderBy('name', $direction),
            'email' => $query->orderBy('email', $direction),
            default => $query->orderBy('id', $direction),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    protected function summary(array $items): array
    {
        return [
            'totalUsers' => count($items),
            'activeUsers' => count(array_filter($items, static fn (array $row): bool => ($row['status'] ?? '') === 'active')),
            'invitedUsers' => 0,
            'lockedUsers' => 0,
            'suspendedUsers' => count(array_filter($items, static fn (array $row): bool => ($row['status'] ?? '') === 'suspended')),
            'mfaEnabledUsers' => 0,
            'usersWithoutRoles' => 0,
            'usersRequiringReview' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return array_filter([
            'q' => $request->query('q', $request->query('search')),
            'status' => $request->query('status'),
            'accountType' => $request->query('accountType', $request->query('userType')),
            'role' => $request->query('role'),
            'department' => $request->query('department'),
            'verification' => $request->query('verification'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
