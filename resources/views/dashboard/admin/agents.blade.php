@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agents')

@push('styles')
<style>
    /* ============================================================
       Agents page shell — same skeleton as Booking Management
       ============================================================ */
    [data-agents-page] {
        max-width: 1540px;
        margin: 0 auto;
    }

    /* KPI cards — same hover/active link treatment as bookings */
    .agents-kpi .card {
        border: 1px solid rgba(98, 105, 118, 0.16);
        height: 100%;
    }
    .agents-kpi .card-body { padding: 0.85rem 1rem; }
    .agents-kpi .h2 {
        font-size: 1.4rem;
        margin-bottom: 0;
        font-variant-numeric: tabular-nums;
    }
    .agents-kpi-link {
        display: block;
        color: inherit;
        text-decoration: none;
        border-radius: 0.5rem;
        height: 100%;
    }
    .agents-kpi-link .card {
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    .agents-kpi-link:hover .card {
        border-color: #93c5fd;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
    }
    .agents-kpi-link.is-active .card {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.18);
        background: #f8fbff;
    }

    /* Queue tabs */
    .agents-queue-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-bottom: 0.9rem;
    }
    .agents-queue-tab {
        border: 1px solid rgba(98, 105, 118, 0.2);
        border-radius: 999px;
        padding: 0.38rem 0.78rem;
        font-size: 0.8rem;
        font-weight: 600;
        color: #475569;
        text-decoration: none;
        background: #fff;
        white-space: nowrap;
        transition: background-color 0.12s ease, border-color 0.12s ease, color 0.12s ease;
    }
    .agents-queue-tab:hover {
        background: #eef5ff;
        border-color: #93c5fd;
        color: #1d4ed8;
    }
    .agents-queue-tab.is-active {
        background: #e0edff;
        border-color: #93c5fd;
        color: #1d4ed8;
    }

    /* ============================================================
       Filter panel — two-row layout (search + faceted filters) plus
       a dedicated actions row, replaces the old single flex-grid.
       ============================================================ */
    .agents-filters {
        background: #f8fafc;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        border: 1px solid rgba(98, 105, 118, 0.14);
    }
    .agents-filters .jp-label {
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 0.3rem;
        display: block;
    }
    .agents-filter-row { margin-bottom: 0.85rem; }
    .agents-filter-row:last-of-type { margin-bottom: 0; }
    .agents-filter-row-fields {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.65rem 0.75rem;
        align-items: end;
    }
    .agents-filter-field { min-width: 0; }
    .agents-filter-field .jp-control,
    .agents-filter-field .jp-control { width: 100%; }
    .agents-filter-range .agents-filter-range-inputs {
        display: flex;
        align-items: center;
        gap: 0.4rem;
    }
    .agents-filter-range .agents-filter-range-sep {
        color: #94a3b8;
        font-weight: 600;
    }
    .agents-filter-range .jp-control { flex: 1 1 0; min-width: 0; }
    .agents-filter-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
        padding-top: 0.85rem;
        margin-top: 0.85rem;
        border-top: 1px dashed rgba(148, 163, 184, 0.35);
        max-width: 100%;
    }
    .agents-filter-actions .btn {
        max-width: 100%;
        white-space: normal;
    }

    /* Search input with magnifier + clear button + typeahead suggestions */
    .agents-search-wrap {
        position: relative;
    }
    .agents-search-input {
        padding-left: 2.25rem !important;
        padding-right: 2.25rem !important;
        height: 2.4rem;
        border-radius: 8px;
        font-size: 0.92rem;
    }
    .agents-search-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
    }
    .agents-search-icon {
        position: absolute;
        top: 50%;
        left: 0.75rem;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 1.05rem;
        pointer-events: none;
    }
    .agents-search-clear {
        position: absolute;
        top: 50%;
        right: 0.5rem;
        transform: translateY(-50%);
        background: transparent;
        border: 0;
        color: #94a3b8;
        padding: 0.25rem 0.4rem;
        border-radius: 6px;
        line-height: 1;
        cursor: pointer;
    }
    .agents-search-clear:hover { color: #475569; background: rgba(148, 163, 184, 0.15); }
    .agents-search-suggestions {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        z-index: 30;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 10px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.16);
        list-style: none;
        margin: 0;
        padding: 0.25rem;
        max-height: 320px;
        overflow-y: auto;
    }
    .agents-search-suggestion {
        padding: 0.55rem 0.6rem;
        border-radius: 7px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        gap: 0.1rem;
        line-height: 1.3;
    }
    .agents-search-suggestion:hover,
    .agents-search-suggestion.is-active {
        background: #eff6ff;
    }
    .agents-search-suggestion-primary {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.88rem;
    }
    .agents-search-suggestion-secondary {
        font-size: 0.76rem;
        color: #64748b;
    }
    .agents-search-suggestion-empty {
        padding: 0.65rem 0.75rem;
        color: #94a3b8;
        font-size: 0.84rem;
        text-align: center;
    }

    /* Loading overlay above the agents table during AJAX swaps */
    .agents-table-wrap { position: relative; }
    .agents-table-loading {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 2.25rem;
        background: rgba(255, 255, 255, 0.72);
        backdrop-filter: blur(2px);
        font-size: 0.85rem;
        color: #1e293b;
        font-weight: 600;
        z-index: 5;
        border-radius: 8px;
    }
    .agents-table-loading[hidden] { display: none; }

    /* List wrapper */
    .agents-list-wrap {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(98, 105, 118, 0.12);
        background: #fff;
    }
    .agents-list-wrap .card-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid rgba(98, 105, 118, 0.08);
    }
    .agents-list-wrap .card-title {
        margin: 0;
        font-size: 0.98rem;
        font-weight: 700;
        color: #0f172a;
    }
    .agents-list-wrap .jp-card__subtitle {
        font-size: 0.78rem;
        color: #64748b;
    }
    .agents-cards {
        padding: 0.75rem;
        display: grid;
        gap: 0.65rem;
    }

    /* Agent card (modeled after .booking-queue-card) */
    .agent-queue-card {
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 10px;
        background: #fff;
        padding: 0.85rem 0.95rem;
        cursor: pointer;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        outline: none;
    }
    .agent-queue-card:hover {
        border-color: #93c5fd;
        box-shadow: 0 4px 16px rgba(37, 99, 235, 0.08);
    }
    .agent-queue-card:focus-visible {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
    }
    .agent-queue-card.is-active {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.18);
        background: #f8fbff;
    }
    .agent-card-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.25rem;
    }
    .agent-card-code {
        font-weight: 700;
        color: #0f172a;
        text-decoration: none;
        font-variant-numeric: tabular-nums;
    }
    .agent-card-code:hover { color: #1d4ed8; }
    .agent-card-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: #0f172a;
        margin-bottom: 0.1rem;
    }
    .agent-card-meta {
        font-size: 0.79rem;
        color: #475569;
        margin-bottom: 0.45rem;
    }
    .agent-card-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0.55rem;
        margin-top: 0.45rem;
    }
    .agent-card-stat {
        background: #f8fafc;
        border-radius: 8px;
        padding: 0.45rem 0.55rem;
        border: 1px solid rgba(148, 163, 184, 0.18);
    }
    .agent-card-stat-label {
        font-size: 0.65rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        font-weight: 700;
    }
    .agent-card-stat-value {
        font-size: 0.9rem;
        font-weight: 700;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }
    .agent-card-stat-value.is-warning { color: #92400e; }
    .agent-card-stat-value.is-success { color: #166534; }
    .agent-card-actions {
        display: flex;
        gap: 0.4rem;
        flex-wrap: wrap;
        margin-top: 0.55rem;
    }
    .agent-card-actions .btn {
        padding: 0.22rem 0.6rem;
        font-size: 0.76rem;
    }

    /* Status pills */
    .badge-soft-success { background: #dcfce7; color: #166534 !important; border: 1px solid #bbf7d0; }
    .badge-soft-warning { background: #fef3c7; color: #92400e !important; border: 1px solid #fde68a; }
    .badge-soft-neutral { background: #e5e7eb; color: #374151 !important; border: 1px solid #d1d5db; }
    .badge-soft-info { background: #dbeafe; color: #1e40af !important; border: 1px solid #bfdbfe; }

    /* Empty state */
    .agents-empty-state {
        border: 1px dashed rgba(148, 163, 184, 0.5);
        border-radius: 10px;
        padding: 1.5rem 0.75rem;
        text-align: center;
        color: #64748b;
        background: #f8fafc;
    }

    /* ===== Operational table — 6-column fixed layout (full-width list) ===== */
    .agents-table-wrap {
        padding: 0;
        /* Safety net only — the slim spec layout means rows always fit at >=992px,
           and the @media block below converts to stacked cards on tablet/mobile. */
        overflow-x: auto;
    }
    .agents-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.83rem;
        table-layout: fixed;
    }
    .agents-table th,
    .agents-table td {
        white-space: normal;
        vertical-align: top;
    }
    .agents-table .col-agent      { width: 18%; }
    .agents-table .col-contact    { width: 30%; }
    .agents-table .col-status     { width: 10%; }
    .agents-table .col-commission { width: 11%; }
    .agents-table .col-bookings   { width: 11%; }
    .agents-table .col-sales      { width: 14%; text-align: right; }
    .agents-table thead th {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        font-weight: 700;
        background: #f8fafc;
        border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        padding: 0.55rem 0.75rem;
        white-space: nowrap;
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .agents-table tbody td {
        padding: 0.65rem 0.75rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.85);
        vertical-align: middle;
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }
    .agents-table tbody tr {
        transition: background-color 0.12s ease;
    }
    .agents-table tbody tr:hover,
    .agents-table tbody tr.ota-admin-click-row:hover { background: #f8fbff; }
    /* Right-aligned, monospace-leaning numeric cells — applied to commission,
       bookings count, and monthly sales. Class kept for backward compat with
       any consumers that still target it (CSV export shares the convention). */
    .agents-table .col-numeric    { text-align: right; }
    .agents-table .col-bookings,
    .agents-table .col-commission { text-align: right; }
    .agents-table .agent-cell-code {
        font-weight: 700;
        color: #1d4ed8;
        display: block;
        line-height: 1.15;
    }
    .agents-table .agent-cell-agency {
        color: #475569;
        font-size: 0.76rem;
        margin-top: 0.1rem;
        display: block;
    }
    .agents-table .agent-cell-name {
        font-weight: 600;
        color: #0f172a;
        line-height: 1.15;
    }
    .agents-table .agent-cell-contactline {
        font-size: 0.76rem;
        color: #64748b;
        margin-top: 0.1rem;
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 0.25rem 0.4rem;
    }
    .agents-table .agent-cell-email {
        word-break: break-word;
    }
    .agents-table .agent-cell-phone {
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }
    .agents-table .agent-cell-sep { opacity: 0.6; }
    /* Hide the pseudo-label slot in desktop table mode; only used in stacked-card mode. */
    .agents-table tbody td::before { content: none; }

    /* ===== Responsive contract =====
       Desktop  >= 992px : standard 6-column table.
       Tablet/mobile     : every row collapses into a stacked card with
                           data-label badges so nothing is hidden behind a
                           horizontal scrollbar. */
    @media (max-width: 1024px) {
        .agent-card-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 991.98px) {
        .agents-table-wrap { overflow-x: visible; }
        .agents-table thead {
            border: 0;
            clip: rect(0 0 0 0);
            height: 1px; margin: -1px;
            overflow: hidden; padding: 0;
            position: absolute; width: 1px;
        }
        /* Reset table-layout: fixed when we collapse to stacked cards so each
           card uses the full row width and the spec's percentage column widths
           don't leak into the mobile layout. */
        .agents-table { table-layout: auto; }
        .agents-table,
        .agents-table tbody,
        .agents-table tr,
        .agents-table td {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }
        .agents-table .col-agent,
        .agents-table .col-contact,
        .agents-table .col-status,
        .agents-table .col-commission,
        .agents-table .col-bookings,
        .agents-table .col-sales { width: 100%; text-align: right; }
        .agents-table tbody tr {
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 10px;
            margin: 0.55rem 0.75rem;
            padding: 0.55rem 0.7rem;
            background: #fff;
            box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
        }
        .agents-table tbody tr.is-active {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.18);
        }
        .agents-table tbody td {
            border-bottom: 1px dashed rgba(148, 163, 184, 0.25);
            padding: 0.4rem 0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 0.75rem;
            text-align: right;
        }
        .agents-table tbody td:last-child { border-bottom: 0; }
        .agents-table tbody td::before {
            content: attr(data-label);
            font-size: 0.66rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 700;
            flex: 0 0 auto;
            text-align: left;
            padding-top: 0.15rem;
        }
        .agents-table .col-agent,
        .agents-table .col-contact { text-align: left; }
        .agents-table .col-agent::before,
        .agents-table .col-contact::before { display: none; }
        .agents-table .col-agent,
        .agents-table .col-contact {
            display: block;
            border-bottom: 1px dashed rgba(148, 163, 184, 0.25);
            padding-bottom: 0.5rem;
        }
    }
    @media (max-width: 1199.98px) {
        .agents-filter-row-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 1199.98px) {
        .agents-filter-row-fields { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 991.98px) {
        .agents-filter-row-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767.98px) {
        .agents-filter-row-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .agents-filter-actions .ms-auto { margin-left: 0 !important; }
    }
    @media (max-width: 640px) {
        .agents-cards { padding: 0.5rem; }
        .agents-filters .btn { width: 100%; }
        .agent-card-actions .btn { flex: 1 1 30%; text-align: center; }
        .agents-kpi .h2 { font-size: 1.2rem; }
        .agents-filter-row-fields { grid-template-columns: 1fr; }
        .agents-filter-actions { flex-direction: column; align-items: stretch; }
        .agents-filter-actions .ms-auto { margin-left: 0 !important; margin-top: 0.25rem; }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between ota-admin-page-header" data-testid="ota-agents-page-header">
        <div class="col">
            <div class="page-pretitle">Network</div>
            <h1 class="jp-page-title">Agents management</h1>
            <div class="text-secondary mt-1">
                Manage agent accounts, commission rates, sales performance, and booking activity.
            </div>
        </div>
        <div class="col-auto ms-auto">
            <div class="btn-list ota-r-action-bar" data-testid="ota-agents-page-actions">
                <a href="{{ route('admin.agent-applications.index') }}" class="jp-btn jp-btn--ghost" data-testid="ota-agents-action-review-applications">
                    <i class="ti ti-clipboard-check me-1"></i> Review applications
                </a>
                <a href="{{ route('admin.users.create') }}" class="jp-btn jp-btn--primary" data-testid="ota-agents-action-add-agent">
                    <i class="ti ti-user-plus me-1"></i> Add agent
                </a>
            </div>
        </div>
    </div>
@endsection

@section('content')
@php
    // Page-level lightweight context — heavy badge helpers live inside the
    // table-rows / preview-body partials so AJAX swaps stay self-contained.
    $a = $selectedAgent;
    $f = $filters ?? [];
    $tabs = $queueTabs ?? ['all' => 'All agents'];
    $activeQueue = $activeQueue ?? 'all';
    $totalAgents = (int) ($agentsTotalCount ?? 0);
    $listedCount = is_countable($agents) ? count($agents) : 0;
@endphp

<div data-agents-page>
    {{-- ===== Top KPI cards (clickable to set queue) ===== --}}
    {{-- Six tiles, aligned with recommended Agent page structure: --}}
    {{-- Total · Active · Pending applications · Monthly sales · Pending commission · Unpaid balance --}}
    <div class="row row-cards agents-kpi mb-3 ota-admin-kpi-card" data-agents-kpis>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="agents-kpi-link {{ $activeQueue === 'all' ? 'is-active' : '' }}" href="{{ route('admin.agents', array_merge(request()->except('page'), ['queue' => 'all'])) }}" data-testid="ota-agents-kpi-total">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Total agents</div>
                        <div class="h2">{{ number_format((int) ($kpis['total'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="agents-kpi-link {{ $activeQueue === 'active' ? 'is-active' : '' }}" href="{{ route('admin.agents', array_merge(request()->except('page'), ['queue' => 'active'])) }}" data-testid="ota-agents-kpi-active">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Active agents</div>
                        <div class="h2 text-success">{{ number_format((int) ($kpis['active'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="agents-kpi-link" href="{{ route('admin.agent-applications.index', ['status' => 'pending']) }}" data-testid="ota-agents-kpi-pending-applications">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Pending applications</div>
                        <div class="h2 text-warning">{{ number_format((int) ($kpis['pending_applications'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="agents-kpi-link" href="{{ route('admin.agents', array_merge(request()->except('page'), ['queue' => 'all'])) }}" data-testid="ota-agents-kpi-monthly">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Monthly agent sales</div>
                        <div class="h2">Rs {{ number_format((int) round((float) ($kpis['monthly_sales'] ?? 0))) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="agents-kpi-link" href="{{ route('admin.commissions.index') }}" data-testid="ota-agents-kpi-pending-commission">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Pending commission</div>
                        <div class="h2 text-warning">Rs {{ number_format((int) round((float) ($kpis['commission_pending_total'] ?? 0))) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="agents-kpi-link {{ $activeQueue === 'with_balance' ? 'is-active' : '' }}" href="{{ route('admin.agents', array_merge(request()->except('page'), ['queue' => 'with_balance'])) }}" data-testid="ota-agents-kpi-unpaid-balance">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Unpaid agent balance</div>
                        <div class="h2 text-danger">Rs {{ number_format((int) round((float) ($kpis['outstanding'] ?? 0))) }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    {{-- ===== Queue tabs ===== --}}
    <div class="agents-queue-tabs ota-admin-queue-tabs" data-agents-tabs>
        @foreach ($tabs as $queueKey => $queueLabel)
            <a href="{{ route('admin.agents', array_merge(request()->except('page'), ['queue' => $queueKey])) }}"
               class="agents-queue-tab ota-admin-queue-tab {{ $activeQueue === $queueKey ? 'is-active' : '' }}"
               data-testid="ota-agents-queue-{{ $queueKey }}">
                {{ $queueLabel }}
            </a>
        @endforeach
    </div>

    <div class="row g-4">
        {{-- ===== Filters + agent list (full width) ===== --}}
        <div class="col-12">
            <div class="agents-filters ota-admin-filter-bar mb-3" data-agents-filter-bar>
                <form method="get"
                      action="{{ route('admin.agents') }}"
                      id="agents-filter-form"
                      autocomplete="off"
                      data-agents-filter-form>
                    <input type="hidden" name="queue" value="{{ $activeQueue }}">
                    <input type="hidden" name="agent_id" id="agents-filter-agent-id" value="">

                    {{-- ============== Row 1 — Search with typeahead ============== --}}
                    <div class="agents-filter-row agents-filter-row-search" data-testid="ota-agents-filter-row-search">
                        <label class="jp-label" for="agents-search-input">Search agent</label>
                        <div class="agents-search-wrap" data-agents-search>
                            <i class="ti ti-search agents-search-icon" aria-hidden="true"></i>
                            <input id="agents-search-input"
                                   type="text"
                                   name="search"
                                   class="jp-control agents-search-input"
                                   placeholder="Search by agent code, agency, contact name, email or phone…"
                                   value="{{ $f['search'] ?? '' }}"
                                   autocomplete="off"
                                   role="combobox"
                                   aria-autocomplete="list"
                                   aria-expanded="false"
                                   aria-controls="agents-search-suggestions"
                                   aria-haspopup="listbox">
                            <button type="button"
                                    class="agents-search-clear"
                                    data-agents-search-clear
                                    aria-label="Clear search"
                                    hidden>
                                <i class="ti ti-x" aria-hidden="true"></i>
                            </button>
                            <ul id="agents-search-suggestions"
                                class="agents-search-suggestions"
                                role="listbox"
                                aria-label="Agent suggestions"
                                data-agents-suggestions
                                hidden></ul>
                        </div>
                    </div>

                    {{-- ============== Row 2 — Status / City / Commission / Sales / Dates ============== --}}
                    <div class="agents-filter-row agents-filter-row-fields" data-testid="ota-agents-filter-row-fields">
                        <div class="agents-filter-field">
                            <label class="jp-label" for="agents-status-input">Status</label>
                            <select id="agents-status-input" class="jp-control" name="status">
                                <option value="">All statuses</option>
                                <option value="active" @selected(($f['status'] ?? '') === 'active')>Active</option>
                                <option value="inactive" @selected(($f['status'] ?? '') === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="agents-filter-field">
                            <label class="jp-label" for="agents-city-input">City</label>
                            <select id="agents-city-input" class="jp-control" name="city">
                                <option value="">All cities</option>
                                @foreach ($cities as $city)
                                    <option value="{{ $city }}" @selected(($f['city'] ?? '') === $city)>{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="agents-filter-field">
                            <label class="jp-label" for="agents-commission-filter">Commission</label>
                            <select id="agents-commission-filter" class="jp-control" name="commission_filter">
                                <option value="">Any rate</option>
                                <option value="zero" @selected(($f['commission_filter'] ?? '') === 'zero')>Zero</option>
                                <option value="below_5" @selected(($f['commission_filter'] ?? '') === 'below_5')>Below 5%</option>
                                <option value="5_to_10" @selected(($f['commission_filter'] ?? '') === '5_to_10')>5% – 10%</option>
                                <option value="above_10" @selected(($f['commission_filter'] ?? '') === 'above_10')>Above 10%</option>
                            </select>
                        </div>
                        <div class="agents-filter-field agents-filter-range" role="group" aria-labelledby="agents-sales-range-label">
                            <span class="jp-label" id="agents-sales-range-label">Sales range</span>
                            <div class="agents-filter-range-inputs">
                                <input id="agents-sales-from"
                                       type="number"
                                       min="0"
                                       step="1"
                                       name="sales_from"
                                       class="jp-control"
                                       placeholder="From"
                                       aria-label="Sales from"
                                       value="{{ $f['sales_from'] ?? '' }}">
                                <span class="agents-filter-range-sep" aria-hidden="true">—</span>
                                <input id="agents-sales-to"
                                       type="number"
                                       min="0"
                                       step="1"
                                       name="sales_to"
                                       class="jp-control"
                                       placeholder="To"
                                       aria-label="Sales to"
                                       value="{{ $f['sales_to'] ?? '' }}">
                            </div>
                        </div>
                        <div class="agents-filter-field agents-filter-range" role="group" aria-labelledby="agents-created-range-label">
                            <span class="jp-label" id="agents-created-range-label">Created date</span>
                            <div class="agents-filter-range-inputs">
                                <input id="agents-created-from"
                                       type="date"
                                       name="created_from"
                                       class="jp-control"
                                       aria-label="Created from"
                                       value="{{ $f['created_from'] ?? '' }}">
                                <span class="agents-filter-range-sep" aria-hidden="true">—</span>
                                <input id="agents-created-to"
                                       type="date"
                                       name="created_to"
                                       class="jp-control"
                                       aria-label="Created to"
                                       value="{{ $f['created_to'] ?? '' }}">
                            </div>
                        </div>
                    </div>

                    {{-- ============== Actions row ============== --}}
                    <div class="agents-filter-actions ota-r-action-bar" data-testid="ota-agents-filter-actions">
                        <button type="submit" class="jp-btn jp-btn--primary">
                            <i class="ti ti-filter me-1"></i> Apply filters
                        </button>
                        <a href="{{ route('admin.agents') }}" class="jp-btn jp-btn--ghost" data-agents-reset>
                            <i class="ti ti-x me-1"></i> Reset
                        </a>
                        <a href="{{ route('admin.agents.export', request()->query()) }}"
                           class="jp-btn jp-btn--ghost ms-auto"
                           data-testid="ota-agents-export-csv"
                           data-agents-export-link>
                            <i class="ti ti-download me-1"></i> Export agents CSV
                        </a>
                    </div>
                </form>
            </div>

            <div class="card agents-list-wrap" data-agents-list>
                <div class="jp-card__head">
                    <div>
                        <h3 class="jp-card__title" id="agents-list-title">{{ $tabs[$activeQueue] ?? 'Agents' }}</h3>
                        <div class="jp-card__subtitle" id="agents-list-subtitle" data-agents-list-subtitle>
                            @php
                                $hasFilters = ($f['search'] ?? '') !== ''
                                    || ($f['city'] ?? '') !== ''
                                    || ($f['status'] ?? '') !== ''
                                    || ($f['commission_filter'] ?? '') !== ''
                                    || ($f['sales_from'] ?? '') !== ''
                                    || ($f['sales_to'] ?? '') !== ''
                                    || ($f['created_from'] ?? '') !== ''
                                    || ($f['created_to'] ?? '') !== '';
                            @endphp
                            Showing <strong data-agents-listed>{{ number_format($listedCount) }}</strong>
                            of <strong data-agents-total>{{ number_format($totalAgents) }}</strong>
                            agent{{ $totalAgents === 1 ? '' : 's' }}@if ($hasFilters)<span data-agents-filters-applied> · filters applied</span>@endif
                        </div>
                    </div>
                </div>

                <div class="agents-table-wrap position-relative" data-testid="ota-agents-table-wrap">
                    {{-- AJAX swap target: only the inner content (table or empty state) is replaced. --}}
                    <div id="agents-table-body" data-agents-table-body>
                        @include('dashboard.admin.partials.agents-table-rows', [
                            'agents' => $agents,
                            'a' => $a,
                            'totalAgents' => $totalAgents,
                            'hasFilters' => $hasFilters || $activeQueue !== 'all',
                        ])
                    </div>
                    {{-- Loading overlay shown by JS while AJAX fetches new rows. --}}
                    <div class="agents-table-loading" data-agents-table-loading hidden role="status" aria-live="polite">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        Loading agents...
                    </div>
                </div>

                <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="small text-secondary">
                        Showing {{ number_format($listedCount) }} of {{ number_format($totalAgents) }} total
                    </div>
                    <div class="ota-r-action-bar">
                        <a href="{{ route('admin.commissions.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">
                            <i class="ti ti-coin me-1"></i> Commissions ledger
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var listWrap        = document.querySelector('[data-agents-list]');
        var tableBody       = document.getElementById('agents-table-body');
        var tableLoading    = document.querySelector('[data-agents-table-loading]');
        var listSubtitle    = document.querySelector('[data-agents-list-subtitle]');
        var listTitle       = document.getElementById('agents-list-title');
        var form            = document.getElementById('agents-filter-form');
        var searchWrap      = document.querySelector('[data-agents-search]');
        var searchInput     = document.getElementById('agents-search-input');
        var clearBtn        = document.querySelector('[data-agents-search-clear]');
        var suggestionsList = document.getElementById('agents-search-suggestions');
        var hiddenAgentId   = document.getElementById('agents-filter-agent-id');
        var exportLink      = document.querySelector('[data-agents-export-link]');
        if (!listWrap || !tableBody || !form) return;

        var dataUrl        = @json(route('admin.agents.data'));
        var suggestionsUrl = @json(route('admin.agents.suggestions'));
        var exportUrl      = @json(route('admin.agents.export'));
        var queueLabels    = @json($queueTabs ?? ['all' => 'All agents']);

        var state = {
            tableLoading: false,
            tableController: null,
            suggestController: null,
            filterTimer: null,
            suggestTimer: null,
            suggestionItems: [],
            activeSuggestion: -1,
            suggestionsVisible: false,
        };

        function esc(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function syncFiltersInUrl(params) {
            try {
                var url = new URL(window.location.href);
                ['search', 'status', 'city', 'commission_filter', 'sales_from', 'sales_to', 'created_from', 'created_to', 'queue'].forEach(function (key) {
                    var v = params.get(key);
                    if (v && String(v).trim() !== '') {
                        url.searchParams.set(key, v);
                    } else {
                        url.searchParams.delete(key);
                    }
                });
                url.searchParams.delete('preview');
                window.history.replaceState({}, '', url.toString());
            } catch (e) {}
        }

        function setTableLoading(on) {
            state.tableLoading = on;
            if (tableLoading) {
                if (on) {
                    tableLoading.removeAttribute('hidden');
                } else {
                    tableLoading.setAttribute('hidden', '');
                }
            }
        }


        function currentFilterParams() {
            var fd = new FormData(form);
            var params = new URLSearchParams();
            fd.forEach(function (v, k) {
                if (k === 'agent_id') return; // hidden bookkeeping field; not part of URL
                if (String(v).trim() === '') return;
                params.append(k, String(v));
            });
            return params;
        }

        function refreshExportLink(params) {
            if (!exportLink) return;
            var qs = params.toString();
            exportLink.setAttribute('href', exportUrl + (qs ? ('?' + qs) : ''));
        }

        function updateSubtitle(listed, total, hasFilters, queueKey) {
            if (!listSubtitle) return;
            var label = (queueLabels && queueLabels[queueKey]) || 'Agents';
            if (listTitle) listTitle.textContent = label;
            var totalLabel = total === 1 ? 'agent' : 'agents';
            listSubtitle.innerHTML = 'Showing <strong data-agents-listed>' + esc(listed) + '</strong> of <strong data-agents-total>'
                + esc(total) + '</strong> ' + totalLabel
                + (hasFilters ? '<span data-agents-filters-applied> · filters applied</span>' : '');
        }

        function fetchAgentsData(opts) {
            opts = opts || {};
            var params = currentFilterParams();

            if (state.tableController && typeof state.tableController.abort === 'function') {
                state.tableController.abort();
            }
            if (typeof AbortController === 'function') {
                state.tableController = new AbortController();
            }

            setTableLoading(true);
            var fetchOpts = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
            if (state.tableController) fetchOpts.signal = state.tableController.signal;

            return fetch(dataUrl + '?' + params.toString(), fetchOpts)
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (json) {
                    if (!json || typeof json.rows_html !== 'string') throw new Error('Invalid payload');
                    tableBody.innerHTML = json.rows_html;
                    updateSubtitle(json.listed_count || 0, json.total_count || 0, !!json.has_filters_applied, params.get('queue') || 'all');
                    refreshExportLink(params);
                    syncFiltersInUrl(params);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    // Last resort: fall back to a real page submit so the user is never stuck.
                    if (!opts.silent) form.submit();
                })
                .finally(function () { setTableLoading(false); });
        }

        var debouncedFilterFetch = function () {
            if (state.filterTimer) clearTimeout(state.filterTimer);
            state.filterTimer = setTimeout(function () { fetchAgentsData(); }, 300);
        };

        // ---------- Typeahead ----------
        function showSuggestions() {
            if (!suggestionsList) return;
            suggestionsList.removeAttribute('hidden');
            state.suggestionsVisible = true;
            if (searchInput) searchInput.setAttribute('aria-expanded', 'true');
        }

        function hideSuggestions() {
            if (!suggestionsList) return;
            suggestionsList.setAttribute('hidden', '');
            state.suggestionsVisible = false;
            state.activeSuggestion = -1;
            if (searchInput) searchInput.setAttribute('aria-expanded', 'false');
        }

        function renderSuggestions(items) {
            if (!suggestionsList) return;
            state.suggestionItems = Array.isArray(items) ? items : [];
            state.activeSuggestion = -1;
            if (state.suggestionItems.length === 0) {
                suggestionsList.innerHTML = '<li class="agents-search-suggestion-empty" role="presentation">No matching agents.</li>';
                showSuggestions();
                return;
            }
            var html = state.suggestionItems.map(function (s, idx) {
                return '<li class="agents-search-suggestion" role="option" id="agents-suggest-' + idx + '"'
                    + ' data-agent-id="' + esc(s.id) + '"'
                    + ' data-agent-code="' + esc(s.code) + '"'
                    + ' data-preview-url="' + esc(s.preview_url) + '">'
                    + '<span class="agents-search-suggestion-primary">' + esc(s.primary_line) + '</span>'
                    + '<span class="agents-search-suggestion-secondary">' + esc(s.secondary_line) + '</span>'
                    + '</li>';
            }).join('');
            suggestionsList.innerHTML = html;
            showSuggestions();
        }

        function setActiveSuggestion(index) {
            if (!suggestionsList) return;
            var lis = suggestionsList.querySelectorAll('.agents-search-suggestion');
            if (!lis.length) return;
            var bounded = (index + lis.length) % lis.length;
            lis.forEach(function (li, i) { li.classList.toggle('is-active', i === bounded); });
            state.activeSuggestion = bounded;
            var active = lis[bounded];
            if (active && searchInput) {
                searchInput.setAttribute('aria-activedescendant', active.id);
                if (typeof active.scrollIntoView === 'function') {
                    active.scrollIntoView({ block: 'nearest' });
                }
            }
        }

        function showSearchingIndicator() {
            if (!suggestionsList) return;
            // Spec PART G: while autocomplete is in flight, surface a
            // "Searching..." line so the user knows we're actively looking.
            suggestionsList.innerHTML = '<li class="agents-search-suggestion-empty" role="presentation" data-agents-suggest-loading>'
                + '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Searching...'
                + '</li>';
            showSuggestions();
        }

        function fetchSuggestions(q) {
            if (!suggestionsUrl || !suggestionsList) return;
            if (!q || q.length < 2) {
                renderSuggestions([]);
                hideSuggestions();
                return;
            }
            if (state.suggestController && typeof state.suggestController.abort === 'function') {
                state.suggestController.abort();
            }
            if (typeof AbortController === 'function') {
                state.suggestController = new AbortController();
            }
            showSearchingIndicator();
            var opts = { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } };
            if (state.suggestController) opts.signal = state.suggestController.signal;
            fetch(suggestionsUrl + '?q=' + encodeURIComponent(q), opts)
                .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(function (json) {
                    renderSuggestions((json && json.suggestions) || []);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    renderSuggestions([]);
                });
        }

        function selectSuggestion(item) {
            if (!item) return;
            if (searchInput) searchInput.value = item.code || '';
            if (hiddenAgentId) hiddenAgentId.value = String(item.id || '');
            hideSuggestions();
            updateClearVisibility();
            fetchAgentsData();
        }

        function updateClearVisibility() {
            if (!clearBtn || !searchInput) return;
            if (searchInput.value && searchInput.value.length > 0) {
                clearBtn.removeAttribute('hidden');
            } else {
                clearBtn.setAttribute('hidden', '');
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (state.filterTimer) { clearTimeout(state.filterTimer); state.filterTimer = null; }
            fetchAgentsData();
        });
        form.querySelectorAll('select, input[type="number"], input[type="date"]').forEach(function (el) {
            el.addEventListener('change', debouncedFilterFetch);
        });

        // Reset link — submit empty form via AJAX rather than navigating
        var resetLink = form.querySelector('[data-agents-reset]');
        if (resetLink) {
            resetLink.addEventListener('click', function (event) {
                event.preventDefault();
                form.querySelectorAll('select, input').forEach(function (el) {
                    if (el.type === 'hidden' && el.name === 'queue') return; // keep queue
                    if (el.type === 'hidden') { el.value = ''; return; }
                    if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    } else {
                        el.value = '';
                    }
                });
                updateClearVisibility();
                hideSuggestions();
                fetchAgentsData();
            });
        }

        // Search input: debounced filter fetch + suggestions fetch
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                updateClearVisibility();
                debouncedFilterFetch();
                if (state.suggestTimer) clearTimeout(state.suggestTimer);
                var q = (searchInput.value || '').trim();
                if (q.length < 2) {
                    renderSuggestions([]);
                    hideSuggestions();
                    return;
                }
                state.suggestTimer = setTimeout(function () { fetchSuggestions(q); }, 180);
            });
            searchInput.addEventListener('focus', function () {
                if (state.suggestionItems.length > 0) showSuggestions();
            });
            searchInput.addEventListener('keydown', function (event) {
                if (!state.suggestionsVisible || state.suggestionItems.length === 0) {
                    if (event.key === 'Escape') hideSuggestions();
                    return;
                }
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    setActiveSuggestion(state.activeSuggestion + 1);
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    setActiveSuggestion(state.activeSuggestion - 1);
                } else if (event.key === 'Enter') {
                    if (state.activeSuggestion >= 0) {
                        event.preventDefault();
                        selectSuggestion(state.suggestionItems[state.activeSuggestion]);
                    }
                } else if (event.key === 'Escape') {
                    hideSuggestions();
                }
            });
        }

        if (suggestionsList) {
            suggestionsList.addEventListener('mousedown', function (event) {
                // mousedown so it fires before the input blur that hides the list.
                var li = event.target.closest('.agents-search-suggestion');
                if (!li) return;
                event.preventDefault();
                var idx = Array.prototype.indexOf.call(suggestionsList.children, li);
                if (idx >= 0 && state.suggestionItems[idx]) {
                    selectSuggestion(state.suggestionItems[idx]);
                }
            });
        }

        // Click outside the search wrap — hide dropdown
        document.addEventListener('click', function (event) {
            if (!searchWrap || searchWrap.contains(event.target)) return;
            hideSuggestions();
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.focus();
                }
                if (hiddenAgentId) hiddenAgentId.value = '';
                hideSuggestions();
                updateClearVisibility();
                fetchAgentsData();
            });
        }

        updateClearVisibility();
    })();
</script>
@endpush

