<?php

namespace App\Services\Dashboard;

use App\Enums\AccountType;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\User;
use App\Support\Dashboard\DashboardPermissionResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * RBAC-scoped quick search for dashboard topbar.
 */
final class DashboardSearchService
{
    public function search(User $user, string $query, int $limit = 8): array
    {
        $term = trim($query);
        if ($term === '' || mb_strlen($term) < 2) {
            return ['results' => [], 'query' => $term];
        }

        $results = [];
        $remaining = $limit;

        if ($remaining > 0 && Gate::forUser($user)->allows('viewAny', Booking::class)) {
            $bookingHits = $this->searchBookings($user, $term, $remaining);
            $results = array_merge($results, $bookingHits);
            $remaining = $limit - count($results);
        }

        if ($remaining > 0 && DashboardPermissionResolver::canViewCustomers($user)) {
            $customerHits = $this->searchCustomers($user, $term, $remaining);
            $results = array_merge($results, $customerHits);
            $remaining = $limit - count($results);
        }

        if ($remaining > 0 && Gate::forUser($user)->allows('viewAny', Agent::class)) {
            $agentHits = $this->searchAgents($user, $term, $remaining);
            $results = array_merge($results, $agentHits);
        }

        return [
            'query' => $term,
            'results' => array_slice($results, 0, $limit),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    protected function searchBookings(User $user, string $term, int $limit): array
    {
        $query = Booking::query()->with(['contact']);

        if (! $user->isPlatformAdmin()) {
            $query->where('bookings.agency_id', $user->current_agency_id);
        }

        $query->where(function (Builder $builder) use ($term): void {
            $builder->where('booking_reference', 'like', '%'.$term.'%')
                ->orWhere('pnr', 'like', '%'.$term.'%')
                ->orWhere('id', $term);
        });

        return $query
            ->orderByDesc('bookings.created_at')
            ->limit($limit)
            ->get()
            ->map(static function (Booking $booking): array {
                $label = $booking->booking_reference ?: 'Draft #'.$booking->id;
                $route = $booking->route ?: '—';

                return [
                    'type' => 'booking',
                    'label' => $label,
                    'detail' => trim($route.' · '.str_replace('_', ' ', (string) $booking->status->value)),
                    'href' => '/admin/bookings/'.$booking->id,
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    protected function searchCustomers(User $user, string $term, int $limit): array
    {
        $query = User::query()->where('account_type', AccountType::Customer);

        if (! $user->isPlatformAdmin()) {
            $query->where('current_agency_id', $user->current_agency_id);
        }

        $query->where(function (Builder $builder) use ($term): void {
            $builder->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%');
        });

        return $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(static fn (User $customer): array => [
                'type' => 'customer',
                'label' => $customer->name,
                'detail' => (string) ($customer->email ?? 'Customer'),
                'href' => '/admin/customers/'.$customer->id,
            ])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    protected function searchAgents(User $user, string $term, int $limit): array
    {
        $query = Agent::query()->with('user');

        if (! $user->isPlatformAdmin()) {
            $query->where('agency_id', $user->current_agency_id);
        }

        $query->where(function (Builder $builder) use ($term): void {
            $builder->where('code', 'like', '%'.$term.'%')
                ->orWhereHas('user', static function (Builder $userQuery) use ($term): void {
                    $userQuery->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                });
        });

        return $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(static function (Agent $agent): array {
                $name = $agent->user?->name ?? $agent->code ?? 'Agent';

                return [
                    'type' => 'agent',
                    'label' => $name,
                    'detail' => (string) ($agent->user?->email ?? 'Agent account'),
                    'href' => '/admin/agents/'.$agent->id,
                ];
            })
            ->all();
    }
}
