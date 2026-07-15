@extends(client_layout('dashboard', 'staff'))

@section('title', 'Staff')

@section('page-header')
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">Operations</div>
            <h1 class="page-title">{{ $agencyName ?? 'Staff portal' }}</h1>
            <div class="text-secondary mt-1">Daily operations cockpit{{ display_sep_dot() }}assigned bookings, payment review, supplier issues, and cancellations.</div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $k = $staffKpis ?? [];
        $op = $operational ?? [];
        $today = $todayActivity ?? [];
    @endphp

    <div class="row g-3 mb-4" data-testid="staff-dashboard-kpis">
        <div class="col-6 col-md-4 col-lg">
            <x-dashboard.kpi-stat label="Assigned to me" :value="number_format((int) ($k['assigned_to_me'] ?? 0))" />
        </div>
        <div class="col-6 col-md-4 col-lg">
            <x-dashboard.kpi-stat label="Payment review" :value="number_format((int) ($k['payment_review'] ?? 0))" accent="amber" />
        </div>
        <div class="col-6 col-md-4 col-lg">
            <x-dashboard.kpi-stat label="Manual review" :value="number_format((int) ($k['manual_review'] ?? 0))" />
        </div>
        <div class="col-6 col-md-4 col-lg">
            <x-dashboard.kpi-stat label="Cancel / refund pending" :value="number_format((int) ($k['cancellation_refund_pending'] ?? 0))" accent="violet" />
        </div>
        <div class="col-6 col-md-4 col-lg">
            <x-dashboard.kpi-stat label="PNR / ticketing pending" :value="number_format((int) ($k['pnr_ticketing_pending'] ?? 0))" accent="emerald" />
        </div>
    </div>

    <x-dashboard.section-header
        title="Work queues"
        subtitle="Open filtered booking lists{{ display_sep_dot() }}agency scope only."
        class="mb-3"
    />

    <div class="row g-3 mb-4 ota-admin-quick" data-testid="staff-dashboard-queues">
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index', ['assigned_to_me' => 1])"
                icon="ti-user-check"
                title="Assigned bookings"
                :helper="number_format((int) ($k['assigned_to_me'] ?? 0)).' assigned to you.'"
            />
        </div>
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index', ['queue' => 'payment_review'])"
                icon="ti-cash"
                title="Payment review"
                :helper="number_format((int) ($op['payment_review'] ?? 0)).' unpaid or partial balances.'"
            />
        </div>
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index', ['queue' => 'needs_action'])"
                icon="ti-alert-triangle"
                title="Manual review / needs action"
                :helper="number_format((int) ($op['needs_action'] ?? 0)).' items need operator follow-up.'"
            />
        </div>
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index', ['queue' => 'supplier_pnr'])"
                icon="ti-plug-connected"
                title="PNR / supplier pending"
                :helper="number_format((int) ($op['supplier_pnr_pending'] ?? 0)).' paid bookings awaiting PNR or supplier fix.'"
            />
        </div>
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index', ['queue' => 'cancellations'])"
                icon="ti-ban"
                title="Cancellations"
                :helper="number_format((int) ($op['cancellations_pending'] ?? 0)).' cancellation requests in progress.'"
            />
        </div>
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index', ['queue' => 'refunds'])"
                icon="ti-receipt-refund"
                title="Refunds"
                :helper="number_format((int) ($op['refunds_pending'] ?? 0)).' refunds awaiting approval or payout.'"
            />
        </div>
        <div class="col-md-6 col-lg-4">
            <x-dashboard.quick-action
                :href="route('staff.bookings.index')"
                icon="ti-list-check"
                title="All accessible bookings"
                helper="Full agency booking list with filters."
            />
        </div>
    </div>

    @if (! empty($supportAlerts ?? []))
        <x-dashboard.section-header
            title="Support alerts"
            subtitle="Agency support tickets{{ display_sep_dot() }}open queues and assignments."
            class="mb-3"
        />
        <div class="row g-3 mb-4 ota-admin-quick" data-testid="staff-support-alerts">
            @foreach ($supportAlerts as $card)
                <div class="col-md-6 col-lg-4">
                    <x-dashboard.quick-action
                        :href="route($card['route'], $card['route_params'] ?? [])"
                        :icon="$card['icon']"
                        :title="$card['label']"
                        :helper="number_format((int) ($card['count'] ?? 0)) . display_sep_dot() . ($card['helper'] ?? '')"
                        :data-testid="$card['testid']"
                    />
                </div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header border-0">
                    <x-dashboard.section-header
                        title="Recent assigned bookings"
                        subtitle="Latest work assigned to you."
                    >
                        <x-slot name="actions">
                            <a href="{{ route('staff.bookings.index', ['assigned_to_me' => 1]) }}" class="btn btn-sm btn-outline-primary">View all</a>
                        </x-slot>
                    </x-dashboard.section-header>
                </div>
                @if (($recentAssigned ?? collect())->isEmpty())
                    <div class="card-body pt-0">
                        <x-dashboard.empty-state
                            icon="ti-user-check"
                            title="No assigned bookings"
                            help="When an admin assigns bookings to you, they appear here."
                        />
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table ota-admin-table mb-0" data-testid="staff-recent-assigned">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer / agent</th>
                                    <th>Route</th>
                                    <th>Payment</th>
                                    <th>Supplier / PNR</th>
                                    <th>Assigned</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentAssigned as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : 'Draft #'.$row['id'] }}</td>
                                        <td class="small">{{ $row['party_label'] ?? $row['customer_name'] }}</td>
                                        <td>{{ $row['route'] ?? display_unknown() }}</td>
                                        <td><span class="small">{{ $row['payment_status_display'] ?? '' }}</span></td>
                                        <td><span class="small">{{ $row['supplier_status_display'] ?? '' }}</span></td>
                                        <td class="small text-secondary">{{ $row['assigned_at'] ?? $row['created_at'] ?? display_unknown() }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('staff.bookings.show', $row['id']) }}" class="btn btn-sm btn-outline-primary">Open</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100" data-testid="staff-today-activity">
                <div class="card-header border-0">
                    <h3 class="card-title mb-0">Today&apos;s activity</h3>
                    <div class="text-secondary small">Needs attention across your agency</div>
                </div>
                <div class="card-body">
                    @php
                        $activityRows = [
                            ['label' => 'Bookings updated today', 'count' => (int) ($today['bookings_updated'] ?? 0), 'href' => route('staff.bookings.index')],
                            ['label' => 'Payment proofs awaiting review', 'count' => (int) ($today['payment_proofs_pending'] ?? 0), 'href' => route('staff.bookings.index', ['queue' => 'payment_review'])],
                            ['label' => 'Cancellations awaiting action', 'count' => (int) ($today['cancellations_pending'] ?? 0), 'href' => route('staff.bookings.index', ['queue' => 'cancellations'])],
                            ['label' => 'Supplier failures / manual review', 'count' => (int) ($today['manual_review'] ?? 0), 'href' => route('staff.bookings.index', ['queue' => 'supplier_pnr'])],
                        ];
                        $hasActivity = collect($activityRows)->contains(fn ($r) => $r['count'] > 0);
                    @endphp
                    @if (! $hasActivity)
                        <x-dashboard.empty-state
                            icon="ti-calendar-check"
                            title="Nothing urgent right now"
                            help="Counts refresh when bookings or payments change today."
                        />
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach ($activityRows as $row)
                                @if ($row['count'] > 0)
                                    <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <a href="{{ $row['href'] }}" class="text-reset">{{ $row['label'] }}</a>
                                        <span class="badge bg-primary-lt">{{ number_format($row['count']) }}</span>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-light border mb-0">
        <span class="text-secondary">Need help?</span>
        <a href="{{ route('support') }}" class="fw-semibold ms-1">Contact support</a>
    </div>
@endsection

