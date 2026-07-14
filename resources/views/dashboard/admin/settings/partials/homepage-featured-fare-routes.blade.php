@php
    $payload = $featuredFaresSection ?? ['enabled' => true, 'title' => '', 'subtitle' => '', 'items' => []];
    $sectionModel = $featuredFaresSectionModel ?? null;
    $fares = $featuredFares ?? collect();
@endphp

<div class="jp-alert jp-alert--info small mb-3">
    Configure <strong>route rules</strong> only (from, to, date offset). Cheapest fares are fetched by
    <code>php artisan homepage:refresh-featured-fares</code> (scheduled daily at 05:00) and shown on the homepage from stored snapshots.
</div>

<div class="jp-card" id="featured-fares">
    <form method="post" action="{{ route('admin.settings.homepage.update', 'feature_cards') }}">
        @csrf
        @method('PATCH')
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="jp-card__title mb-0">Featured fares</h3>
            <label class="form-check m-0">
                <input type="hidden" name="is_enabled" value="0">
                <input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $payload['enabled'] ?? true))>
                <span class="form-check-label">Section enabled</span>
            </label>
        </div>
        <div class="card-body border-bottom">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="jp-label">Section title</label>
                    <input class="jp-control" name="title" value="{{ old('title', $payload['title'] ?? '') }}">
                </div>
                <div class="col-md-6">
                    <label class="jp-label">Sort order</label>
                    <input class="jp-control" type="number" name="sort_order" value="{{ old('sort_order', $sectionModel?->sort_order ?? 100) }}">
                </div>
                <div class="col-12">
                    <label class="jp-label">Section subtitle</label>
                    <textarea class="jp-control" rows="2" name="subtitle">{{ old('subtitle', $payload['subtitle'] ?? '') }}</textarea>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="jp-btn jp-btn--outline btn-sm">Save section heading</button>
                </div>
            </div>
        </div>
    </form>

    <div class="jp-card__body">
        <h4 class="h5 mb-2">Route rules</h4>
        <form method="post" action="{{ route('admin.settings.homepage-featured-fares.store') }}" class="row g-2 border rounded p-3 mb-3">
            @csrf
            <div class="col-md-2">
                <label class="jp-label">From (IATA)</label>
                <input class="jp-control text-uppercase" name="origin_code" maxlength="3" value="{{ old('origin_code') }}" placeholder="LHE" required>
            </div>
            <div class="col-md-2">
                <label class="jp-label">To (IATA)</label>
                <input class="jp-control text-uppercase" name="destination_code" maxlength="3" value="{{ old('destination_code') }}" placeholder="DXB" required>
            </div>
            <div class="col-md-3">
                <label class="jp-label">Date offset</label>
                <select class="jp-control" name="date_offset_days" required>
                    @foreach ($offsetOptions as $days)
                        <option value="{{ $days }}" @selected((int) old('date_offset_days', 7) === $days)>Today + {{ $days }} days</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="jp-label">Sort order</label>
                <input class="jp-control" type="number" name="sort_order" value="{{ old('sort_order', 100) }}" min="0">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <label class="form-check mb-2">
                    <input type="hidden" name="is_enabled" value="0">
                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', true))>
                    <span class="form-check-label">Enabled</span>
                </label>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="jp-btn jp-btn--primary btn-sm w-100">Add</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="jp-table table-sm">
                <thead>
                    <tr>
                        <th>Sort</th>
                        <th>Route</th>
                        <th>Offset</th>
                        <th>Status</th>
                        <th>Last refresh</th>
                        <th>Snapshot</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fares as $fare)
                        @php
                            $snap = is_array($fare->snapshot) ? $fare->snapshot : [];
                            $status = $fare->last_status;
                        @endphp
                        <tr>
                            <td>{{ $fare->sort_order }}</td>
                            <td>
                                <strong>{{ $fare->origin_code }} → {{ $fare->destination_code }}</strong>
                                @if (! $fare->is_enabled)
                                    <span class="badge bg-secondary">Off</span>
                                @endif
                            </td>
                            <td>+{{ $fare->date_offset_days }}d</td>
                            <td>
                                <span class="badge @if($status?->value === 'success') bg-success @elseif($status?->value === 'failed') bg-danger @elseif($status?->value === 'no_results') bg-warning @else bg-secondary @endif">
                                    {{ $status?->label() ?? 'Pending' }}
                                </span>
                                @if ($fare->last_error_message)
                                    <div class="text-danger small">{{ Str::limit($fare->last_error_message, 80) }}</div>
                                @endif
                            </td>
                            <td class="small">{{ $fare->last_refreshed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="small">
                                @if (($snap['airline_name'] ?? '') !== '')
                                    {{ $snap['airline_name'] }}@if(!empty($snap['airline_code'])) ({{ $snap['airline_code'] }})@endif<br>
                                @endif
                                @if (! empty($snap['departure_date']))
                                    {{ $snap['departure_date'] }}<br>
                                @endif
                                @if (($snap['price_total'] ?? 0) > 0)
                                    {{ $snap['currency'] ?? 'PKR' }} {{ number_format((float) $snap['price_total']) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-nowrap">
                                <a href="{{ route('admin.settings.homepage-featured-fares.edit', $fare) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Edit</a>
                                <form method="post" action="{{ route('admin.settings.homepage-featured-fares.refresh', $fare) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Refresh</button>
                                </form>
                                <form method="post" action="{{ route('admin.settings.homepage-featured-fares.destroy', $fare) }}" class="d-inline" onsubmit="return confirm('Remove this route?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted">No routes yet. Add a route above, then run <code>php artisan homepage:refresh-featured-fares</code>.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
