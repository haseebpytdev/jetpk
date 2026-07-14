@extends('layouts.mobile-app')

@section('title', 'Agency Reports')

@section('mobile_app_title', 'Reports')

@section('content')
    @php
        $f = $filters ?? [];
        $s = $summary ?? [];
        $moneyPrefix = 'Rs ';
    @endphp

    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-reports">
        <form method="get" action="{{ route('agent.reports.index') }}" class="ota-mobile-agent__filters ota-mobile-agent__filters--form" data-testid="agent-reports-filters">
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_from">From</label>
                <input type="date" name="date_from" id="date_from" class="ota-mobile-agent__input" value="{{ $f['date_from'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="date_to">To</label>
                <input type="date" name="date_to" id="date_to" class="ota-mobile-agent__input" value="{{ $f['date_to'] ?? '' }}">
            </div>
            <div class="ota-mobile-agent__field">
                <label class="ota-mobile-agent__label" for="status">Status</label>
                <select name="status" id="status" class="ota-mobile-agent__input">
                    <option value="all">All statuses</option>
                    @foreach ($bookingStatusOptions ?? [] as $status)
                        <option value="{{ $status }}" @selected(($f['status'] ?? 'all') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ota-mobile-agent__actions">
                <button type="submit" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Apply</button>
                @if (array_filter($f))
                    <a href="{{ route('agent.reports.index') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--secondary">Clear</a>
                @endif
            </div>
        </form>

        <section class="ota-mobile-agent__card" data-testid="agent-reports-summary">
            <h2 class="ota-mobile-agent__card-title">Summary</h2>
            <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                <div>
                    <dt>Gross sales</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($s['gross_sales'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Net revenue</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($s['net_revenue'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Total bookings</dt>
                    <dd class="ota-mobile-agent__amount">{{ number_format((int) ($s['total_bookings'] ?? 0)) }}</dd>
                </div>
                <div>
                    <dt>Refunds paid</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($s['refund_paid_amount'] ?? 0), 2) }}</dd>
                </div>
                <div>
                    <dt>Outstanding</dt>
                    <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($s['outstanding_balance'] ?? 0), 2) }}</dd>
                </div>
            </dl>
        </section>

        @if (empty($hasLiveData))
            <div class="ota-mobile-agent__empty" data-testid="agent-reports-empty">
                <p class="ota-mobile-agent__empty-title">No report data yet</p>
                <p class="ota-mobile-agent__empty-help">Bookings in your agency will appear here once created.</p>
            </div>
        @else
            <section class="ota-mobile-agent__list" aria-label="Top routes">
                <div class="ota-mobile-agent__card-head">
                    <h2 class="ota-mobile-agent__card-title">Top routes</h2>
                </div>
                @forelse ($topRoutes ?? [] as $row)
                    <article class="ota-mobile-agent__card">
                        <p class="ota-mobile-agent__route">{{ $row['route'] ?? '—' }}</p>
                        <dl class="ota-mobile-agent__meta ota-mobile-agent__meta--finance">
                            <div>
                                <dt>Bookings</dt>
                                <dd class="ota-mobile-agent__amount">{{ (int) ($row['bookings'] ?? 0) }}</dd>
                            </div>
                            <div>
                                <dt>Sales</dt>
                                <dd class="ota-mobile-agent__amount">{{ $moneyPrefix }}{{ number_format((float) ($row['sales'] ?? 0), 2) }}</dd>
                            </div>
                        </dl>
                    </article>
                @empty
                    <div class="ota-mobile-agent__card">
                        <p class="ota-mobile-agent__note">No routes in this date range.</p>
                    </div>
                @endforelse
            </section>
        @endif
    </div>
@endsection
