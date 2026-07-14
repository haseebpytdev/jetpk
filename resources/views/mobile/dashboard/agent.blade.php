@extends('layouts.mobile-app')

@section('title', 'Agent dashboard')

@section('content')
    @php
        $perm = $portalPermissions ?? [];
        $bk = $bookingKpis ?? [];
        $ws = $walletSummary ?? null;
        $user = auth()->user();
        $userLabel = trim((string) ($user?->name ?? ''));
        if ($userLabel === '') {
            $userLabel = trim((string) ($user?->email ?? 'Agent'));
        }
        $recent = $recentBookings ?? collect();
        $featured = $recent->first();
        $travelersCount = $travelersCount ?? null;
    @endphp

    <div class="ota-mobile-dashboard" data-testid="ota-mobile-agent-dashboard">
        <header class="ota-mobile-dashboard__header ota-mobile-dashboard__header--agent">
            <p class="ota-mobile-dashboard__agency">{{ $agencyName ?? 'Agent portal' }}</p>
            <div class="ota-mobile-dashboard__header-row">
                <h1 class="ota-mobile-dashboard__name">{{ $userLabel }}</h1>
                @if (($perm['wallet_view'] ?? false) && $ws !== null)
                    <div class="ota-mobile-dashboard__wallet-pill" data-testid="ota-mobile-agent-wallet-pill">
                        <span class="ota-mobile-dashboard__wallet-label">Available</span>
                        <span class="ota-mobile-dashboard__wallet-value">Rs {{ number_format((float) ($ws['available_balance'] ?? 0), 0) }}</span>
                    </div>
                @endif
            </div>
        </header>

        <section class="ota-mobile-dashboard__section" aria-label="Overview">
            <div class="ota-mobile-dashboard__stats" data-testid="ota-mobile-agent-dashboard-stats">
                @if ($perm['bookings_view'] ?? false)
                    @include('mobile.dashboard.partials.stat-card', [
                        'label' => 'Total bookings',
                        'value' => number_format((int) ($bk['total'] ?? 0)),
                    ])
                    @include('mobile.dashboard.partials.stat-card', [
                        'label' => 'Pending payment',
                        'value' => number_format((int) ($bk['pending_payment'] ?? 0)),
                        'tone' => 'amber',
                    ])
                @endif
                @if (($perm['wallet_view'] ?? false) && $ws !== null)
                    @include('mobile.dashboard.partials.stat-card', [
                        'label' => 'Wallet balance',
                        'value' => 'Rs '.number_format((float) ($ws['balance'] ?? 0), 0),
                        'tone' => 'emerald',
                    ])
                    @include('mobile.dashboard.partials.stat-card', [
                        'label' => 'Pending deposits',
                        'value' => 'Rs '.number_format((float) ($ws['pending_deposits'] ?? 0), 0),
                        'tone' => 'sky',
                    ])
                @endif
                @if (($perm['travelers_manage'] ?? false) && $travelersCount !== null)
                    @include('mobile.dashboard.partials.stat-card', [
                        'label' => 'Saved travelers',
                        'value' => number_format((int) $travelersCount),
                        'tone' => 'violet',
                    ])
                @endif
                @if ($perm['commissions_view'] ?? false)
                    @include('mobile.dashboard.partials.stat-card', [
                        'label' => 'Commission earned',
                        'value' => 'Rs '.number_format((float) ($bk['commission_earned'] ?? 0), 0),
                        'tone' => 'emerald',
                    ])
                @endif
            </div>
        </section>

        @if (($perm['bookings_view'] ?? false) && $featured)
            @php
                $price = $featured->fareBreakdown?->total;
                $priceLabel = $price !== null ? 'Rs '.number_format((float) $price, 0) : null;
            @endphp
            <section class="ota-mobile-dashboard__section" aria-label="Recent booking">
                @include('mobile.dashboard.partials.booking-list-card', [
                    'booking' => $featured,
                    'showUrl' => route('agent.bookings.show', $featured),
                    'passengerCount' => $featured->passengers->count(),
                    'priceLabel' => $priceLabel,
                    'sectionLabel' => 'Recent request',
                ])
            </section>
        @elseif ($perm['bookings_view'] ?? false)
            <section class="ota-mobile-dashboard__section" aria-label="Bookings">
                <div class="ota-mobile-dashboard__empty" data-testid="ota-mobile-agent-dashboard-empty">
                    <p class="ota-mobile-dashboard__empty-title">No bookings yet</p>
                    @if ($perm['bookings_create'] ?? false)
                        <a href="{{ route('agent.bookings.create') }}" class="ota-mobile-dashboard__btn ota-mobile-dashboard__btn--primary">New booking</a>
                    @endif
                </div>
            </section>
        @endif

        <section class="ota-mobile-dashboard__section" aria-label="Quick actions">
            <h2 class="ota-mobile-dashboard__section-title">Quick actions</h2>
            <div class="ota-mobile-dashboard__quick-grid">
                @if ($perm['bookings_create'] ?? false)
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.bookings.create'),
                        'title' => 'New booking',
                        'icon' => 'plus',
                        'testId' => 'ota-mobile-agent-new-booking',
                    ])
                @endif
                @if ($perm['bookings_view'] ?? false)
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.bookings.index'),
                        'title' => 'Bookings',
                        'icon' => 'bookings',
                    ])
                @endif
                @if ($perm['wallet_view'] ?? false)
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.wallet.show'),
                        'title' => 'Wallet',
                        'icon' => 'wallet',
                        'testId' => 'ota-mobile-agent-wallet-quick',
                    ])
                    @if (Route::has('agent.deposits.index'))
                        @include('mobile.dashboard.partials.quick-action-card', [
                            'href' => route('agent.deposits.index'),
                            'title' => 'Deposits',
                            'icon' => 'deposit',
                        ])
                    @endif
                @endif
                @if (($perm['ledger_view'] ?? false) && Route::has('agent.ledger.index'))
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.ledger.index'),
                        'title' => 'Ledger',
                        'icon' => 'ledger',
                    ])
                @endif
                @if (($perm['commissions_view'] ?? false) && Route::has('agent.commissions.index'))
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.commissions.index'),
                        'title' => 'Commissions',
                        'icon' => 'wallet',
                    ])
                @endif
                @if (($perm['reports_view'] ?? false) && Route::has('agent.reports.index'))
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.reports.index'),
                        'title' => 'Reports',
                        'icon' => 'bookings',
                    ])
                @endif
                @if ((($perm['reports_view'] ?? false) || ($perm['ledger_view'] ?? false)) && Route::has('agent.finance.statement.show'))
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.finance.statement.show'),
                        'title' => 'Statement',
                        'icon' => 'deposit',
                    ])
                @endif
                @if (($perm['ledger_view'] ?? false) && Route::has('agent.accounting.ledger.index'))
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.accounting.ledger.index'),
                        'title' => 'Accounting',
                        'icon' => 'ledger',
                    ])
                @endif
                @if (($perm['travelers_manage'] ?? false) && Route::has('agent.travelers.index'))
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.travelers.index'),
                        'title' => 'Travelers',
                        'icon' => 'travelers',
                    ])
                @endif
                @if ($perm['support_manage'] ?? false)
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.support.tickets.index'),
                        'title' => 'Support',
                        'icon' => 'support',
                    ])
                @endif
                @if ($perm['agency_view'] ?? false)
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.agency.show'),
                        'title' => 'Agency profile',
                        'icon' => 'agency',
                    ])
                @endif
                @if ($perm['staff_manage'] ?? false)
                    @include('mobile.dashboard.partials.quick-action-card', [
                        'href' => route('agent.staff.index'),
                        'title' => 'Staff',
                        'icon' => 'staff',
                    ])
                @endif
            </div>
        </section>
    </div>
@endsection
