<?php

namespace App\Services\Customers;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only guest booker aggregates from bookings without customer_id.
 */
class GuestCustomerService
{
    public function countGuests(User $actor, Request $request): int
    {
        $base = $this->baseQuery($actor);
        $this->applyFilters($base, $request);

        return (int) DB::query()->fromSub(clone $base, 'guest_groups')->count();
    }

    public function paginate(User $actor, Request $request): LengthAwarePaginator
    {
        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $base = $this->baseQuery($actor);
        $this->applyFilters($base, $request);

        $total = (int) DB::query()
            ->fromSub(clone $base, 'guest_groups')
            ->count();

        $rows = (clone $base)
            ->orderByDesc('last_booking_at')
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (object $row): array => $this->mapRow($row));

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $rows,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function bookingsForGuest(User $actor, string $email, ?string $phone): Collection
    {
        $query = DB::table('bookings')
            ->leftJoin('booking_contacts', 'booking_contacts.booking_id', '=', 'bookings.id')
            ->whereNull('bookings.customer_id')
            ->select([
                'bookings.id',
                'bookings.booking_reference',
                'bookings.route',
                'bookings.status',
                'bookings.created_at',
            ])
            ->orderByDesc('bookings.id');

        if (! $actor->isPlatformAdmin()) {
            $query->where('bookings.agency_id', $actor->current_agency_id);
        }

        $query->where(function (Builder $inner) use ($email, $phone): void {
            if ($email !== '') {
                $inner->orWhere('booking_contacts.email', $email);
            }
            if ($phone !== '') {
                $inner->orWhere('booking_contacts.phone', $phone);
            }
        });

        return $query->get()->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'booking_reference' => (string) ($row->booking_reference ?: ('#'.$row->id)),
            'route' => (string) ($row->route ?: '—'),
            'status' => (string) ($row->status ?: '—'),
            'created_at' => $row->created_at ? Carbon::parse((string) $row->created_at)->format('Y-m-d H:i') : '—',
        ]);
    }

    protected function baseQuery(User $actor): Builder
    {
        $emailKey = "LOWER(COALESCE(booking_contacts.email, ''))";
        $phoneKey = "COALESCE(booking_contacts.phone, '')";

        $query = DB::table('bookings')
            ->leftJoin('booking_contacts', 'booking_contacts.booking_id', '=', 'bookings.id')
            ->leftJoin('booking_passengers as lead_passenger', function ($join): void {
                $join->on('lead_passenger.booking_id', '=', 'bookings.id')
                    ->where('lead_passenger.is_lead_passenger', true);
            })
            ->whereNull('bookings.customer_id')
            ->where(function (Builder $inner): void {
                $inner->where(function (Builder $contact): void {
                    $contact->whereNotNull('booking_contacts.email')
                        ->where('booking_contacts.email', '<>', '');
                })->orWhere(function (Builder $contact): void {
                    $contact->whereNotNull('booking_contacts.phone')
                        ->where('booking_contacts.phone', '<>', '');
                })->orWhere(function (Builder $passenger): void {
                    $passenger->whereNotNull('lead_passenger.first_name')
                        ->where('lead_passenger.first_name', '<>', '');
                });
            })
            ->groupBy(DB::raw($emailKey), DB::raw($phoneKey))
            ->selectRaw('MIN(bookings.id) as guest_id')
            ->selectRaw('MAX(COALESCE(lead_passenger.first_name, "")) as first_name')
            ->selectRaw('MAX(COALESCE(lead_passenger.last_name, "")) as last_name')
            ->selectRaw("MAX({$emailKey}) as email")
            ->selectRaw("MAX({$phoneKey}) as phone")
            ->selectRaw('COUNT(bookings.id) as bookings_count')
            ->selectRaw('MAX(bookings.created_at) as last_booking_at')
            ->selectRaw('MAX(COALESCE(bookings.booking_reference, "")) as latest_booking_reference');

        if (! $actor->isPlatformAdmin()) {
            $query->where('bookings.agency_id', $actor->current_agency_id);
        }

        return $query;
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $query->havingRaw(
                '(MAX(COALESCE(lead_passenger.first_name, "")) LIKE ?
                    OR MAX(COALESCE(lead_passenger.last_name, "")) LIKE ?
                    OR MAX(LOWER(COALESCE(booking_contacts.email, ""))) LIKE LOWER(?)
                    OR MAX(COALESCE(booking_contacts.phone, "")) LIKE ?)',
                [$search, $search, $search, $search]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapRow(object $row): array
    {
        $firstName = trim((string) ($row->first_name ?? ''));
        $lastName = trim((string) ($row->last_name ?? ''));

        return [
            'guest_id' => (int) $row->guest_id,
            'first_name' => $firstName !== '' ? $firstName : '—',
            'last_name' => $lastName !== '' ? $lastName : '—',
            'email' => trim((string) ($row->email ?? '')) ?: '—',
            'phone' => trim((string) ($row->phone ?? '')) ?: '—',
            'bookings_count' => (int) ($row->bookings_count ?? 0),
            'last_booking_at' => $row->last_booking_at
                ? Carbon::parse((string) $row->last_booking_at)->format('Y-m-d H:i')
                : '—',
            'latest_booking_reference' => trim((string) ($row->latest_booking_reference ?? '')) ?: '—',
        ];
    }
}
