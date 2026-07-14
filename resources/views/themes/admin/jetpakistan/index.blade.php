@extends(client_layout('dashboard', 'admin'))

@section('title', 'Admin Dashboard')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Operations overview</h1>
            <p>Action-first dashboard for bookings, payments, and supplier health.</p>
        </div>
        <a href="{{ client_route('admin.bookings') }}" class="jp-btn jp-btn--sm">View bookings</a>
    </div>
@endsection

@section('content')
    @php
        $s = $stats ?? [];
        $hasLiveData = (bool) ($hasLiveData ?? false);
        $opKpis = collect($operationalKpis ?? [])->keyBy('key');
        $attn = collect($needsAttention ?? []);
        $cb = $commandSummary ?? [];
        $health = $supplierHealth ?? collect();
        $pnr = $pnrHealth ?? [];
        $failures = $recentSupplierFailures ?? collect();
        $quickActions = collect($adminQuickActions ?? []);
        $taskActions = collect($taskActions ?? []);

        $kpi = static function (string $key, int $default = 0) use ($opKpis, $attn, $cb, $pnr, $failures): int {
            if ($key === 'pending_deposits') {
                return (int) ($cb['pending_deposits'] ?? $attn->firstWhere('key', 'pending_deposits')['count'] ?? 0);
            }
            if ($key === 'supplier_failures') {
                return (int) ($pnr['recent_supplier_failures_7d'] ?? $failures->count());
            }
            if ($key === 'failed_notifications') {
                return (int) ($attn->firstWhere('key', 'failed_notifications')['count'] ?? 0);
            }
            if ($key === 'support_tickets') {
                return (int) ($attn->firstWhere('key', 'open_support_tickets')['count'] ?? 0);
            }
            return (int) ($opKpis->get($key)['count'] ?? $attn->firstWhere('key', $key)['count'] ?? $default);
        };

        $kpiCards = [
            ['key' => 'today_bookings', 'label' => "Today's bookings", 'count' => (int) ($s['today_bookings'] ?? $s['bookings_today'] ?? 0), 'tone' => '', 'route' => 'admin.bookings', 'params' => []],
            ['key' => 'ticketing_pending', 'label' => 'Pending ticketing', 'count' => $kpi('ticketing_pending'), 'tone' => 't-amber', 'route' => 'admin.bookings', 'params' => ['queue' => 'ticketing']],
            ['key' => 'payment_review', 'label' => 'Pending payments', 'count' => $kpi('payment_review'), 'tone' => 't-blue', 'route' => 'admin.bookings', 'params' => ['queue' => 'payment_review']],
            ['key' => 'supplier_pnr_pending', 'label' => 'Supplier / PNR pending', 'count' => $kpi('supplier_pnr_pending'), 'tone' => 't-purple', 'route' => 'admin.bookings', 'params' => ['queue' => 'supplier_pnr']],
            ['key' => 'cancellations_pending', 'label' => 'Cancellation queue', 'count' => $kpi('cancellations_pending'), 'tone' => '', 'route' => 'admin.bookings', 'params' => ['queue' => 'cancellations']],
            ['key' => 'supplier_failures', 'label' => 'Supplier errors (7d)', 'count' => $kpi('supplier_failures'), 'tone' => 't-danger', 'route' => 'admin.bookings', 'params' => ['queue' => 'supplier_pnr']],
            ['key' => 'support_tickets', 'label' => 'Support tickets', 'count' => $kpi('support_tickets'), 'tone' => 't-blue', 'route' => 'admin.support.tickets.index', 'params' => []],
        ];

        $revenueToday = $revenueSnapshot['today_total'] ?? $revenueSnapshot['today'] ?? null;
        $recent = $recentBookings ?? [];
        $pendingQueue = $attn->filter(fn ($row) => (int) ($row['count'] ?? 0) > 0)->values();
    @endphp

    @if (! $hasLiveData)
        <x-themes.admin.jetpakistan.components.empty-state title="No live booking data yet" message="Metrics update automatically once bookings are created." />
    @endif

    <div class="jp-kpis jp-kpis--6">
        @foreach ($kpiCards as $card)
            <a href="{{ client_route($card['route'], $card['params']) }}" class="jp-kpi {{ $card['tone'] }}">
                <div class="jp-kpi__top"><div class="jp-kpi__ic" aria-hidden="true">●</div></div>
                <div class="jp-kpi__v">{{ number_format((int) $card['count']) }}</div>
                <div class="jp-kpi__l">{{ $card['label'] }}</div>
            </a>
        @endforeach
        @if ($revenueToday !== null)
            <div class="jp-kpi">
                <div class="jp-kpi__top"><div class="jp-kpi__ic" aria-hidden="true">₨</div></div>
                <div class="jp-kpi__v">PKR {{ number_format((float) $revenueToday, 0) }}</div>
                <div class="jp-kpi__l">Revenue today</div>
            </div>
        @endif
    </div>

    @if ($pendingQueue->isNotEmpty() || $quickActions->isNotEmpty())
        <div class="jp-card">
            <div class="jp-card__head">
                <h2 class="jp-card__title">Pending action queue</h2>
            </div>
            <div class="jp-action-queue">
                @foreach ($pendingQueue as $item)
                    @php
                        $routeName = $item['route'] ?? 'admin.bookings';
                        $routeParams = $item['route_params'] ?? ($item['params'] ?? []);
                    @endphp
                    <a href="{{ client_route($routeName, $routeParams) }}" class="jp-action-queue__item">
                        <span class="jp-action-queue__label">{{ $item['label'] ?? 'Review' }}</span>
                        <span class="jp-badge-pill jp-badge-pill--amber">{{ number_format((int) ($item['count'] ?? 0)) }}</span>
                    </a>
                @endforeach
                @foreach ($quickActions as $action)
                    <a href="{{ client_route($action['route'] ?? 'admin.bookings', $action['route_params'] ?? []) }}" class="jp-action-queue__item">
                        <span class="jp-action-queue__label">{{ $action['label'] ?? 'Action' }}</span>
                        <span class="jp-action-queue__hint">{{ $action['hint'] ?? '' }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="jp-card">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Supplier health</h2>
            <a href="{{ client_route('admin.api-settings') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Manage</a>
        </div>
        @if ($health->isEmpty())
            <x-themes.admin.jetpakistan.components.empty-state title="No supplier connections" message="Configure suppliers to see connection health here." />
        @else
            <div class="jp-supplier-health">
                @foreach ($health->take(8) as $row)
                    @php
                        $tone = match ($row['status'] ?? '') {
                            'connected' => 'green',
                            'error' => 'danger',
                            'disabled' => 'amber',
                            default => '',
                        };
                    @endphp
                    <div class="jp-supplier-health__row">
                        <div class="jp-supplier-health__name">{{ $row['name'] ?? 'Supplier' }}</div>
                        <x-themes.admin.jetpakistan.components.status-badge :label="$row['status_label'] ?? 'Unknown'" :tone="$tone" />
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="jp-card">
        <div class="jp-card__head">
            <h2 class="jp-card__title">Recent bookings</h2>
            <a href="{{ client_route('admin.bookings') }}" class="jp-btn jp-btn--sm jp-btn--ghost">View all</a>
        </div>
        @if (empty($recent))
            <x-themes.admin.jetpakistan.components.empty-state title="No recent bookings" />
        @else
            <div class="jp-dtable-wrap">
                <table class="jp-dtable">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Route</th>
                            <th>Status</th>
                            <th class="num">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($recent)->take(8) as $row)
                            <tr>
                                <td data-label="Reference">
                                    <a href="{{ client_route('admin.bookings.show', ['booking' => $row['id'] ?? 0]) }}" class="jp-cell-id">{{ $row['ref'] ?? ('#'.$row['id']) }}</a>
                                </td>
                                <td data-label="Route">{{ $row['route'] ?? '—' }}</td>
                                <td data-label="Status"><x-themes.admin.jetpakistan.components.status-badge :label="ucfirst(str_replace('_', ' ', $row['status'] ?? '—'))" /></td>
                                <td data-label="Created" class="num">{{ $row['created_at'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if (! empty($supportAlerts ?? []))
        <div class="jp-card">
            <div class="jp-card__head"><h2 class="jp-card__title">Support alerts</h2></div>
            <div class="jp-kpis jp-kpis--compact">
                @foreach ($supportAlerts as $card)
                    <a href="{{ client_route($card['route'], $card['route_params'] ?? []) }}" class="jp-kpi">
                        <div class="jp-kpi__v">{{ number_format((int) ($card['count'] ?? 0)) }}</div>
                        <div class="jp-kpi__l">{{ $card['label'] ?? 'Support' }}</div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if (! empty($pnr) || $failures->isNotEmpty())
        <div class="jp-card">
            <div class="jp-card__head"><h2 class="jp-card__title">System / queue health</h2></div>
            <div class="jp-kv-grid">
                @if (! empty($pnr['pending_sync'] ?? null))
                    <div class="jp-kv"><span class="jp-kv__l">PNR sync pending</span><span class="jp-kv__v">{{ $pnr['pending_sync'] }}</span></div>
                @endif
                @if (! empty($pnr['recent_supplier_failures_7d'] ?? null))
                    <div class="jp-kv"><span class="jp-kv__l">Supplier failures (7d)</span><span class="jp-kv__v">{{ $pnr['recent_supplier_failures_7d'] }}</span></div>
                @endif
                @if ($failures->isNotEmpty())
                    <div class="jp-kv jp-kv--full">
                        <span class="jp-kv__l">Latest supplier failure</span>
                        <span class="jp-kv__v">{{ $failures->first()['label'] ?? $failures->first()['message'] ?? 'See bookings queue' }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
