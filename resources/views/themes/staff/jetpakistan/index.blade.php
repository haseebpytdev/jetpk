@extends(client_layout('dashboard', 'staff'))

@section('title', 'Staff dashboard')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>{{ $agencyName ?? 'Staff portal' }}</h1>
            <p>Assigned bookings, payment review, and operational queues.</p>
        </div>
        <a href="{{ client_route('staff.bookings.index') }}" class="jp-btn jp-btn--sm">All bookings</a>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

@php
    $k = $staffKpis ?? [];
    $op = $operational ?? [];
    $today = $todayActivity ?? [];
@endphp

<div class="jp-kpis jp-kpis--5">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['assigned_to_me'] ?? 0)) }}</div><div class="jp-kpi__l">Assigned to me</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($k['payment_review'] ?? 0)) }}</div><div class="jp-kpi__l">Payment review</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['manual_review'] ?? 0)) }}</div><div class="jp-kpi__l">Manual review</div></div>
    <div class="jp-kpi t-purple"><div class="jp-kpi__v">{{ number_format((int) ($k['cancellation_refund_pending'] ?? 0)) }}</div><div class="jp-kpi__l">Cancel / refund</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format((int) ($k['pnr_ticketing_pending'] ?? 0)) }}</div><div class="jp-kpi__l">PNR / ticketing</div></div>
</div>

<div class="jp-card">
    <div class="jp-card__head"><h2 class="jp-card__title">Work queues</h2></div>
    <div class="jp-action-queue">
        <a href="{{ client_route('staff.bookings.index', ['assigned_to_me' => 1]) }}" class="jp-action-queue__item">
            <span class="jp-action-queue__label">Assigned bookings</span>
            <span class="jp-badge-pill">{{ number_format((int) ($k['assigned_to_me'] ?? 0)) }}</span>
        </a>
        <a href="{{ client_route('staff.bookings.index', ['queue' => 'payment_review']) }}" class="jp-action-queue__item">
            <span class="jp-action-queue__label">Payment review</span>
            <span class="jp-badge-pill jp-badge-pill--amber">{{ number_format((int) ($op['payment_review'] ?? 0)) }}</span>
        </a>
        <a href="{{ client_route('staff.bookings.index', ['queue' => 'needs_action']) }}" class="jp-action-queue__item">
            <span class="jp-action-queue__label">Needs action</span>
            <span class="jp-badge-pill">{{ number_format((int) ($op['needs_action'] ?? 0)) }}</span>
        </a>
        <a href="{{ client_route('staff.bookings.index', ['queue' => 'supplier_pnr']) }}" class="jp-action-queue__item">
            <span class="jp-action-queue__label">PNR / supplier</span>
            <span class="jp-badge-pill jp-badge-pill--amber">{{ number_format((int) ($op['supplier_pnr_pending'] ?? 0)) }}</span>
        </a>
        <a href="{{ client_route('staff.bookings.index', ['queue' => 'cancellations']) }}" class="jp-action-queue__item">
            <span class="jp-action-queue__label">Cancellations</span>
            <span class="jp-badge-pill">{{ number_format((int) ($op['cancellations_pending'] ?? 0)) }}</span>
        </a>
        <a href="{{ client_route('staff.bookings.index', ['queue' => 'refunds']) }}" class="jp-action-queue__item">
            <span class="jp-action-queue__label">Refunds</span>
            <span class="jp-badge-pill">{{ number_format((int) ($op['refunds_pending'] ?? 0)) }}</span>
        </a>
    </div>
</div>

@if (! empty($supportAlerts ?? []))
    <div class="jp-card">
        <div class="jp-card__head"><h2 class="jp-card__title">Support alerts</h2></div>
        <div class="jp-action-queue">
            @foreach ($supportAlerts as $card)
                <a href="{{ client_route($card['route'], $card['route_params'] ?? []) }}" class="jp-action-queue__item">
                    <span class="jp-action-queue__label">{{ $card['label'] ?? 'Support' }}</span>
                    <span class="jp-badge-pill">{{ number_format((int) ($card['count'] ?? 0)) }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif

<div class="jp-grid jp-grid--2">
    <div class="jp-card">
        <div class="jp-card__head jp-between">
            <h2 class="jp-card__title">Recent assigned bookings</h2>
            <a href="{{ client_route('staff.bookings.index', ['assigned_to_me' => 1]) }}" class="jp-btn jp-btn--sm jp-btn--ghost">View all</a>
        </div>
        @if (($recentAssigned ?? collect())->isEmpty())
            <x-themes.admin.jetpakistan.components.empty-state title="No assigned bookings" message="When an admin assigns bookings to you, they appear here." />
        @else
            <div class="jp-dtable-wrap">
                <table class="jp-dtable">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Customer / agent</th>
                            <th>Route</th>
                            <th>Payment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentAssigned as $row)
                            <tr>
                                <td><span class="jp-cell-id">{{ ($row['booking_ref'] ?? '') !== '' ? $row['booking_ref'] : 'Draft #'.$row['id'] }}</span></td>
                                <td>{{ $row['party_label'] ?? $row['customer_name'] ?? '—' }}</td>
                                <td>{{ $row['route'] ?? '—' }}</td>
                                <td><x-themes.admin.jetpakistan.components.status-badge :label="$row['payment_status_display'] ?? 'Unpaid'" tone="amber" /></td>
                                <td><a href="{{ client_route('staff.bookings.show', ['booking' => $row['id']]) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h2 class="jp-card__title">Today&apos;s activity</h2></div>
        @php
            $activityRows = [
                ['label' => 'Bookings updated today', 'count' => (int) ($today['bookings_updated'] ?? 0), 'route' => 'staff.bookings.index', 'params' => []],
                ['label' => 'Payment proofs pending', 'count' => (int) ($today['payment_proofs_pending'] ?? 0), 'route' => 'staff.bookings.index', 'params' => ['queue' => 'payment_review']],
                ['label' => 'Cancellations pending', 'count' => (int) ($today['cancellations_pending'] ?? 0), 'route' => 'staff.bookings.index', 'params' => ['queue' => 'cancellations']],
                ['label' => 'Manual review', 'count' => (int) ($today['manual_review'] ?? 0), 'route' => 'staff.bookings.index', 'params' => ['queue' => 'supplier_pnr']],
            ];
            $hasActivity = collect($activityRows)->contains(fn ($r) => $r['count'] > 0);
        @endphp
        @if (! $hasActivity)
            <x-themes.admin.jetpakistan.components.empty-state title="Nothing urgent right now" message="Counts refresh when bookings or payments change today." />
        @else
            <ul class="jp-list-plain">
                @foreach ($activityRows as $row)
                    @if ($row['count'] > 0)
                        <li class="jp-list-plain__item">
                            <a href="{{ client_route($row['route'], $row['params']) }}">{{ $row['label'] }}</a>
                            <span class="jp-badge-pill">{{ number_format($row['count']) }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif
    </div>
</div>
@endsection
