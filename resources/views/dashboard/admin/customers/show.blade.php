@extends(client_layout('dashboard', 'admin'))

@section('title', 'Customer: '.$customer->name)

@push('styles')
<style>
    .customer-profile-tabs { margin-bottom: 1rem; }
    .customer-profile-tabs .nav-link {
        font-size: .82rem;
        font-weight: 600;
        border-radius: 999px;
        padding: .38rem .72rem;
        color: #475569;
    }
    .customer-profile-tabs .nav-link.active {
        background: #e0edff;
        color: #1d4ed8;
        border-color: #93c5fd;
    }
    .customer-kv {
        display: flex;
        justify-content: space-between;
        gap: .75rem;
        font-size: .88rem;
        border-bottom: 1px dashed rgba(148, 163, 184, .3);
        padding-bottom: .35rem;
        margin-bottom: .35rem;
    }
    .customer-kv:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
    .customer-kv .label { color: #64748b; font-weight: 600; }
    .customer-kv .value { color: #0f172a; font-weight: 600; text-align: right; word-break: break-word; }
    .customer-tab-hidden { display: none !important; }
    @media (max-width: 767.98px) {
        .admin-customer-bookings-table .table thead th:nth-child(3),
        .admin-customer-bookings-table .table thead th:nth-child(4),
        .admin-customer-bookings-table .table thead th:nth-child(5),
        .admin-customer-bookings-table .table tbody td:nth-child(3),
        .admin-customer-bookings-table .table tbody td:nth-child(4),
        .admin-customer-bookings-table .table tbody td:nth-child(5) {
            display: none;
        }
        .admin-customer-travelers-table .table thead th:nth-child(3),
        .admin-customer-travelers-table .table thead th:nth-child(4),
        .admin-customer-travelers-table .table tbody td:nth-child(3),
        .admin-customer-travelers-table .table tbody td:nth-child(4) {
            display: none;
        }
    }
</style>
@endpush

@section('page-header')
    <x-dashboard.section-header :title="$customer->name" subtitle="Customer profile and activity.">
        <x-slot:actions>
            <a href="{{ route('admin.customers.index') }}" class="jp-btn jp-btn--ghost btn-sm">Back to customers</a>
        </x-slot:actions>
    </x-dashboard.section-header>
@endsection

@section('content')
    @php
        $tabs = [
            'overview' => 'Overview',
            'bookings' => 'Bookings',
            'travelers' => 'Travelers',
            'support' => 'Support tickets',
            'security' => 'Account &amp; security',
        ];
        $socialProviders = $customer->socialAccounts->pluck('provider')->unique()->values();
    @endphp

    <ul class="nav nav-pills customer-profile-tabs flex-wrap gap-1" role="tablist" data-testid="admin-customer-tabs">
        @foreach ($tabs as $tabKey => $tabLabel)
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === $tabKey ? 'active' : '' }}"
                   href="{{ route('admin.customers.show', ['customer' => $customer, 'tab' => $tabKey]) }}">
                    {!! $tabLabel !!}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Overview --}}
    <div class="{{ $activeTab !== 'overview' ? 'customer-tab-hidden' : '' }}" data-testid="admin-customer-tab-overview">
        <div class="card border-0 shadow-sm">
            <div class="jp-card__body">
                <div class="customer-kv"><span class="label">Name</span><span class="value">{{ $customer->name }}</span></div>
                <div class="customer-kv"><span class="label">{{ \App\Support\Identity\IdentityDisplay::labelUserActorId() }}</span><span class="value">{{ $customerIdentifier ?? '—' }}</span></div>
                <div class="customer-kv"><span class="label">Email</span><span class="value">{{ $customer->email }}</span></div>
                <div class="customer-kv"><span class="label">Phone</span><span class="value">{{ $phone ?? '—' }}</span></div>
                <div class="customer-kv"><span class="label">Account status</span><span class="value"><x-dashboard.status-badge :status="$customer->status?->value ?? 'unknown'" /></span></div>
                <div class="customer-kv"><span class="label">Google linked</span><span class="value">{{ $googleLinked ? 'Yes' : 'No' }}</span></div>
                <div class="customer-kv"><span class="label">Created</span><span class="value">{{ $customer->created_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                <div class="customer-kv"><span class="label">Last login</span><span class="value">{{ $customer->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</span></div>
                <div class="customer-kv">
                    <span class="label">Profile completion</span>
                    <span class="value">
                        @if ($profileTravelerCard['is_complete'])
                            <span class="badge bg-success-lt text-success">Complete</span>
                        @else
                            <span class="badge bg-warning-lt text-warning">Incomplete</span>
                        @endif
                    </span>
                </div>
                @if (! $profileTravelerCard['is_complete'] && ! empty($profileTravelerCard['completeness_issues']))
                    <div class="mt-3">
                        <div class="text-secondary small fw-semibold mb-1">Missing profile details</div>
                        <ul class="small text-secondary mb-0 ps-3">
                            @foreach ($profileTravelerCard['completeness_issues'] as $issue)
                                <li>{{ $issue }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Bookings --}}
    <div class="{{ $activeTab !== 'bookings' ? 'customer-tab-hidden' : '' }}" data-testid="admin-customer-tab-bookings">
        <div class="card border-0 shadow-sm admin-customer-bookings-table">
            <div class="card-header border-0 pb-0">
                <h3 class="jp-card__title mb-0">Customer bookings</h3>
                <div class="jp-card__subtitle text-secondary">Click a row to open the booking in All bookings.</div>
            </div>
            <div class="table-responsive ota-r-table-wrap">
                <table class="jp-table table-hover mb-0 ota-r-text-safe">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th class="text-end">Amount</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bookings as $booking)
                            @php
                                $amount = $booking->selected_fare_total ?? $booking->balance_due ?? $booking->amount_paid;
                            @endphp
                            <tr class="ota-admin-click-row"
                                data-href="{{ route('admin.bookings.show', $booking) }}"
                                tabindex="0"
                                role="link"
                                aria-label="Open booking {{ $booking->reference_code }}">
                                <td class="fw-semibold">{{ $booking->reference_code }}</td>
                                <td>{{ $booking->route ?? '—' }}</td>
                                <td><x-dashboard.status-badge :status="$booking->status?->value ?? 'unknown'" /></td>
                                <td><x-dashboard.status-badge :status="$booking->payment_status ?? 'unknown'" /></td>
                                <td class="text-end text-nowrap">
                                    @if ($amount !== null)
                                        {{ $booking->currency ?? 'PKR' }} {{ number_format((float) $amount, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="small text-secondary text-nowrap">{{ $booking->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <x-dashboard.empty-state title="No bookings yet" help="This customer has not placed any bookings." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Travelers --}}
    <div class="{{ $activeTab !== 'travelers' ? 'customer-tab-hidden' : '' }}" data-testid="admin-customer-tab-travelers">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header border-0 pb-0">
                <h3 class="jp-card__title mb-0">Default profile traveler</h3>
                <div class="jp-card__subtitle text-secondary">Derived from account name and user profile — not a saved traveler row.</div>
            </div>
            <div class="jp-card__body">
                <div class="customer-kv"><span class="label">Name</span><span class="value">{{ $profileTravelerCard['full_name'] ?: '—' }}</span></div>
                <div class="customer-kv"><span class="label">Email</span><span class="value">{{ $profileTravelerCard['email'] ?: '—' }}</span></div>
                <div class="customer-kv"><span class="label">Phone</span><span class="value">{{ $profileTravelerCard['phone'] ?? '—' }}</span></div>
                <div class="customer-kv"><span class="label">Nationality</span><span class="value">{{ $profileTravelerCard['nationality'] ?? '—' }}</span></div>
                <div class="customer-kv">
                    <span class="label">Status</span>
                    <span class="value">
                        @if ($profileTravelerCard['is_complete'])
                            <span class="badge bg-success-lt text-success">Complete</span>
                        @else
                            <span class="badge bg-warning-lt text-warning">Incomplete</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm admin-customer-travelers-table">
            <div class="card-header border-0 pb-0">
                <h3 class="jp-card__title mb-0">Saved travelers</h3>
            </div>
            <div class="table-responsive ota-r-table-wrap">
                <table class="jp-table mb-0 ota-r-text-safe">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Document</th>
                            <th>Nationality</th>
                            <th>Status</th>
                            <th>Default</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($savedTravelers as $traveler)
                            <tr>
                                <td class="fw-semibold">{{ $traveler->fullName() }}</td>
                                <td class="small">{{ $traveler->maskedDocumentNumber() ?? '—' }}</td>
                                <td>{{ $traveler->nationality ?? '—' }}</td>
                                <td>
                                    <span class="badge {{ $traveler->isComplete() ? 'bg-success-lt text-success' : 'bg-warning-lt text-warning' }}">
                                        {{ $traveler->completenessStatus() }}
                                    </span>
                                </td>
                                <td>{{ $traveler->is_default ? 'Yes' : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-dashboard.empty-state title="No saved travelers" help="Saved travelers from the customer portal will appear here." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Support --}}
    <div class="{{ $activeTab !== 'support' ? 'customer-tab-hidden' : '' }}" data-testid="admin-customer-tab-support">
        <div class="card border-0 shadow-sm">
            <div class="card-header border-0 pb-0">
                <h3 class="jp-card__title mb-0">Support tickets</h3>
                <div class="jp-card__subtitle text-secondary">Tickets created by this customer. Click a row to open the ticket.</div>
            </div>
            <div class="table-responsive ota-r-table-wrap">
                <table class="jp-table table-hover mb-0 ota-r-text-safe">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Subject</th>
                            <th>Booking</th>
                            <th>Status</th>
                            <th>Last reply</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($supportTickets as $ticket)
                            <tr class="ota-admin-click-row"
                                data-href="{{ route('admin.support.tickets.show', $ticket) }}"
                                tabindex="0"
                                role="link"
                                aria-label="Open support ticket {{ $ticket->id }}">
                                <td class="fw-semibold">{{ $ticket->id }}</td>
                                <td>{{ e($ticket->subject) }}</td>
                                <td class="small">{{ e($ticket->booking?->booking_reference ?? '—') }}</td>
                                <td><x-dashboard.status-badge :status="$ticket->status" /></td>
                                <td class="small text-secondary text-nowrap">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <x-dashboard.empty-state title="No support tickets" help="Support tickets from this customer will appear here." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Account & Security --}}
    <div class="{{ $activeTab !== 'security' ? 'customer-tab-hidden' : '' }}" data-testid="admin-customer-tab-security">
        <div class="card border-0 shadow-sm">
            <div class="jp-card__body">
                <div class="customer-kv"><span class="label">Account type</span><span class="value">{{ $customer->account_type?->value ?? '—' }}</span></div>
                <div class="customer-kv"><span class="label">Status</span><span class="value"><x-dashboard.status-badge :status="$customer->status?->value ?? 'unknown'" /></span></div>
                <div class="customer-kv"><span class="label">Email verified</span><span class="value">{{ $customer->email_verified_at?->format('Y-m-d H:i') ?? 'Not verified' }}</span></div>
                <div class="customer-kv"><span class="label">Google linked</span><span class="value">{{ $googleLinked ? 'Yes' : 'No' }}</span></div>
                <div class="customer-kv">
                    <span class="label">Social providers</span>
                    <span class="value">{{ $socialProviders->isNotEmpty() ? $socialProviders->join(', ') : 'None' }}</span>
                </div>
                <div class="mt-4 pt-2 border-top">
                    <p class="text-secondary small mb-3">
                        Role changes, suspension, invites, and permission edits are managed in Users &amp; Access — not on this customer profile.
                    </p>
                    <a href="{{ route('admin.users.edit', $customer) }}" class="jp-btn jp-btn--outline">Open Users &amp; Access edit</a>
                </div>
            </div>
        </div>
    </div>
@endsection
