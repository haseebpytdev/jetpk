<?php

namespace App\Services\Dashboard\Api;

use App\Enums\AccountType;
use App\Http\Resources\Dashboard\DashboardCustomerResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardCustomersReadService
{
    /**
     * @return array{items: list<array<string, mixed>>, pagination: array<string, int>, filters: array<string, mixed>}
     */
    public function paginate(User $user, Request $request): array
    {
        Gate::authorize('viewAny', User::class);

        $query = $this->scopedQuery($user)
            ->select(['id', 'name', 'email', 'status', 'created_at', 'meta', 'email_verified_at'])
            ->with(['profile:id,user_id,phone,whatsapp,city,country,nationality'])
            ->withCount('bookings')
            ->withMax('bookings as last_booking_at', 'created_at');

        $this->applyFilters($query, $request);

        $page = max(1, (int) $request->query('page', 1));
        $pageSize = max(5, min(50, (int) $request->query('pageSize', 25)));
        $this->applySort($query, $request);

        $paginator = (clone $query)->paginate($pageSize, ['*'], 'page', $page);
        $items = $paginator->getCollection()
            ->map(static fn (User $customer): array => DashboardCustomerResource::fromModel($customer))
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
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(User $user, string $id): ?array
    {
        Gate::authorize('viewAny', User::class);

        $customerId = str_starts_with(strtoupper($id), 'CU-') ? (int) substr($id, 3) : (int) $id;
        $customer = $this->scopedQuery($user)->whereKey($customerId)->first();
        if ($customer === null) {
            return null;
        }

        Gate::authorize('view', $customer);

        return DashboardCustomerResource::fromModel($customer);
    }

    /**
     * @return Builder<User>
     */
    protected function scopedQuery(User $user): Builder
    {
        $query = User::query()->where('account_type', AccountType::Customer);

        if (! $user->isPlatformAdmin()) {
            $query->where('current_agency_id', $user->current_agency_id);
        }

        return $query;
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
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhereHas('profile', function (Builder $profile) use ($search): void {
                        $profile->where('phone', 'like', '%'.$search.'%')
                            ->orWhere('whatsapp', 'like', '%'.$search.'%');
                    });
            });
        }

        $status = (string) $request->query('accountStatus', $request->query('status', 'all'));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', strtolower($status));
        }

        $verification = (string) $request->query('verificationStatus', $request->query('verification', 'all'));
        if ($verification === 'Verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verification === 'Pending') {
            $query->whereNull('email_verified_at');
        }

        $customerType = (string) $request->query('customerType', 'all');
        if ($customerType !== '' && $customerType !== 'all') {
            $query->where('meta->customer_type', $customerType);
        }
    }

    /**
     * @param  Builder<User>  $query
     */
    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->query('sort', 'newest');
        $direction = strtolower((string) $request->query('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'name' => $query->orderBy('name', $direction),
            'oldest' => $query->orderBy('id', 'asc'),
            'bookingCount' => $query->orderBy('bookings_count', $direction),
            'lastBookingDate' => $query->orderBy('last_booking_at', $direction),
            default => $query->orderByDesc('id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function activeFilters(Request $request): array
    {
        return array_filter([
            'q' => $request->query('q', $request->query('search')),
            'accountStatus' => $request->query('accountStatus', $request->query('status')),
            'verificationStatus' => $request->query('verificationStatus', $request->query('verification')),
            'customerType' => $request->query('customerType'),
            'sort' => $request->query('sort'),
            'direction' => $request->query('direction'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== 'all');
    }
}
