@extends(client_layout('frontend', 'frontend'))

@php
    $brand = config('ota-brand', []);
    $client = config('ota-client', []);
    $brandName = $client['agency_name'] ?? ($brand['product_name'] ?? config('app.name'));
@endphp

@section('title', 'Group Ticketing — '.$brandName)

@section('content')
    <section class="ota-section ota-umrah-groups-page" aria-labelledby="ota-umrah-groups-heading">
        <div class="ota-container">
            <header class="ota-section-head ota-umrah-groups-hero">
                <p class="ota-section-kicker">Group ticketing</p>
                <h1 id="ota-umrah-groups-heading" class="ota-section-title">Group Ticketing</h1>
                <p class="ota-section-desc">Browse group departure packages with fixed dates, routes, and transparent pricing. Contact us to enquire — online booking is not available yet.</p>
            </header>

            @if (! empty($warnings))
                @foreach ($warnings as $warning)
                    <div class="ota-alert ota-alert-warning ota-umrah-groups-notice" role="status">{{ e($warning) }}</div>
                @endforeach
            @endif

            <div class="ota-umrah-groups-layout">
                <aside class="ota-umrah-groups-filters" aria-label="Search filters">
                    <div class="ota-umrah-groups-panel">
                        <h2 class="ota-umrah-groups-panel__title">Search packages</h2>
                        <form method="get" action="{{ client_route('umrah-groups.index') }}" class="ota-umrah-groups-form">
                            <div class="form-group">
                                <label for="umrah-sector" class="control-label">Sector / route</label>
                                <input type="text" id="umrah-sector" name="sector" class="form-control" value="{{ e($filters['sector'] ?? '') }}" placeholder="e.g. LHE-JED" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label for="umrah-dept-date" class="control-label">Departure date</label>
                                <input type="date" id="umrah-dept-date" name="dept_date" class="form-control" value="{{ e($filters['dept_date'] ?? '') }}">
                            </div>
                            <div class="form-group">
                                <label for="umrah-type" class="control-label">Package type</label>
                                <input type="text" id="umrah-type" name="type" class="form-control" value="{{ e($filters['type'] ?? '') }}" placeholder="e.g. Umrah" maxlength="50">
                            </div>
                            @if (count($airlines) > 0)
                                <div class="form-group">
                                    <label for="umrah-airline" class="control-label">Airline</label>
                                    <select id="umrah-airline" name="airline_id" class="form-control">
                                        <option value="">Any airline</option>
                                        @foreach ($airlines as $airline)
                                            <option value="{{ $airline['id'] }}" @selected(($filters['airline_id'] ?? '') == (string) $airline['id'])>{{ e($airline['name']) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="ota-umrah-groups-form__actions">
                                <button type="submit" class="ota-btn ota-btn-primary">Search</button>
                                <a href="{{ client_route('umrah-groups.index') }}" class="ota-btn ota-btn-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </aside>

                <div class="ota-umrah-groups-results">
                    @if ($statusMessage)
                        <div class="ota-umrah-groups-empty" role="status">
                            <p>{{ e($statusMessage) }}</p>
                            @if ($apiState === 'disabled')
                                <p class="ota-umrah-groups-empty__hint">Package search will appear here once the supplier connection is enabled.</p>
                            @endif
                        </div>
                    @elseif (count($packages) === 0)
                        <div class="ota-umrah-groups-empty" role="status">
                            <p>No group tickets matched your search.</p>
                        </div>
                    @else
                        <p class="ota-umrah-groups-count">{{ count($packages) }} package{{ count($packages) === 1 ? '' : 's' }} found</p>
                        <div class="ota-umrah-groups-grid" data-testid="umrah-groups-grid">
                            @foreach ($packages as $package)
                                @include('frontend.umrah-groups.partials.package-card', ['package' => $package])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endsection
