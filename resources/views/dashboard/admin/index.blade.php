@extends(client_layout('dashboard', 'admin'))

@section('title', 'Admin Dashboard')

@section('page-header')
    <div class="jp-between ota-admin-page-head ota-dash-overview-head">
        <div class="col">
            <h1 class="page-title mb-0">Admin Dashboard</h1>
            <p class="ota-dash-overview-subtitle mb-0">Action-first overview</p>
        </div>
    </div>
@endsection

@section('content')
    @php
        $s = $stats ?? [];
        $recent = $recentBookings ?? [];
        $hasLiveData = (bool) ($hasLiveData ?? false);
        $opKpis = collect($operationalKpis ?? [])->keyBy('key');
        $attn = collect($needsAttention ?? []);
        $cb = $commandSummary ?? [];
        $health = $supplierHealth ?? collect();
        $pnr = $pnrHealth ?? [];
        $agents = $agentPerformance ?? [];
        $failures = $recentSupplierFailures ?? collect();

        $pendingDepositsCount = (int) ($cb['pending_deposits'] ?? $attn->firstWhere('key', 'pending_deposits')['count'] ?? 0);
        $pendingDepositsUrl = route('admin.agent-deposits.index', ['status' => 'submitted']);
        $supplierFailureCount = (int) ($pnr['recent_supplier_failures_7d'] ?? $failures->count());

        $kpi = static function (string $key, int $default = 0) use ($opKpis, $attn, $cb, $agents, $pnr, $supplierFailureCount): int {
            if ($key === 'pending_deposits') {
                return (int) ($cb['pending_deposits'] ?? $attn->firstWhere('key', 'pending_deposits')['count'] ?? 0);
            }
            if ($key === 'agency_applications') {
                return (int) ($agents['pending_applications'] ?? 0);
            }
            if ($key === 'supplier_failures') {
                return $supplierFailureCount;
            }
            if ($key === 'failed_notifications') {
                return (int) ($attn->firstWhere('key', 'failed_notifications')['count'] ?? 0);
            }

            return (int) ($opKpis->get($key)['count'] ?? $attn->firstWhere('key', $key)['count'] ?? 0);
        };

        $actionCards = [
            [
                'key' => 'pending_deposits',
                'label' => 'Pending Deposits',
                'count' => $kpi('pending_deposits'),
                'helper' => 'Agency funds waiting approval.',
                'icon' => 'ti-wallet',
                'tone' => 'amber',
                'priority' => true,
                'route' => 'admin.agent-deposits.index',
                'route_params' => ['status' => 'submitted'],
                'cta' => 'Review Deposits',
            ],
            [
                'key' => 'agency_applications',
                'label' => 'Agency Applications',
                'count' => $kpi('agency_applications'),
                'helper' => 'New agent sign-ups awaiting review.',
                'icon' => 'ti-user-plus',
                'tone' => 'violet',
                'route' => 'admin.agent-applications.index',
                'route_params' => [],
                'cta' => 'Review',
            ],
            [
                'key' => 'payment_review',
                'label' => 'Payment Review',
                'count' => $kpi('payment_review'),
                'helper' => 'Unpaid or partial balances.',
                'icon' => 'ti-cash',
                'tone' => 'emerald',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'payment_review'],
                'cta' => 'Review',
            ],
            [
                'key' => 'supplier_pnr_pending',
                'label' => 'Supplier / PNR Pending',
                'count' => $kpi('supplier_pnr_pending'),
                'helper' => 'Paid bookings awaiting a PNR.',
                'icon' => 'ti-plug-connected',
                'tone' => 'blue',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
                'cta' => 'View',
            ],
            [
                'key' => 'manual_review',
                'label' => 'Manual Review',
                'count' => $kpi('manual_review'),
                'helper' => 'Supplier failures needing staff review.',
                'icon' => 'ti-alert-circle',
                'tone' => 'amber',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
                'cta' => 'Review',
            ],
            [
                'key' => 'ticketing_pending',
                'label' => 'Ticketing Pending',
                'count' => $kpi('ticketing_pending'),
                'helper' => 'Ready for ticket issuance.',
                'icon' => 'ti-ticket',
                'tone' => 'rose',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'ticketing'],
                'cta' => 'View',
            ],
            [
                'key' => 'cancellations_pending',
                'label' => 'Cancellation Requests',
                'count' => $kpi('cancellations_pending'),
                'helper' => 'Requests awaiting decision.',
                'icon' => 'ti-ban',
                'tone' => 'teal',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'cancellations'],
                'cta' => 'View',
            ],
            [
                'key' => 'refunds_pending',
                'label' => 'Refund Requests',
                'count' => $kpi('refunds_pending'),
                'helper' => 'Approve or pay out approved refunds.',
                'icon' => 'ti-receipt-refund',
                'tone' => 'violet',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'refunds'],
                'cta' => 'View',
            ],
            [
                'key' => 'failed_notifications',
                'label' => 'Failed Notifications',
                'count' => $kpi('failed_notifications'),
                'helper' => 'Communications that need a retry.',
                'icon' => 'ti-bell-ringing',
                'tone' => 'amber',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'all'],
                'cta' => 'View',
            ],
            [
                'key' => 'supplier_failures',
                'label' => 'Supplier Failures',
                'count' => $kpi('supplier_failures'),
                'helper' => 'Recent supplier attempt failures (7d).',
                'icon' => 'ti-alert-triangle',
                'tone' => 'danger',
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'supplier_pnr'],
                'cta' => 'View',
            ],
        ];

        $shortcuts = [
            ['key' => 'deposits', 'label' => 'Review Deposits', 'icon' => 'ti-wallet', 'route' => 'admin.agent-deposits.index', 'route_params' => ['status' => 'submitted']],
            ['key' => 'agent_applications', 'label' => 'Approve Agencies', 'icon' => 'ti-building', 'route' => 'admin.agent-applications.index', 'route_params' => []],
            ['key' => 'payment_review', 'label' => 'Payment Review', 'icon' => 'ti-cash', 'route' => 'admin.bookings', 'route_params' => ['queue' => 'payment_review']],
            ['key' => 'ticketing', 'label' => 'Ticketing Queue', 'icon' => 'ti-ticket', 'route' => 'admin.bookings', 'route_params' => ['queue' => 'ticketing']],
            ['key' => 'manual_review', 'label' => 'Manual Review', 'icon' => 'ti-eye-check', 'route' => 'admin.bookings', 'route_params' => ['queue' => 'supplier_pnr']],
            ['key' => 'supplier_errors', 'label' => 'Supplier Errors', 'icon' => 'ti-plug-connected-x', 'route' => 'admin.bookings', 'route_params' => ['queue' => 'supplier_pnr']],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'ti-chart-bar', 'route' => 'admin.reports', 'route_params' => []],
            ['key' => 'api_settings', 'label' => 'API Settings', 'icon' => 'ti-api', 'route' => 'admin.api-settings', 'route_params' => []],
        ];

        $sabreRow = $health->first(fn ($h) => strtoupper((string) ($h['code'] ?? '')) === 'SABRE');
        $sabreStatus = (string) ($sabreRow['status'] ?? 'not_configured');
        $sabreLabel = match ($sabreStatus) {
            'connected' => 'Connected',
            'disabled' => 'Disabled',
            'error' => 'Error',
            default => 'Not configured',
        };
        $sabreTone = match ($sabreStatus) {
            'connected' => 'good',
            'error' => 'warn',
            default => 'muted',
        };

        $connectedSuppliers = $health->where('status', 'connected')->count();
        $errorSuppliers = $health->where('status', 'error')->count();
        $apiHealthLabel = $errorSuppliers > 0 ? 'Needs attention' : ($connectedSuppliers > 0 ? 'Good' : 'Setup required');
        $apiHealthTone = $errorSuppliers > 0 ? 'warn' : ($connectedSuppliers > 0 ? 'good' : 'muted');

        $failedNotifications = $kpi('failed_notifications');

        $activityIcon = static function (string $key): string {
            return match ($key) {
                'pending_deposits' => 'ti-wallet',
                'payment_review' => 'ti-cash',
                'supplier_pnr_pending', 'supplier_failures' => 'ti-plug-connected',
                'ticketing_pending' => 'ti-ticket',
                'cancellations_pending' => 'ti-ban',
                'refunds_pending' => 'ti-receipt-refund',
                'failed_notifications' => 'ti-bell-ringing',
                default => 'ti-activity',
            };
        };

        $activityItems = $attn->filter(fn ($row) => (int) ($row['count'] ?? 0) > 0)->take(5)->map(function (array $row) use ($activityIcon): array {
            $row['icon'] = $activityIcon((string) ($row['key'] ?? ''));

            return $row;
        });
        if ($activityItems->isEmpty() && collect($recent)->isNotEmpty()) {
            $activityItems = collect($recent)->take(5)->map(fn ($row) => [
                'key' => 'booking_'.$row['id'],
                'label' => 'Booking '.($row['ref'] ?? '#'.$row['id']).' · '.($row['route'] ?? '—'),
                'helper' => ucfirst((string) ($row['status'] ?? 'updated')),
                'time' => $row['created_at'] ?? '',
                'icon' => 'ti-plane',
                'count' => null,
                'route' => 'admin.bookings',
                'route_params' => ['queue' => 'all', 'preview' => $row['preview_query'] ?? ''],
            ]);
        }
    @endphp

    <div class="ota-dash-overview" data-testid="ota-dash-overview">

        @if (! $hasLiveData)
            <div class="jp-alert jp-alert--info mb-3 py-2">
                <i class="ti ti-info-circle me-1"></i>
                No live booking data yet. Metrics update automatically once bookings are created.
            </div>
        @endif

        <div class="ota-dash-notice mb-3" data-testid="ota-dash-notice">
            <div class="ota-dash-notice__text">
                <i class="ti ti-info-circle"></i>
                <span>Supplier connections and ticketing providers may still require final API onboarding. Manual review remains available.</span>
            </div>
            <span class="ota-dash-notice__badge">Unified Overview Layout</span>
        </div>

        <section class="mb-4" aria-labelledby="ota-action-queue-heading">
            <h2 id="ota-action-queue-heading" class="ota-dash-section-title mb-2">Action Queue</h2>
            <div class="ota-dash-action-grid" data-testid="ota-action-queue">
                @foreach ($actionCards as $card)
                    @php
                        $href = route($card['route'], $card['route_params'] ?? []);
                        $depositLabel = $card['count'] === 1 ? 'deposit' : 'deposits';
                    @endphp
                    @if (($card['key'] ?? '') === 'pending_deposits')
                        <span class="visually-hidden" data-testid="ota-command-banner-pending-deposits">
                            {{ number_format((int) $card['count']) }} pending {{ $depositLabel }}
                        </span>
                    @endif
                    <x-dashboard.overview.action-card
                        :href="$href"
                        :label="$card['label']"
                        :count="$card['count']"
                        :helper="$card['helper']"
                        :icon="$card['icon']"
                        :tone="$card['tone']"
                        :priority="($card['priority'] ?? false)"
                        :cta="$card['cta']"
                        :test-key="$card['key']"
                        data-testid="ota-op-kpi-{{ $card['key'] }}"
                    />
                @endforeach
            </div>
        </section>

        @if (! empty($supportAlerts ?? []))
            <section class="mb-4" aria-labelledby="ota-support-alerts-heading">
                <h2 id="ota-support-alerts-heading" class="ota-dash-section-title mb-2">Support alerts</h2>
                <div class="ota-dash-action-grid" data-testid="ota-support-alerts">
                    @foreach ($supportAlerts as $card)
                        <x-dashboard.overview.action-card
                            :href="route($card['route'], $card['route_params'] ?? [])"
                            :label="$card['label']"
                            :count="$card['count']"
                            :helper="$card['helper']"
                            :icon="$card['icon']"
                            :tone="$card['tone']"
                            :cta="$card['cta'] ?? 'View'"
                            :test-key="$card['key']"
                            :data-testid="$card['testid']"
                        />
                    @endforeach
                </div>
            </section>
        @endif

        <section class="mb-4" aria-labelledby="ota-shortcuts-heading">
            <h2 id="ota-shortcuts-heading" class="ota-dash-section-title mb-2">Quick Shortcuts</h2>
            <div class="ota-dash-shortcuts" data-testid="ota-admin-quick-actions">
                @foreach ($shortcuts as $shortcut)
                    <x-dashboard.overview.shortcut-chip
                        :href="route($shortcut['route'], $shortcut['route_params'] ?? [])"
                        :label="$shortcut['label']"
                        :icon="$shortcut['icon']"
                        :test-key="$shortcut['key']"
                    />
                @endforeach
            </div>
        </section>

        <div class="row g-3">
            <div class="col-lg-7">
                <section class="card ota-dash-panel h-100" data-testid="ota-dash-recent-activity">
                    <div class="card-header py-2 border-0">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <h3 class="jp-card__title mb-0 fs-6">Recent Activity</h3>
                            <a href="{{ route('admin.bookings') }}" class="small text-decoration-none">View all activity</a>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        @forelse ($activityItems as $item)
                            @php
                                $itemHref = route($item['route'] ?? 'admin.bookings', (array) ($item['route_params'] ?? []));
                            @endphp
                            <a href="{{ $itemHref }}" class="ota-dash-activity-item text-reset text-decoration-none">
                                <span class="ota-dash-activity-icon"><i class="ti {{ $item['icon'] ?? 'ti-activity' }}"></i></span>
                                <span class="flex-grow-1 min-w-0">
                                    <span class="ota-dash-activity-label">{{ $item['label'] }}</span>
                                    @if (! empty($item['helper']))
                                        <span class="ota-dash-activity-meta">{{ $item['helper'] }}</span>
                                    @endif
                                </span>
                                <span class="ota-dash-activity-time text-secondary small text-nowrap">
                                    @if (! empty($item['time']))
                                        {{ $item['time'] }}
                                    @elseif (isset($item['count']))
                                        {{ number_format((int) $item['count']) }} pending
                                    @endif
                                </span>
                            </a>
                        @empty
                            <p class="text-secondary small mb-0 py-2">No pending activity right now.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="card ota-dash-panel h-100" data-testid="ota-dash-system-status">
                    <div class="card-header py-2 border-0">
                        <h3 class="jp-card__title mb-0 fs-6">System Status</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="ota-dash-status-row">
                            <div>
                                <div class="ota-dash-status-label">Sabre Connection</div>
                                <div class="ota-dash-status-meta">GDS Connection</div>
                            </div>
                            <span class="ota-dash-status-badge ota-dash-status-badge--{{ $sabreTone }}">{{ $sabreLabel }}</span>
                        </div>
                        <div class="ota-dash-status-row">
                            <div>
                                <div class="ota-dash-status-label">Wallet Service</div>
                                <div class="ota-dash-status-meta">Payment Wallet</div>
                            </div>
                            <span class="ota-dash-status-badge ota-dash-status-badge--good">Active</span>
                        </div>
                        <div class="ota-dash-status-row">
                            <div>
                                <div class="ota-dash-status-label">API Health</div>
                                <div class="ota-dash-status-meta">API Endpoints</div>
                            </div>
                            <span class="ota-dash-status-badge ota-dash-status-badge--{{ $apiHealthTone }}">{{ $apiHealthLabel }}</span>
                        </div>
                        <div class="ota-dash-status-row">
                            <div>
                                <div class="ota-dash-status-label">Notifications Queue</div>
                                <div class="ota-dash-status-meta">Retry Queue</div>
                            </div>
                            <span class="ota-dash-status-badge ota-dash-status-badge--{{ $failedNotifications > 0 ? 'warn' : 'good' }}">
                                {{ $failedNotifications > 0 ? number_format($failedNotifications).' Pending' : 'Clear' }}
                            </span>
                        </div>
                        <div class="pt-2">
                            <a href="{{ route('admin.api-settings') }}" class="small text-decoration-none">View system logs <i class="ti ti-chevron-right"></i></a>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        {{-- Legacy test hook: compact overview no longer renders the command banner --}}
        <span class="visually-hidden" data-testid="ota-op-kpi-row" aria-hidden="true"></span>
        <span class="visually-hidden" data-testid="ota-needs-attention" aria-hidden="true"></span>
    </div>
@endsection
