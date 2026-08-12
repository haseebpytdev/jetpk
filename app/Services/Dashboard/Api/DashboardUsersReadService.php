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
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>, summary: array<string, int>, facets: array<string, list<string>>}
     */
    public function paginate(User $actor, Request $request): array
    {
        DashboardPermissionResolver::assertPermission($actor, 'users.view');

        $scope = $this->directoryScope($request);
        $query = $this->scopedQuery($actor, $scope);
        $this->applyFilters($query, $request, $scope);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $this->applySort($query, $request);

        $paginator = (clone $query)->with(['staffProfile', 'currentAgency'])->paginate($pageSize, ['*'], 'page', $page);
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
            'filters' => $this->activeFilters($request, $scope),
            'summary' => $this->summary($items, $paginator->total()),
            'facets' => $this->facets($scope),
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
    protected function scopedQuery(User $actor, string $scope = 'users'): Builder
    {
        if ($scope === 'staff') {
            return User::query()->where('account_type', AccountType::Staff);
        }

        return User::query()->whereIn('account_type', [
            AccountType::PlatformAdmin,
            AccountType::Staff,
            AccountType::Agent,
            AccountType::AgentStaff,
            AccountType::Customer,
        ]);
    }

    protected function directoryScope(Request $request): string
    {
        $scope = strtolower(trim((string) $request->query('scope', 'users')));

        return $scope === 'staff' ? 'staff' : 'users';
    }

    protected function resolveUser(User $actor, string $id): ?User
    {
        $query = $this->scopedQuery($actor, 'users');
        if (preg_match('/^JP-USR-(\d+)$/i', $id, $matches) === 1) {
            return (clone $query)->with(['staffProfile', 'currentAgency'])->whereKey((int) $matches[1])->first();
        }
        if (ctype_digit($id)) {
            return (clone $query)->with(['staffProfile', 'currentAgency'])->whereKey((int) $id)->first();
        }

        return null;
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function applyFilters(Builder $query, Request $request, string $scope): void
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        if ($search !== '') {
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('staffProfile', function (Builder $profile) use ($search): void {
                        $profile->where('job_title', 'like', '%'.$search.'%')
                            ->orWhere('department', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = (string) $request->query('status', 'all');
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($scope !== 'staff') {
            $accountType = (string) $request->query('accountType', $request->query('userType', 'all'));
            if ($accountType !== '' && $accountType !== 'all') {
                $mapped = match ($accountType) {
                    'superAdministrator', 'platform_admin' => AccountType::PlatformAdmin,
                    'operationsManager', 'administrator', 'staff' => AccountType::Staff,
                    'bookingAgent', 'agent' => AccountType::Agent,
                    'agentStaff', 'agent_staff' => AccountType::AgentStaff,
                    'customer' => AccountType::Customer,
                    default => null,
                };
                if ($mapped instanceof AccountType) {
                    $query->where('account_type', $mapped);
                }
            }
        }

        $department = trim((string) $request->query('department', ''));
        if ($department !== '') {
            $query->where(function (Builder $inner) use ($department): void {
                $inner->whereHas('staffProfile', function (Builder $profile) use ($department): void {
                    $profile->where('department', 'like', '%'.$department.'%');
                })->orWhere('meta->department', 'like', '%'.$department.'%');
            });
        }

        $agency = trim((string) $request->query('agency', ''));
        if ($agency !== '' && $scope !== 'staff') {
            $query->whereHas('currentAgency', function (Builder $agencyQuery) use ($agency): void {
                $agencyQuery->where('name', 'like', '%'.$agency.'%');
            });
        }

        $jobTitle = trim((string) $request->query('jobTitle', $request->query('role', '')));
        if ($jobTitle !== '' && $scope === 'staff') {
            $query->whereHas('staffProfile', function (Builder $profile) use ($jobTitle): void {
                $profile->where('job_title', 'like', '%'.$jobTitle.'%');
            });
        }

        $verification = (string) $request->query('verification', $request->query('verificationState', 'all'));
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
            'userType', 'accountType' => $query->orderBy('account_type', $direction),
            default => $query->orderBy('id', $direction),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, int>
     */
    protected function summary(array $items, int $total): array
    {
        return [
            'totalUsers' => $total,
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
     * @return array<string, list<string>>
     */
    protected function facets(string $scope): array
    {
        if ($scope === 'staff') {
            return [
                'userTypes' => ['operationsManager'],
                'statuses' => ['active', 'suspended', 'disabled'],
            ];
        }

        return [
            'userTypes' => [
                'superAdministrator',
                'operationsManager',
                'bookingAgent',
                'agentStaff',
                'customer',
            ],
            'statuses' => ['active', 'suspended', 'disabled'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request, string $scope): array
    {
        return array_filter([
            'scope' => $scope,
            'q' => $request->query('q', $request->query('search')),
            'status' => $request->query('status'),
            'accountType' => $request->query('accountType', $request->query('userType')),
            'role' => $request->query('role'),
            'jobTitle' => $request->query('jobTitle'),
            'department' => $request->query('department'),
            'agency' => $request->query('agency'),
            'verification' => $request->query('verification', $request->query('verificationState')),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
