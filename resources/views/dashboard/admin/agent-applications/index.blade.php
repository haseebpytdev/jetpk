@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agent applications')

@php
    $f = $filters ?? [];
    $hasFilters = (bool) ($hasFilters ?? (
        ($f['search'] ?? '') !== ''
        || ($f['status'] ?? '') !== ''
        || ($f['submitted_from'] ?? '') !== ''
        || ($f['submitted_to'] ?? '') !== ''
        || ($f['city_country'] ?? '') !== ''
        || ! empty($f['duplicate_only'])
    ));

    $exportQuery = request()->only(['search', 'status', 'submitted_from', 'submitted_to', 'city_country', 'duplicate_only']);

    $statusBadgeFor = fn (string $status): string => match ($status) {
        'pending' => 'badge-soft-warning',
        'approved' => 'badge-soft-success',
        'rejected' => 'badge-soft-danger',
        'needs_more_info' => 'badge-soft-purple',
        default => 'badge-soft-neutral',
    };

    $statusLabelFor = fn (string $status): string => match ($status) {
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'needs_more_info' => 'Needs info',
        default => ucfirst(str_replace('_', ' ', $status)),
    };

    $duplicateKeys = $duplicateEmailKeys ?? [];
    $convertedKeys = $convertedEmailKeys ?? [];
    $duplicateCounts = $duplicateEmailCounts ?? [];
@endphp

