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
@endphp
@extends(client_layout('frontend', 'frontend'))

@section('title', 'Group Ticketing Search')

@section('content')
    <section class="ota-section ota-group-ticketing-page" id="gt-search-root" data-results-url="{{ client_route('group-ticketing.search.results') }}">
        <div class="ota-container">
            <div class="ota-hero-search ota-hero-search--results ota-group-search-inline">
                <div class="ota-hero-search-card">
                    @include('frontend.partials.ota-hero-group-search', [
                        'widgetId' => 'gt-results',
                        'groupFacets' => $facets,
                        'groupSearchFilters' => $filters,
                        'minDate' => now()->format('Y-m-d'),
                    ])
                </div>
            </div>

            <div class="ota-umrah-groups-layout ota-umrah-groups-layout--rows">
                <aside class="ota-umrah-groups-filters" aria-label="Search filters">
                    <div class="ota-umrah-groups-panel">
                        <h2 class="ota-umrah-groups-panel__title">Filters</h2>
                        <form method="get" action="{{ client_route('group-ticketing.search') }}" class="ota-umrah-groups-form" id="gt-filters-form" data-gt-filters-form>
                            @if (count($facets['sectors'] ?? []) > 0)
                                <div class="form-group">
                                    <label for="gt-sector" class="control-label">Sector</label>
                                    <select id="gt-sector" name="sector" class="form-control gt-filter-input">
                                        <option value="">Any sector</option>
                                        @foreach ($facets['sectors'] as $sector)
                                            <option value="{{ e($sector) }}" @selected(($filters['sector'] ?? '') === $sector)>{{ e($sector) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (count($facets['departure_dates'] ?? []) > 0)
                                <div class="form-group">
                                    <label for="gt-date" class="control-label">Departure date</label>
                                    <select id="gt-date" name="dept_date" class="form-control gt-filter-input">
                                        <option value="">Any date</option>
                                        @foreach ($facets['departure_dates'] as $date)
                                            <option value="{{ e($date) }}" @selected(($filters['dept_date'] ?? '') === $date)>{{ e($date) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (count($facets['categories'] ?? []) > 0)
                                <div class="form-group">
                                    <label for="gt-category" class="control-label">Category</label>
                                    <select id="gt-category" name="category" class="form-control gt-filter-input">
                                        <option value="">Any category</option>
                                        @foreach ($facets['categories'] as $cat)
                                            <option value="{{ e($cat['slug']) }}" @selected(($filters['category'] ?? '') === $cat['slug'])>{{ e($cat['name']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if (count($facets['airlines'] ?? []) > 0)
                                <div class="form-group">
                                    <label for="gt-sidebar-airline" class="control-label">Airline</label>
                                    <select id="gt-sidebar-airline" name="airline" class="form-control gt-filter-input">
                                        <option value="">Any airline</option>
                                        @foreach ($facets['airlines'] as $airline)
                                            <option value="{{ e($airline['name']) }}" @selected(($filters['airline'] ?? '') === $airline['name'])>{{ e($airline['name']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="form-group">
                                <label for="gt-min-seats" class="control-label">Min seats available</label>
                                <input type="number" id="gt-min-seats" name="min_seats" class="form-control gt-filter-input" min="1" max="50" value="{{ e($filters['min_seats'] ?? '') }}" placeholder="Any">
                            </div>
                            <div class="form-check mb-2">
                                <input type="checkbox" name="flexible" value="1" id="gt-flex" class="form-check-input gt-filter-input" @checked(filter_var($filters['flexible'] ?? false, FILTER_VALIDATE_BOOL))>
                                <label class="form-check-label" for="gt-flex">Flexible date (same month)</label>
                            </div>
                            <div class="form-group">
                                <label for="gt-sort" class="control-label">Sort by</label>
                                <select id="gt-sort" name="sort" class="form-control gt-filter-input">
                                    <option value="departure" @selected(($sort ?? 'departure') === 'departure')>Soonest departure</option>
                                    <option value="price" @selected(($sort ?? '') === 'price')>Lowest price</option>
                                    <option value="seats" @selected(($sort ?? '') === 'seats')>Most seats</option>
                                    <option value="airline" @selected(($sort ?? '') === 'airline')>Airline A–Z</option>
                                </select>
                            </div>
                            <div class="ota-umrah-groups-form__actions">
                                <button type="submit" class="ota-btn ota-btn-primary">Apply</button>
                                <a href="{{ client_route('group-ticketing.search') }}" class="ota-btn ota-btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </aside>

                <div class="ota-umrah-groups-results">
                    @if ($statusMessage)
                        <div class="ota-umrah-groups-empty" role="status" id="gt-empty"><p>{{ e($statusMessage) }}</p></div>
                    @endif

                    <p class="ota-umrah-groups-count" id="gt-count-label">{{ $countLabel }}</p>

                    @php
                        $freshness = is_array($inventoryFreshness ?? null) ? $inventoryFreshness : [];
                        $publicRefreshNotice = trim((string) ($freshness['user_notice'] ?? ''));
                        $showAdminFreshness = auth()->check()
                            && (auth()->user()->isPlatformAdmin() || auth()->user()->isStaff())
                            && ! empty($freshness);
                    @endphp
                    @if ($publicRefreshNotice !== '')
                        <p class="ota-group-inventory-refresh-notice text-muted small mb-2" id="gt-refresh-notice" role="status">{{ e($publicRefreshNotice) }}</p>
                    @else
                        <p class="ota-group-inventory-refresh-notice text-muted small mb-2" id="gt-refresh-notice" role="status" hidden></p>
                    @endif
                    @if ($showAdminFreshness)
                        <p class="ota-group-inventory-freshness-note text-muted small mb-2" role="status">
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

                    <div class="ota-group-results-list ota-group-results-list--rows" id="gt-results-list" data-testid="group-results-list">
                        @include('frontend.group-ticketing.partials.result-rows', ['results' => $results, 'cards' => $cards])
                    </div>

                    @if ($paginator->hasMorePages())
                        <div class="ota-group-results-load-more" id="gt-load-more-wrap">
                            <button type="button" class="ota-btn ota-btn-secondary" id="gt-load-more" data-testid="group-load-more">Load more</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection

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
    var heroForm = root.querySelector('.ota-hero-group-search-form');
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
