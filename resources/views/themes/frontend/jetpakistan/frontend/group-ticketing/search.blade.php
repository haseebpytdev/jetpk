@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\GroupInventory> $results */
    /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator */
    $total = $paginator->total();
    $shown = min($paginator->currentPage() * $paginator->perPage(), $total);
    $countLabel = $countLabel ?? (
        $total === 0
            ? 'No group departures found'
            : "Showing {$shown} of {$total} group departure".($total === 1 ? '' : 's')
    );
    $widgetId = 'jp-gt-'.substr(md5('group-search'), 0, 8);
    $minDate = now()->format('Y-m-d');
@endphp
@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Group Ticketing Search')

@push('styles')
@php $jpSearchAssetVersion = 22; @endphp
<link rel="stylesheet" href="{{ rtrim(client_theme()->frontendThemeUrl(), '/') }}/css/jp-search.css?v={{ $jpSearchAssetVersion }}">
@endpush

@section('content')
    <section class="jp-page jp-group-search-page" id="gt-search-root" data-results-url="{{ client_route('group-ticketing.search.results') }}">
        <div class="wrap jp-page-wrap">
            <header class="jp-page-hero">
                @php $groupHero = is_array($groupPageContent['hero'] ?? null) ? $groupPageContent['hero'] : []; @endphp
                <p class="jp-page-hero__kicker">{{ $groupHero['kicker'] ?? '' }}</p>
                <h1 class="jp-page-hero__title">{{ $groupHero['title'] ?? '' }}</h1>
                <p class="jp-page-hero__desc">{{ $groupHero['description'] ?? '' }}</p>
            </header>

            <div
                class="search jp-group-search-card jp-group-search-card--page"
                data-jp-search
                data-jp-search-ready="false"
                data-min-date="{{ $minDate }}"
            >
                @include('themes.frontend.jetpakistan.components.search.groups-panel', [
                    'widgetId' => $widgetId,
                    'minDate' => $minDate,
                    'groupFacets' => $facets,
                    'groupSearchFilters' => $filters,
                    'activeProduct' => 'groups',
                ])
            </div>

            <div class="jp-group-search-layout">
                <aside class="jp-group-search-filters" aria-label="Search filters">
                    <div class="jp-card jp-group-filter-panel">
                        <h2 class="jp-card__title">Filters</h2>
                        <form method="get" action="{{ client_route('group-ticketing.search') }}" class="jp-group-filter-form" id="gt-filters-form" data-gt-filters-form>
                            @if (count($facets['sectors'] ?? []) > 0)
                                <div class="jp-form-group">
                                    <label for="gt-sector" class="jp-label">Sector</label>
                                    <select id="gt-sector" name="sector" class="jp-select jp-select--placeholder gt-filter-input">
                                        <option value="">Any sector</option>
                                        @foreach ($facets['sectors'] as $sector)
                                            <option value="{{ e($sector) }}" @selected(($filters['sector'] ?? '') === $sector)>{{ e($sector) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (count($facets['departure_dates'] ?? []) > 0)
                                <div class="jp-form-group">
                                    <label for="gt-date" class="jp-label">Departure date</label>
                                    <select id="gt-date" name="dept_date" class="jp-select jp-select--placeholder gt-filter-input">
                                        <option value="">Any date</option>
                                        @foreach ($facets['departure_dates'] as $date)
                                            <option value="{{ e($date) }}" @selected(($filters['dept_date'] ?? '') === $date)>{{ e($date) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (count($facets['categories'] ?? []) > 0)
                                <div class="jp-form-group">
                                    <label for="gt-category" class="jp-label">Category</label>
                                    <select id="gt-category" name="category" class="jp-select jp-select--placeholder gt-filter-input">
                                        <option value="">Any category</option>
                                        @foreach ($facets['categories'] as $cat)
                                            <option value="{{ e($cat['slug']) }}" @selected(($filters['category'] ?? '') === $cat['slug'])>{{ e($cat['name']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (count($facets['airlines'] ?? []) > 0)
                                <div class="jp-form-group">
                                    <label for="gt-sidebar-airline" class="jp-label">Airline</label>
                                    <select id="gt-sidebar-airline" name="airline" class="jp-select jp-select--placeholder gt-filter-input">
                                        <option value="">Any airline</option>
                                        @foreach ($facets['airlines'] as $airline)
                                            <option value="{{ e($airline['name']) }}" @selected(($filters['airline'] ?? '') === $airline['name'])>{{ e($airline['name']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="jp-form-group">
                                <label for="gt-min-seats" class="jp-label">Min seats available</label>
                                <input type="number" id="gt-min-seats" name="min_seats" class="jp-input gt-filter-input" min="1" max="50" value="{{ e($filters['min_seats'] ?? '') }}" placeholder="Any">
                            </div>
                            <label class="jp-auth-remember" for="gt-flex">
                                <input type="checkbox" name="flexible" value="1" id="gt-flex" class="gt-filter-input" @checked(filter_var($filters['flexible'] ?? false, FILTER_VALIDATE_BOOL))>
                                <span>Flexible date (same month)</span>
                            </label>
                            <div class="jp-form-group">
                                <label for="gt-sort" class="jp-label">Sort by</label>
                                <select id="gt-sort" name="sort" class="jp-select gt-filter-input">
                                    <option value="departure" @selected(($sort ?? 'departure') === 'departure')>Soonest departure</option>
                                    <option value="price" @selected(($sort ?? '') === 'price')>Lowest price</option>
                                    <option value="seats" @selected(($sort ?? '') === 'seats')>Most seats</option>
                                    <option value="airline" @selected(($sort ?? '') === 'airline')>Airline A–Z</option>
                                </select>
                            </div>
                            <div class="jp-page-actions">
                                <x-jp.button type="submit" variant="primary">Apply</x-jp.button>
                                <a href="{{ client_route('group-ticketing.search') }}" class="jp-btn jp-btn--secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </aside>

                <div class="jp-group-search-results">
                    @php
                        $showStatusAlert = ($statusMessage ?? '') !== '';
                        $hideCountForStatus = $showStatusAlert && $total === 0;
                    @endphp
                    @if ($showStatusAlert)
                        <x-jp.alert variant="warning" role="status" id="gt-empty">{{ $statusMessage }}</x-jp.alert>
                    @endif

                    @if (! $hideCountForStatus)
                        <p class="jp-group-search-count" id="gt-count-label">{{ $countLabel }}</p>
                    @else
                        <p class="jp-group-search-count" id="gt-count-label" hidden>{{ $countLabel }}</p>
                    @endif

                    @php
                        $freshness = is_array($inventoryFreshness ?? null) ? $inventoryFreshness : [];
                        $publicRefreshNotice = trim((string) ($freshness['user_notice'] ?? ''));
                        $showAdminFreshness = auth()->check()
                            && (auth()->user()->isPlatformAdmin() || auth()->user()->isStaff())
                            && ! empty($freshness);
                    @endphp
                    @if ($publicRefreshNotice !== '' && $publicRefreshNotice !== ($statusMessage ?? ''))
                        <p class="jp-field-hint" id="gt-refresh-notice" role="status">{{ e($publicRefreshNotice) }}</p>
                    @elseif ($publicRefreshNotice === '' && ! $showStatusAlert)
                        <p class="jp-field-hint" id="gt-refresh-notice" role="status" hidden></p>
                    @else
                        <p class="jp-field-hint" id="gt-refresh-notice" role="status" hidden></p>
                    @endif
                    @if ($showAdminFreshness)
                        <p class="jp-field-hint" role="status">
                            @if (($freshness['minutes_ago'] ?? null) !== null)
                                Inventory last updated {{ (int) $freshness['minutes_ago'] }} min ago
                            @else
                                Inventory sync time unknown
                            @endif
                            @if (! empty($freshness['skip_reason']) && ($freshness['skipped'] ?? false))
                                (sync skipped: {{ e((string) $freshness['skip_reason']) }})
                            @elseif ($freshness['synced'] ?? false)
                                (refreshed on this search)
                            @endif
                        </p>
                    @endif

                    <div class="jp-group-results-list" id="gt-results-list" data-testid="group-results-list">
                        @include('frontend.group-ticketing.partials.result-rows', ['results' => $results, 'cards' => $cards])
                    </div>

                    @if ($paginator->hasMorePages())
                        <div class="ota-group-results-load-more" id="gt-load-more-wrap">
                            <button type="button" class="jp-btn jp-btn--secondary" id="gt-load-more" data-testid="group-load-more">Load more</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

@push('theme-scripts')
@php $jpSearchAssetVersion = 22; @endphp
@php $jpThemeBase = rtrim(client_theme()->frontendThemeUrl(), '/'); @endphp
<script src="{{ $jpThemeBase }}/js/jp-dates.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script src="{{ $jpThemeBase }}/js/forms.js?v={{ $jpSearchAssetVersion }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var root = document.querySelector('.jp-group-search-card[data-jp-search]');
  if (!root) return;
  if (window.JpForms) window.JpForms.init(root);
  if (window.JpDates) window.JpDates.init(root);
}, { once: true });
</script>
@endpush

@push('scripts')
<script>
(function () {
    var root = document.getElementById('gt-search-root');
    if (!root) return;

    var resultsUrl = root.getAttribute('data-results-url');
    var list = document.getElementById('gt-results-list');
    var countLabel = document.getElementById('gt-count-label');
    var refreshNotice = document.getElementById('gt-refresh-notice');
    var loadMoreBtn = document.getElementById('gt-load-more');
    var loadMoreWrap = document.getElementById('gt-load-more-wrap');
    var filtersForm = document.getElementById('gt-filters-form');
    var heroForm = root.querySelector('[data-jp-group-form], .ota-hero-group-search-form');
    var page = {{ (int) $paginator->currentPage() }};
    var hasMore = {{ $paginator->hasMorePages() ? 'true' : 'false' }};
    var loading = false;
    var debounceTimer = null;

    function collectParams(targetPage) {
        var params = new URLSearchParams(window.location.search);
        params.set('page', String(targetPage || 1));
        if (filtersForm) {
            new FormData(filtersForm).forEach(function (value, key) {
                if (value !== '' && value !== null) {
                    params.set(key, value);
                } else {
                    params.delete(key);
                }
            });
        }
        if (heroForm) {
            new FormData(heroForm).forEach(function (value, key) {
                if (value !== '' && value !== null) {
                    params.set(key, value);
                }
            });
        }
        return params;
    }

    function syncUrl(params) {
        var qs = params.toString();
        var next = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState({}, '', next);
    }

    function fetchResults(reset) {
        if (loading) return;
        loading = true;
        if (loadMoreBtn) loadMoreBtn.disabled = true;
        list.classList.add('is-loading');

        var targetPage = reset ? 1 : page + 1;
        var params = collectParams(targetPage);
        syncUrl(params);

        fetch(resultsUrl + '?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (reset) {
                    list.innerHTML = json.html || '';
                    page = 1;
                } else {
                    list.insertAdjacentHTML('beforeend', json.html || '');
                    page = json.page || targetPage;
                }
                hasMore = !!json.has_more;
                if (countLabel) countLabel.textContent = json.count_label || '';
                if (refreshNotice) {
                    if (json.user_notice) {
                        refreshNotice.textContent = json.user_notice;
                        refreshNotice.hidden = false;
                    } else if (reset) {
                        refreshNotice.textContent = '';
                        refreshNotice.hidden = true;
                    }
                }
                if (loadMoreWrap) loadMoreWrap.style.display = (hasMore && json.bookable !== false) ? '' : 'none';
                var empty = document.getElementById('gt-empty');
                if (empty) empty.style.display = (json.total === 0) ? '' : 'none';
            })
            .catch(function () {})
            .finally(function () {
                loading = false;
                if (loadMoreBtn) loadMoreBtn.disabled = false;
                list.classList.remove('is-loading');
            });
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            if (hasMore) fetchResults(false);
        });
    }

    if (filtersForm) {
        filtersForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fetchResults(true);
        });
        filtersForm.querySelectorAll('.gt-filter-input').forEach(function (el) {
            el.addEventListener('change', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () { fetchResults(true); }, 350);
            });
        });
    }
})();
</script>
@endpush
