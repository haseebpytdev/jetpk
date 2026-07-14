@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Agent dashboard')

@section('account_content')
    @php
        $perm = $portalPermissions ?? [];
        $bk = $bookingKpis ?? [];
        $fin = $financeSummary ?? [];
        $ws = $walletSummary ?? null;
        $agentName = trim((string) (auth()->user()?->name ?? 'Agent'));
        $firstName = strtok($agentName !== '' ? $agentName : 'Agent', ' ') ?: 'Agent';
        $recentRows = $recentBookings ?? collect();
        $todayRecentBookings = $recentRows->filter(fn ($booking) => $booking->created_at?->isToday())->count();
        $agentAgency = auth()->user()?->currentAgency;
        $homepageSections = ($publicBranding ?? [])['sections'] ?? collect();
        $homepageHeroSection = $homepageSections instanceof \Illuminate\Support\Collection
            ? $homepageSections->get('hero')
            : ($homepageSections['hero'] ?? null);
        if ($homepageHeroSection === null && $agentAgency !== null) {
            $homepageHeroSection = $agentAgency->homepageSections()
                ->where('section_key', \App\Services\Agencies\HomepageSectionPresenter::HERO)
                ->first();
        }
        $dashboardAgencySettings = $agencySettings ?? $agentAgency?->agencySetting;
        $dashboardHero = app(\App\Services\Agencies\HomepageSectionPresenter::class)->presentHero(
            $homepageHeroSection,
            $dashboardAgencySettings ?? null,
            config('ota-brand', []),
        );
        $dashboardHeroBg = $dashboardHero['background_url'] ?? null;
        $dashboardHeroBgStyle = filled($dashboardHeroBg)
            ? "--ota-hero-bg-image: url('".e($dashboardHeroBg)."')"
            : '';
    @endphp

    <section
        @class([
            'ota-dashboard-hero',
            'ota-dashboard-hero--homepage-image' => filled($dashboardHeroBg),
        ])
        data-testid="agent-dashboard-hero"
        @if($dashboardHeroBgStyle !== '') style="{{ $dashboardHeroBgStyle }}" @endif
    >
        <div class="ota-dashboard-hero__content">
            <span class="ota-dashboard-hero__badge">Agent Portal</span>
            <h1>Welcome back, {{ $firstName }}</h1>
            <p>Manage customer bookings, payments, and agency support.</p>
            <div class="ota-dashboard-hero__chips" aria-label="Agent portal benefits">
                <span><i class="ti ti-plane-departure" aria-hidden="true"></i> Customer Trips</span>
                <span><i class="ti ti-headset" aria-hidden="true"></i> Agency Support</span>
                <span><i class="ti ti-shield-check" aria-hidden="true"></i> Secure Transactions</span>
            </div>
        </div>
    </section>

    <section class="ota-dashboard-actions" aria-label="Agent quick actions">
        @if ($perm['bookings_create'] ?? false)
            <a href="{{ route('agent.bookings.create') }}" class="ota-dashboard-action">
                <span class="ota-dashboard-action__icon"><i class="ti ti-plane-departure" aria-hidden="true"></i></span>
                <span><strong>New Booking</strong><small>Book flights for your customer</small></span>
            </a>
        @endif
        @if ($perm['bookings_view'] ?? false)
            <a href="{{ route('agent.bookings.index') }}" class="ota-dashboard-action">
                <span class="ota-dashboard-action__icon"><i class="ti ti-ticket" aria-hidden="true"></i></span>
                <span><strong>Bookings</strong><small>View and manage trips</small></span>
            </a>
        @endif
        @if ($perm['support_manage'] ?? false)
            <a href="{{ route('agent.support.tickets.index') }}" class="ota-dashboard-action">
                <span class="ota-dashboard-action__icon"><i class="ti ti-headset" aria-hidden="true"></i></span>
                <span><strong>Support</strong><small>Get help for an issue</small></span>
            </a>
        @endif
        @if ($perm['travelers_manage'] ?? false)
            <a href="{{ route('agent.travelers.create') }}" class="ota-dashboard-action">
                <span class="ota-dashboard-action__icon"><i class="ti ti-user-plus" aria-hidden="true"></i></span>
                <span><strong>Add Customer</strong><small>Save traveler details</small></span>
            </a>
        @endif
        @if (($perm['commissions_view'] ?? false) && Route::has('agent.commissions.index'))
            <a href="{{ route('agent.commissions.index') }}" class="ota-dashboard-action">
                <span class="ota-dashboard-action__icon"><i class="ti ti-percentage" aria-hidden="true"></i></span>
                <span><strong>Commissions</strong><small>Track earnings</small></span>
            </a>
        @endif
    </section>

    <section class="ota-dashboard-kpis ota-agent-dashboard-grid" data-testid="agent-dashboard-kpis">
        @if ($perm['bookings_view'] ?? false)
            <div class="ota-dashboard-kpi">
                <span class="ota-dashboard-kpi__icon"><i class="ti ti-calendar-event" aria-hidden="true"></i></span>
                <span class="ota-dashboard-kpi__label">Todays Bookings</span>
                <a href="{{ route('agent.bookings.index') }}" class="ota-dashboard-kpi__value-link">
                    <strong>{{ number_format((int) $todayRecentBookings) }}</strong>
                </a>
            </div>
            <div class="ota-dashboard-kpi">
                <span class="ota-dashboard-kpi__icon"><i class="ti ti-ticket" aria-hidden="true"></i></span>
                <span class="ota-dashboard-kpi__label">Total Bookings</span>
                <a href="{{ route('agent.bookings.index') }}" class="ota-dashboard-kpi__value-link">
                    <strong>{{ number_format((int) ($bk['total'] ?? 0)) }}</strong>
                </a>
            </div>
            <div class="ota-dashboard-kpi ota-dashboard-kpi--amber">
                <span class="ota-dashboard-kpi__icon"><i class="ti ti-wallet" aria-hidden="true"></i></span>
                <span class="ota-dashboard-kpi__label">Pending Payment</span>
                <a href="{{ route('agent.bookings.index', ['filter' => 'pending_payment']) }}" class="ota-dashboard-kpi__value-link">
                    <strong>{{ number_format((int) ($bk['pending_payment'] ?? 0)) }}</strong>
                </a>
            </div>
            <div class="ota-dashboard-kpi ota-dashboard-kpi--violet">
                <span class="ota-dashboard-kpi__icon"><i class="ti ti-circle-check" aria-hidden="true"></i></span>
                <span class="ota-dashboard-kpi__label">PNR Confirmed</span>
                <a href="{{ route('agent.bookings.index', ['filter' => 'pnr_created']) }}" class="ota-dashboard-kpi__value-link">
                    <strong>{{ number_format((int) ($bk['pnr_confirmed'] ?? 0)) }}</strong>
                </a>
            </div>
        @endif
    </section>

    @if (($perm['commissions_view'] ?? false) || ($perm['wallet_view'] ?? false))
        <div class="ota-dashboard-content-grid ota-dashboard-content-grid--finance ota-agent-dashboard-finance-grid mb-4">
            <div class="ota-account-card ota-agent-dashboard-finance-card" data-testid="agent-finance-summary">
                <div class="ota-account-card__head">
                    <div>
                        <h2 class="ota-account-card__title">Finance summary</h2>
                        <p class="ota-account-card__lead">
                            @if (($perm['commissions_view'] ?? false) && ($perm['wallet_view'] ?? false))
                                Commissions and wallet at a glance.
                            @elseif ($perm['commissions_view'] ?? false)
                                Commission totals at a glance.
                            @else
                                Wallet balance and deposits at a glance.
                            @endif
                        </p>
                    </div>
                </div>
                <div class="ota-account-card__body">
                    <dl class="ota-account-dl">
                        @if ($perm['commissions_view'] ?? false)
                            <div class="ota-account-dl__row">
                                <dt>Commission earned</dt>
                                <dd>Rs {{ number_format((float) ($fin['balance'] ?? 0), 2) }}</dd>
                            </div>
                            <div class="ota-account-dl__row">
                                <dt>Commission pending</dt>
                                <dd>Rs {{ number_format((float) ($fin['pending'] ?? 0), 2) }}</dd>
                            </div>
                            <div class="ota-account-dl__row">
                                <dt>Payouts (paid)</dt>
                                <dd>Rs {{ number_format((float) ($fin['paid'] ?? 0), 2) }}</dd>
                            </div>
                        @endif
                        @if ($perm['wallet_view'] ?? false)
                            <div class="ota-account-dl__row" data-testid="agent-dashboard-wallet-balance">
                                <dt>Wallet balance</dt>
                                <dd>Rs {{ number_format((float) ($ws['balance'] ?? 0), 2) }}</dd>
                            </div>
                            <div class="ota-account-dl__row">
                                <dt>Pending deposits</dt>
                                <dd>Rs {{ number_format((float) ($ws['pending_deposits'] ?? 0), 2) }}</dd>
                            </div>
                            <div class="ota-account-dl__row">
                                <dt>Credit limit</dt>
                                <dd>
                                    @if ($ws['credit_enabled'] ?? false)
                                        Rs {{ number_format((float) $ws['credit_limit'], 2) }}
                                    @else
                                        Not enabled
                                    @endif
                                </dd>
                            </div>
                        @endif
                    </dl>
                    <div class="ota-agent-finance-actions">
                        @if ($perm['payments_upload'] ?? false)
                            <a href="{{ route('agent.deposits.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-dashboard-request-deposit">Request deposit</a>
                        @endif
                        @if (($perm['ledger_view'] ?? false) && Route::has('agent.ledger.index'))
                            <a href="{{ route('agent.ledger.index') }}" class="ota-account-btn ota-account-btn--secondary" data-testid="agent-dashboard-view-ledger">View ledger</a>
                        @endif
                    </div>
                    @if ($perm['wallet_view'] ?? false)
                        <div class="ota-account-note mt-3 mb-0" data-testid="agent-wallet-credit-notice">
                            Booking credit enforcement is not enabled yet.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($perm['bookings_view'] ?? false)
        <section class="ota-dashboard-content-grid">
            <div class="ota-account-card">
                <div class="ota-account-card__head">
                    <div>
                        <h2 class="ota-account-card__title">Recent Bookings</h2>
                        <p class="ota-account-card__lead">Your latest customer booking requests.</p>
                    </div>
                    <a href="{{ route('agent.bookings.index') }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View all bookings</a>
                </div>
                @if (($recentBookings ?? collect())->isEmpty())
                    <div class="ota-account-card__body">
                        <div class="ota-account-empty ota-agent-dashboard-empty" data-testid="agent-recent-bookings-empty">
                            <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-ticket"></i></div>
                            <p class="ota-account-empty-title">No bookings yet</p>
                            <p class="ota-account-empty-help">Create your first booking request to see trips here.</p>
                            @if ($perm['bookings_create'] ?? false)
                                <div class="ota-account-empty-action">
                                    <a href="{{ route('agent.bookings.create') }}" class="ota-account-btn ota-account-btn--primary">Create booking</a>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="ota-account-card__body ota-account-card__body--flush">
                        <div class="ota-account-table-wrap">
                            <table class="ota-account-table ota-account-table--dashboard ota-agent-dashboard-table mb-0" data-testid="agent-recent-bookings">
                                <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Route</th>
                                        <th>Customer</th>
                                        <th>Payment</th>
                                        <th>PNR / supplier</th>
                                        @if ($perm['commissions_view'] ?? false)
                                            <th>Commission</th>
                                        @endif
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentBookings as $booking)
                                        @php
                                            $pax = $booking->passengers->first();
                                            $customer = trim(implode(' ', array_filter([$pax?->title, $pax?->first_name, $pax?->last_name]))) ?: ($booking->contact?->email ?? 'Customer');
                                            $hasPnr = filled($booking->pnr);
                                            $meta = is_array($booking->meta) ? $booking->meta : [];
                                            $supplierOp = \App\Support\Bookings\SupplierOperationalStatus::fromValues(
                                                (string) ($booking->supplier_booking_status ?? 'not_started'),
                                                (string) (($meta['supplier_provider'] ?? null) ?: ($booking->supplier ?? '')),
                                                $hasPnr,
                                                $meta,
                                            );
                                            $paymentOp = \App\Support\Bookings\PaymentOperationalStatus::fromValue((string) ($booking->payment_status ?? 'unpaid'));
                                            $commissionEntry = $booking->commissionEntries->sortByDesc('created_at')->first();
                                        @endphp
                                        <tr class="ota-dashboard-booking-row">
                                            <td class="fw-semibold ota-r-text-safe" data-label="Reference">{{ $booking->booking_reference ?? 'Draft' }}</td>
                                            <td data-label="Route">{{ $booking->route ?? 'N/A' }}</td>
                                            <td data-label="Customer">{{ $customer }}</td>
                                            <td data-label="Payment"><span class="text-capitalize">{{ $paymentOp['label'] }}</span></td>
                                            <td data-label="PNR / supplier">
                                                @if ($hasPnr)
                                                    <span class="ota-r-text-safe">{{ $booking->pnr }}</span>
                                                @else
                                                    <span class="text-secondary">{{ $supplierOp['label'] }}</span>
                                                @endif
                                            </td>
                                            @if ($perm['commissions_view'] ?? false)
                                                <td data-label="Commission">
                                                    @if ($commissionEntry)
                                                        <span class="text-capitalize">{{ $commissionEntry->status->value }}</span>
                                                        <span class="d-block small">Rs {{ number_format((float) $commissionEntry->commission_amount, 0) }}</span>
                                                    @else
                                                        <span class="text-secondary">N/A</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td data-label="Status"><x-dashboard.status-badge :status="$booking->status" /></td>
                                            <td class="text-end" data-label="Action">
                                                <a href="{{ route('agent.bookings.show', $booking) }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            @if ($perm['support_manage'] ?? false)
                <aside class="ota-dashboard-help-card ota-agent-dashboard-support-card">
                    <span class="ota-dashboard-help-card__icon"><i class="ti ti-headset" aria-hidden="true"></i></span>
                    <h2>Agency support</h2>
                    <p>Create and review support tickets for booking, payment, and operational issues.</p>
                    <ul>
                        <li><i class="ti ti-circle-check" aria-hidden="true"></i> Booking and payment help</li>
                        <li><i class="ti ti-circle-check" aria-hidden="true"></i> Customer trip support</li>
                        <li><i class="ti ti-circle-check" aria-hidden="true"></i> Wallet and deposit questions</li>
                    </ul>
                    <a href="{{ route('agent.support.tickets.index') }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--block">Open Support</a>
                </aside>
            @endif
        </section>
    @endif
@endsection
