@extends(client_layout('dashboard', 'admin'))

@section('title', 'Customers')

@push('styles')
<style>
    @media (max-width: 767.98px) {
        .admin-customers-table .table thead th:nth-child(4),
        .admin-customers-table .table thead th:nth-child(5),
        .admin-customers-table .table thead th:nth-child(6),
        .admin-customers-table .table tbody td:nth-child(4),
        .admin-customers-table .table tbody td:nth-child(5),
        .admin-customers-table .table tbody td:nth-child(6) {
            display: none;
        }
    }
</style>
@endpush

@section('page-header')
    <x-dashboard.section-header
        title="Customers"
        subtitle="Customer accounts, booking history, travelers, and support activity."
    />
@endsection

@section('content')
    @php
        use App\Support\Identity\ActorIdentifier;
        $activeSegment = $segment ?? 'registered';
    @endphp

    <div class="users-type-tabs ota-admin-queue-tabs mb-3" data-testid="admin-customers-segments">
        <a href="{{ route('admin.customers.index', request()->except('page', 'segment')) }}"
           class="users-type-tab ota-admin-queue-tab {{ $activeSegment === 'registered' ? 'is-active' : '' }}">Registered customers</a>
        <a href="{{ route('admin.customers.index', array_merge(request()->except('page'), ['segment' => 'guests'])) }}"
           class="users-type-tab ota-admin-queue-tab {{ $activeSegment === 'guests' ? 'is-active' : '' }}">Guest customers</a>
    </div>

    @if ($activeSegment === 'guests')
        <div class="card border-0 shadow-sm admin-customers-table ota-admin-table" data-testid="admin-guest-customers-table">
            <div class="card-header border-0 pb-0">
                <h3 class="jp-card__title mb-0">Guest customers</h3>
                <div class="jp-card__subtitle text-secondary">Guest bookers aggregated from bookings without registered accounts.</div>
            </div>
            <div class="table-responsive ota-r-table-wrap">
                <table class="jp-table mb-0 ota-r-text-safe ota-admin-table">
                    <thead class="table-light">
                        <tr>
                            <th>Guest ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th class="text-end">Bookings</th>
                            <th>Last booking</th>
                            <th>Latest ref</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($guestCustomers as $guest)
                            @php
                                $guestIdentifier = ActorIdentifier::forGuest($guest);
                                $guestShowUrl = route('admin.customers.guests.show', [
                                    'guest_id' => $guest['guest_id'],
                                    'first_name' => $guest['first_name'] !== '—' ? $guest['first_name'] : '',
                                    'email' => $guest['email'] !== '—' ? $guest['email'] : '',
                                    'phone' => $guest['phone'] !== '—' ? $guest['phone'] : '',
                                ]);
                            @endphp
                            <tr class="ota-admin-click-row" data-href="{{ $guestShowUrl }}" tabindex="0" role="link">
                                <td class="fw-semibold">{{ $guestIdentifier }}</td>
                                <td>{{ trim($guest['first_name'].' '.$guest['last_name']) ?: '—' }}</td>
                                <td>{{ $guest['email'] }}</td>
                                <td>{{ $guest['phone'] }}</td>
                                <td class="text-end">{{ number_format((int) $guest['bookings_count']) }}</td>
                                <td>{{ $guest['last_booking_at'] }}</td>
                                <td>{{ $guest['latest_booking_reference'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4 text-secondary">No guest customers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($guestCustomers->hasPages())
                <div class="card-footer">{{ $guestCustomers->appends(request()->query())->links() }}</div>
            @endif
        </div>
    @else
    <div class="row g-3 mb-4" data-testid="admin-customers-kpis">
        <div class="col-6 col-md">
            <x-dashboard.kpi-stat label="Total customers" :value="number_format($kpis['total'])" />
        </div>
        <div class="col-6 col-md">
            <x-dashboard.kpi-stat label="Active customers" :value="number_format($kpis['active'])" accent="emerald" />
        </div>
        <div class="col-6 col-md">
            <x-dashboard.kpi-stat label="Google linked" :value="number_format($kpis['google_linked'])" />
        </div>
        <div class="col-6 col-md">
            <x-dashboard.kpi-stat label="With bookings" :value="number_format($kpis['with_bookings'])" accent="violet" />
        </div>
        <div class="col-12 col-md">
            <x-dashboard.kpi-stat label="Profile incomplete" :value="number_format($kpis['profile_incomplete'])" accent="amber" />
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 ota-admin-filter-bar">
        <div class="card-body py-2">
            <form method="get" action="{{ route('admin.customers.index') }}" class="jp-form-grid jp-form-grid--filter ota-r-form-grid">
                <div class="col-12 col-md-3">
                    <label class="jp-label small mb-0" for="customers-search">Search</label>
                    <input id="customers-search" class="jp-control jp-control-sm" name="search" placeholder="Name, email, or phone" value="{{ $filters['search'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2">
                    <label class="jp-label small mb-0" for="customers-status">Status</label>
                    <select id="customers-status" class="jp-control jp-control-sm" name="status">
                        <option value="">All statuses</option>
                        @foreach (['active', 'invited', 'suspended', 'inactive'] as $statusOption)
                            <option value="{{ $statusOption }}" @selected(($filters['status'] ?? '') === $statusOption)>{{ ucfirst($statusOption) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="jp-label small mb-0" for="customers-google">Google linked</label>
                    <select id="customers-google" class="jp-control jp-control-sm" name="google_linked">
                        <option value="">Any</option>
                        <option value="yes" @selected(($filters['google_linked'] ?? '') === 'yes')>Yes</option>
                        <option value="no" @selected(($filters['google_linked'] ?? '') === 'no')>No</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="jp-label small mb-0" for="customers-created-from">Created from</label>
                    <input id="customers-created-from" type="date" class="jp-control jp-control-sm" name="created_from" value="{{ $filters['created_from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="jp-label small mb-0" for="customers-created-to">Created to</label>
                    <input id="customers-created-to" type="date" class="jp-control jp-control-sm" name="created_to" value="{{ $filters['created_to'] ?? '' }}">
                </div>
                <div class="col-12 col-md-1">
                    <div class="ota-r-action-bar">
                        <button type="submit" class="jp-btn jp-btn--sm jp-btn--primary flex-fill">Apply</button>
                        <a href="{{ route('admin.customers.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost flex-fill">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm admin-customers-table ota-admin-table">
        <div class="card-header border-0 pb-0">
            <h3 class="jp-card__title mb-0">Customer list</h3>
            <div class="jp-card__subtitle text-secondary">Click a row to open the customer profile.</div>
        </div>
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table table-hover mb-0 ota-r-text-safe ota-admin-table" data-testid="admin-customers-table">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Google</th>
                        <th class="text-end">Bookings</th>
                        <th>Last booking</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        @php
                            $profile = $customer->profile;
                            $phone = trim((string) ($profile?->phone ?? $profile?->whatsapp ?? ($customer->meta['phone'] ?? '')));
                            $hasGoogle = $customer->socialAccounts->contains(fn ($account) => $account->provider === 'google');
                        @endphp
                        <tr class="ota-admin-click-row"
                            data-href="{{ route('admin.customers.show', $customer) }}"
                            tabindex="0"
                            role="link"
                            aria-label="Open customer profile for {{ $customer->name }}">
                            <td class="fw-semibold">{{ $customer->name }}</td>
                            <td class="small">{{ $customer->email }}</td>
                            <td class="small text-secondary">{{ $phone !== '' ? $phone : '—' }}</td>
                            <td>
                                @if ($hasGoogle)
                                    <span class="badge bg-success-lt text-success">Linked</span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format((int) $customer->bookings_count) }}</td>
                            <td class="small text-nowrap text-secondary">
                                {{ $customer->last_booking_at ? \Illuminate\Support\Carbon::parse($customer->last_booking_at)->format('Y-m-d H:i') : '—' }}
                            </td>
                            <td><x-dashboard.status-badge :status="$customer->status?->value ?? 'unknown'" /></td>
                            <td class="small text-nowrap text-secondary">{{ $customer->created_at?->format('Y-m-d') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-dashboard.empty-state icon="ti-user-heart" title="No customers found" help="Try adjusting your filters or check back when new customers register." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($customers->hasPages())
            <div class="card-footer">{{ $customers->appends(request()->query())->links() }}</div>
        @endif
    </div>
    @endif
@endsection