@push('styles')
<style>
    [data-agent-applications-page] {
        max-width: 1540px;
        margin: 0 auto;
    }

    .applications-kpi .card {
        border: 1px solid rgba(98, 105, 118, 0.16);
        height: 100%;
    }
    .applications-kpi .card-body {
        padding: 0.85rem 1rem;
    }
    .applications-kpi .h2 {
        font-size: 1.4rem;
        margin-bottom: 0;
        font-variant-numeric: tabular-nums;
    }
    .applications-kpi-link {
        display: block;
        color: inherit;
        text-decoration: none;
        border-radius: 0.5rem;
        height: 100%;
    }
    .applications-kpi-link .card {
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    .applications-kpi-link:hover .card {
        border-color: #93c5fd;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.08);
    }

    .applications-filters {
        background: #f8fafc;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        border: 1px solid rgba(98, 105, 118, 0.14);
    }
    .applications-filters .jp-label {
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 0.3rem;
        display: block;
    }
    .applications-filter-row {
        display: grid;
        grid-template-columns: minmax(10rem, 1.4fr) minmax(7rem, 0.9fr) minmax(8rem, 0.95fr) minmax(8rem, 0.95fr) minmax(8rem, 1fr) minmax(6.5rem, auto) auto auto auto;
        gap: 0.65rem 0.75rem;
        align-items: end;
    }
    .applications-filter-field {
        min-width: 0;
    }
    .applications-filter-field .jp-control,
    .applications-filter-field .jp-control {
        width: 100%;
    }
    .applications-filter-checkbox {
        display: flex;
        align-items: center;
        gap: 0.45rem;
        min-height: 2rem;
        padding-bottom: 0.1rem;
        font-size: 0.84rem;
        color: #334155;
        white-space: nowrap;
    }
    .applications-filter-actions-inline {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        align-items: center;
    }

    .applications-table-wrap {
        padding: 0;
        overflow-x: auto;
    }
    .applications-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.83rem;
        table-layout: fixed;
    }
    .applications-table th,
    .applications-table td {
        white-space: normal;
        vertical-align: middle;
    }
    .applications-table .col-applicant { width: 16%; }
    .applications-table .col-company { width: 16%; }
    .applications-table .col-contact { width: 22%; }
    .applications-table .col-status { width: 10%; }
    .applications-table .col-submitted { width: 12%; }
    .applications-table .col-flags { width: 14%; }
    .applications-table .col-action { width: 10%; text-align: right; }
    .applications-table thead th {
        font-size: 0.68rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #475569;
        font-weight: 700;
        background: #f8fafc;
        border-bottom: 1px solid rgba(148, 163, 184, 0.35);
        padding: 0.5rem 0.65rem;
        white-space: nowrap;
    }
    .applications-table tbody td {
        padding: 0.45rem 0.65rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.85);
        color: #0f172a;
        font-variant-numeric: tabular-nums;
    }
    .applications-table tbody tr:hover {
        background: #f8fbff;
    }
    .application-primary {
        font-weight: 700;
        color: #1d4ed8;
        text-decoration: none;
        line-height: 1.2;
        display: inline-block;
    }
    .application-primary:hover {
        color: #1e40af;
        text-decoration: underline;
    }
    .application-contact-email {
        display: block;
        font-size: 0.8rem;
        color: #334155;
        word-break: break-word;
    }
    .application-contact-phone {
        display: block;
        font-size: 0.76rem;
        color: #64748b;
        margin-top: 0.1rem;
    }
    .applications-flags {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
    }

    .badge-soft-success { background: #dcfce7; color: #166534 !important; border: 1px solid #bbf7d0; }
    .badge-soft-warning { background: #fef3c7; color: #92400e !important; border: 1px solid #fde68a; }
    .badge-soft-danger { background: #fee2e2; color: #991b1b !important; border: 1px solid #fecaca; }
    .badge-soft-purple { background: #ede9fe; color: #5b21b6 !important; border: 1px solid #ddd6fe; }
    .badge-soft-neutral { background: #e5e7eb; color: #374151 !important; border: 1px solid #d1d5db; }
    .badge-soft-converted { background: #e0f2fe; color: #075985 !important; border: 1px solid #bae6fd; }
    .badge-soft-duplicate { background: #ffedd5; color: #9a3412 !important; border: 1px solid #fed7aa; }

    .applications-empty-state {
        border: 1px dashed rgba(148, 163, 184, 0.5);
        border-radius: 10px;
        padding: 2rem 1rem;
        text-align: center;
        color: #64748b;
        background: #f8fafc;
    }
    .applications-empty-state h3 {
        color: #0f172a;
        font-size: 1.05rem;
        margin-bottom: 0.35rem;
    }

    .applications-search-wrap {
        position: relative;
    }
    .applications-search-clear {
        position: absolute;
        right: 0.35rem;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        color: #94a3b8;
        padding: 0.15rem 0.25rem;
        line-height: 1;
    }
    .applications-search-clear:hover {
        color: #475569;
    }
    .applications-search-suggestions {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        z-index: 30;
        margin: 0;
        padding: 0.35rem;
        list-style: none;
        background: #fff;
        border: 1px solid rgba(148, 163, 184, 0.45);
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        max-height: 280px;
        overflow-y: auto;
    }
    .applications-search-suggestion {
        padding: 0.55rem 0.6rem;
        border-radius: 7px;
        cursor: pointer;
    }
    .applications-search-suggestion:hover,
    .applications-search-suggestion.is-active {
        background: #eff6ff;
    }
    .applications-search-suggestion-primary {
        display: block;
        font-weight: 700;
        color: #0f172a;
        font-size: 0.88rem;
    }
    .applications-search-suggestion-secondary {
        display: block;
        font-size: 0.76rem;
        color: #64748b;
    }
    .applications-search-suggestion-empty {
        padding: 0.65rem 0.75rem;
        color: #94a3b8;
        font-size: 0.84rem;
    }
    .applications-table-panel {
        position: relative;
        min-height: 4rem;
    }
    .applications-table-loading {
        position: absolute;
        inset: 0;
        z-index: 5;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.72);
        font-size: 0.88rem;
        color: #475569;
    }
    .applications-table-loading[hidden] {
        display: none !important;
    }

    @media (max-width: 1199.98px) {
        .applications-filter-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        .applications-filter-actions-inline {
            grid-column: 1 / -1;
        }
    }
    @media (max-width: 767.98px) {
        .applications-filter-row {
            grid-template-columns: 1fr;
        }
        .applications-table thead {
            display: none;
        }
        .applications-table,
        .applications-table tbody,
        .applications-table tr,
        .applications-table td {
            display: block;
            width: 100%;
        }
        .applications-table tbody tr {
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 10px;
            margin-bottom: 0.65rem;
            padding: 0.35rem 0;
            background: #fff;
        }
        .applications-table tbody td {
            border: 0;
            padding: 0.35rem 0.75rem;
        }
        .applications-table tbody td::before {
            content: attr(data-label);
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 0.15rem;
        }
        .applications-table .col-action {
            text-align: left;
        }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between" data-testid="ota-agent-applications-page-header">
        <div class="col">
            <div class="page-pretitle">Partner onboarding</div>
            <h1 class="jp-page-title">Agent applications</h1>
            <div class="text-secondary mt-1">
                Review partner applications, approve qualified agents, and track onboarding status.
            </div>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.agent-applications.export', $exportQuery) }}"
               class="jp-btn jp-btn--ghost"
               data-applications-export-link
               data-testid="ota-agent-applications-export-csv-header">
                <i class="ti ti-download me-1"></i> Export applications CSV
            </a>
        </div>
    </div>
@endsection

@section('content')
<div data-agent-applications-page>
    <div class="row row-cards applications-kpi mb-3" data-testid="ota-agent-applications-kpis">
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="applications-kpi-link" href="{{ route('admin.agent-applications.index') }}">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Total applications</div>
                        <div class="h2">{{ number_format((int) ($kpis['total'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="applications-kpi-link" href="{{ route('admin.agent-applications.index', ['status' => 'pending']) }}">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Pending review</div>
                        <div class="h2 text-warning">{{ number_format((int) ($kpis['pending'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="applications-kpi-link" href="{{ route('admin.agent-applications.index', ['status' => 'approved']) }}">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Approved</div>
                        <div class="h2 text-success">{{ number_format((int) ($kpis['approved'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="applications-kpi-link" href="{{ route('admin.agent-applications.index', ['status' => 'rejected']) }}">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Rejected</div>
                        <div class="h2 text-danger">{{ number_format((int) ($kpis['rejected'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <div class="card card-sm h-100">
                <div class="jp-card__body">
                    <div class="text-secondary small">Converted to agent</div>
                    <div class="h2">{{ number_format((int) ($kpis['converted'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xl-2">
            <a class="applications-kpi-link" href="{{ route('admin.agent-applications.index', ['duplicate_only' => 1]) }}">
                <div class="card card-sm">
                    <div class="jp-card__body">
                        <div class="text-secondary small">Duplicate emails</div>
                        <div class="h2">{{ number_format((int) ($kpis['duplicates'] ?? 0)) }}</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="jp-card" data-testid="ota-agent-applications-filter-card">
        <div class="card-body applications-filters">
            <form method="GET"
                  action="{{ route('admin.agent-applications.index') }}"
                  id="agent-applications-filter-form"
                  class="applications-filter-form">
                <div class="applications-filter-row">
                    <div class="applications-filter-field applications-search-wrap" data-applications-search>
                        <label class="jp-label" for="applications-search">Search</label>
                        <input type="search"
                               id="applications-search"
                               name="search"
                               value="{{ $f['search'] ?? '' }}"
                               class="jp-control jp-control-sm"
                               placeholder="Name, email, company, phone"
                               autocomplete="off"
                               role="combobox"
                               aria-autocomplete="list"
                               aria-expanded="false"
                               aria-controls="applications-search-suggestions"
                               aria-haspopup="listbox">
                        <button type="button"
                                class="applications-search-clear"
                                data-applications-search-clear
                                aria-label="Clear search"
                                @if (trim((string) ($f['search'] ?? '')) === '') hidden @endif>
                            <i class="ti ti-x" aria-hidden="true"></i>
                        </button>
                        <ul id="applications-search-suggestions"
                            class="applications-search-suggestions"
                            role="listbox"
                            aria-label="Application suggestions"
                            data-applications-suggestions
                            hidden></ul>
                    </div>
                    <div class="applications-filter-field">
                        <label class="jp-label" for="applications-status">Status</label>
                        <select id="applications-status" name="status" class="jp-control jp-control-sm">
                            <option value="" @selected(($f['status'] ?? '') === '')>All statuses</option>
                            <option value="pending" @selected(($f['status'] ?? '') === 'pending')>Pending</option>
                            <option value="approved" @selected(($f['status'] ?? '') === 'approved')>Approved</option>
                            <option value="rejected" @selected(($f['status'] ?? '') === 'rejected')>Rejected</option>
                            <option value="needs_more_info" @selected(($f['status'] ?? '') === 'needs_more_info')>Needs info</option>
                        </select>
                    </div>
                    <div class="applications-filter-field">
                        <label class="jp-label" for="applications-submitted-from">Submitted from</label>
                        <input type="date"
                               id="applications-submitted-from"
                               name="submitted_from"
                               value="{{ $f['submitted_from'] ?? '' }}"
                               class="jp-control jp-control-sm">
                    </div>
                    <div class="applications-filter-field">
                        <label class="jp-label" for="applications-submitted-to">Submitted to</label>
                        <input type="date"
                               id="applications-submitted-to"
                               name="submitted_to"
                               value="{{ $f['submitted_to'] ?? '' }}"
                               class="jp-control jp-control-sm">
                    </div>
                    <div class="applications-filter-field">
                        <label class="jp-label" for="applications-city-country">City/Country</label>
                        <input type="text"
                               id="applications-city-country"
                               name="city_country"
                               value="{{ $f['city_country'] ?? '' }}"
                               class="jp-control jp-control-sm"
                               placeholder="City or country">
                    </div>
                    <div class="applications-filter-checkbox">
                        <input type="checkbox"
                               id="applications-duplicate-only"
                               name="duplicate_only"
                               value="1"
                               class="form-check-input"
                               @checked(! empty($f['duplicate_only']))>
                        <label class="form-check-label mb-0" for="applications-duplicate-only">Duplicate only</label>
                    </div>
                    <div class="applications-filter-actions-inline">
                        <button type="submit" class="jp-btn jp-btn--primary btn-sm">Apply</button>
                        <a href="{{ route('admin.agent-applications.index') }}"
                           class="jp-btn jp-btn--ghost btn-sm"
                           data-applications-reset>Reset</a>
                        <a href="{{ route('admin.agent-applications.export', $exportQuery) }}"
                           class="jp-btn jp-btn--ghost btn-sm"
                           data-applications-export-link
                           data-testid="ota-agent-applications-export-csv">
                            Export CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12">
        <div class="jp-card" data-testid="ota-agent-applications-list">
            <div class="jp-card__head">
                <h3 class="jp-card__title mb-0">Application queue</h3>
                <div class="card-actions text-secondary small" data-applications-list-subtitle>
                    Showing <strong data-applications-listed>{{ number_format($applications->count()) }}</strong>
                    of <strong data-applications-total>{{ number_format($applications->total()) }}</strong>
                    application{{ $applications->total() === 1 ? '' : 's' }}@if ($hasFilters)<span data-applications-filters-applied> · filters applied</span>@endif
                </div>
            </div>

            <div class="applications-table-panel">
                <div class="applications-table-loading" data-applications-table-loading hidden role="status" aria-live="polite">
                    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Loading applications...
                </div>
                <div id="agent-applications-table-body" data-agent-applications-table-body>
                    @include('dashboard.admin.partials.agent-applications-table-body', [
                        'applications' => $applications,
                        'duplicateEmailKeys' => $duplicateKeys,
                        'convertedEmailKeys' => $convertedKeys,
                        'duplicateEmailCounts' => $duplicateCounts,
                        'hasFilters' => $hasFilters,
                    ])
                </div>
            </div>
            <div id="agent-applications-pagination" data-agent-applications-pagination>
                @if ($applications->hasPages())
                    <div class="card-footer">
                        {{ $applications->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var pageRoot = document.querySelector('[data-agent-applications-page]');
    var form = document.getElementById('agent-applications-filter-form');
    var tableBody = document.getElementById('agent-applications-table-body');
    var paginationEl = document.getElementById('agent-applications-pagination');
    var tableLoading = document.querySelector('[data-applications-table-loading]');
    var listSubtitle = document.querySelector('[data-applications-list-subtitle]');
    var searchWrap = document.querySelector('[data-applications-search]');
    var searchInput = document.getElementById('applications-search');
    var clearBtn = document.querySelector('[data-applications-search-clear]');
    var suggestionsList = document.getElementById('applications-search-suggestions');
    var exportLinks = document.querySelectorAll('[data-applications-export-link]');
    if (!pageRoot || !form || !tableBody) return;

    var dataUrl = @json(route('admin.agent-applications.data'));
    var suggestionsUrl = @json(route('admin.agent-applications.suggestions'));
    var exportUrl = @json(route('admin.agent-applications.export'));

    var state = {
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

    function currentFilterParams() {
        var fd = new FormData(form);
        var params = new URLSearchParams();
        fd.forEach(function (v, k) {
            if (String(v).trim() === '') return;
            params.append(k, String(v));
        });
        return params;
    }

    function syncFiltersInUrl(params) {
        try {
            var url = new URL(window.location.href);
            ['search', 'status', 'submitted_from', 'submitted_to', 'city_country', 'duplicate_only', 'page'].forEach(function (key) {
                var v = params.get(key);
                if (v && String(v).trim() !== '') {
                    url.searchParams.set(key, v);
                } else {
                    url.searchParams.delete(key);
                }
            });
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}
    }

    function refreshExportLinks(params) {
        var qs = params.toString();
        var href = exportUrl + (qs ? ('?' + qs) : '');
        exportLinks.forEach(function (link) {
            link.setAttribute('href', href);
        });
    }

    function updateSubtitle(listed, total, hasFilters) {
        if (!listSubtitle) return;
        var label = total === 1 ? 'application' : 'applications';
        listSubtitle.innerHTML = 'Showing <strong data-applications-listed>' + esc(listed) + '</strong> of <strong data-applications-total>'
            + esc(total) + '</strong> ' + label
            + (hasFilters ? '<span data-applications-filters-applied> · filters applied</span>' : '');
    }

    function setTableLoading(on) {
        if (!tableLoading) return;
        if (on) {
            tableLoading.removeAttribute('hidden');
        } else {
            tableLoading.setAttribute('hidden', '');
        }
    }

    function fetchApplicationsData(opts) {
        opts = opts || {};
        var params = opts.params || currentFilterParams();

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
                if (!json || typeof json.table_html !== 'string') throw new Error('Invalid payload');
                tableBody.innerHTML = json.table_html;
                if (paginationEl) {
                    paginationEl.innerHTML = json.pagination_html || '';
                }
                updateSubtitle(json.listed_count || 0, json.total_count || 0, !!json.has_filters_applied);
                refreshExportLinks(params);
                syncFiltersInUrl(params);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                if (!opts.silent) form.submit();
            })
            .finally(function () { setTableLoading(false); });
    }

    var debouncedFilterFetch = function () {
        if (state.filterTimer) clearTimeout(state.filterTimer);
        state.filterTimer = setTimeout(function () { fetchApplicationsData(); }, 300);
    };

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
            suggestionsList.innerHTML = '<li class="applications-search-suggestion-empty" role="presentation">No matching applications.</li>';
            showSuggestions();
            return;
        }
        var html = state.suggestionItems.map(function (s, idx) {
            return '<li class="applications-search-suggestion" role="option" id="applications-suggest-' + idx + '"'
                + ' data-search-value="' + esc(s.search_value) + '">'
                + '<span class="applications-search-suggestion-primary">' + esc(s.primary_line) + '</span>'
                + '<span class="applications-search-suggestion-secondary">' + esc(s.secondary_line) + '</span>'
                + '</li>';
        }).join('');
        suggestionsList.innerHTML = html;
        showSuggestions();
    }

    function setActiveSuggestion(index) {
        if (!suggestionsList) return;
        var lis = suggestionsList.querySelectorAll('.applications-search-suggestion');
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
        suggestionsList.innerHTML = '<li class="applications-search-suggestion-empty" role="presentation">'
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
        if (searchInput) searchInput.value = item.search_value || '';
        hideSuggestions();
        updateClearVisibility();
        fetchApplicationsData();
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
        var params = currentFilterParams();
        params.delete('page');
        fetchApplicationsData({ params: params });
    });

    form.querySelectorAll('select, input[type="date"], input[type="checkbox"]').forEach(function (el) {
        el.addEventListener('change', debouncedFilterFetch);
    });

    var cityInput = document.getElementById('applications-city-country');
    if (cityInput) {
        cityInput.addEventListener('input', debouncedFilterFetch);
    }

    var resetLink = form.querySelector('[data-applications-reset]');
    if (resetLink) {
        resetLink.addEventListener('click', function (event) {
            event.preventDefault();
            form.querySelectorAll('select, input').forEach(function (el) {
                if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                } else if (el.type === 'checkbox') {
                    el.checked = false;
                } else {
                    el.value = '';
                }
            });
            updateClearVisibility();
            hideSuggestions();
            fetchApplicationsData();
        });
    }

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
            var li = event.target.closest('.applications-search-suggestion');
            if (!li) return;
            event.preventDefault();
            var idx = Array.prototype.indexOf.call(suggestionsList.children, li);
            if (idx >= 0 && state.suggestionItems[idx]) {
                selectSuggestion(state.suggestionItems[idx]);
            }
        });
    }

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
            hideSuggestions();
            updateClearVisibility();
            fetchApplicationsData();
        });
    }

    if (paginationEl) {
        paginationEl.addEventListener('click', function (event) {
            var link = event.target.closest('a');
            if (!link || !paginationEl.contains(link)) return;
            var href = link.getAttribute('href');
            if (!href) return;
            event.preventDefault();
            try {
                var url = new URL(href, window.location.origin);
                var params = currentFilterParams();
                var page = url.searchParams.get('page');
                if (page) {
                    params.set('page', page);
                } else {
                    params.delete('page');
                }
                fetchApplicationsData({ params: params });
            } catch (e) {
                window.location.href = href;
            }
        });
    }

    updateClearVisibility();
})();
</script>
@endpush

