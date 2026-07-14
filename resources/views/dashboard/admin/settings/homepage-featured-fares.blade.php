@extends(client_layout('dashboard', 'admin'))

@section('title', 'Homepage Featured Fares')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.settings.index') }}">Settings</a></div>
            <h1 class="jp-page-title">Homepage Featured Fares</h1>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.settings.index') }}" class="jp-btn jp-btn--ghost btn-sm">Settings hub</a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'featured-fare-created')
        <div class="jp-alert jp-alert--success">Featured fare route added.</div>
    @elseif (session('status') === 'featured-fare-updated')
        <div class="jp-alert jp-alert--success">Featured fare route updated.</div>
    @elseif (session('status') === 'featured-fare-deleted')
        <div class="jp-alert jp-alert--success">Featured fare route removed.</div>
    @elseif (session('status') === 'featured-fare-refreshed')
        <div class="jp-alert jp-alert--success">Refresh completed. Check status below.</div>
    @endif
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="jp-alert jp-alert--info small mb-3">
        Routes are refreshed via <code>php artisan homepage:refresh-featured-fares</code> (scheduled daily at 05:00).
        The public homepage shows stored snapshots only — no live API search on page load.
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Add route</h3></div>
        <div class="jp-card__body">
            <form method="post" action="{{ route('admin.settings.homepage-featured-fares.store') }}" class="row g-2">
                @csrf
                <div class="col-md-2">
                    <label class="jp-label">From (IATA)</label>
                    <input class="jp-control text-uppercase" name="origin_code" maxlength="3" value="{{ old('origin_code') }}" placeholder="LHE" required>
                </div>
                <div class="col-md-2">
                    <label class="jp-label">To (IATA)</label>
                    <input class="jp-control text-uppercase" name="destination_code" maxlength="3" value="{{ old('destination_code') }}" placeholder="DXB" required>
                </div>
                <div class="col-md-2">
                    <label class="jp-label">Departure offset</label>
                    <select class="jp-control" name="date_offset_days" required>
                        @foreach ($offsetOptions as $days)
                            <option value="{{ $days }}" @selected((int) old('date_offset_days', 7) === $days)>Today + {{ $days }} days</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="jp-label">Cabin</label>
                    <select class="jp-control" name="cabin">
                        <option value="economy" selected>Economy</option>
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
                <div class="col-12">
                    <button type="submit" class="jp-btn jp-btn--primary btn-sm">Add route</button>
                </div>
            </form>
        </div>
    </div>

    <div class="jp-card">
        <div class="jp-card__head"><h3 class="jp-card__title mb-0">Configured routes</h3></div>
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Route</th>
                        <th>Offset</th>
                        <th>Status</th>
                        <th>Last refresh</th>
                        <th>Snapshot price</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($fares as $fare)
                        @php
                            $snap = is_array($fare->snapshot) ? $fare->snapshot : [];
                            $status = $fare->last_status;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $fare->origin_code }} → {{ $fare->destination_code }}</strong>
                                @if (! $fare->is_enabled)
                                    <span class="badge bg-secondary ms-1">Disabled</span>
                                @endif
                            </td>
                            <td>+{{ $fare->date_offset_days }}d</td>
                            <td>
                                <span class="badge @if($status?->value === 'success') bg-success @elseif($status?->value === 'failed') bg-danger @elseif($status?->value === 'no_results') bg-warning @else bg-secondary @endif">
                                    {{ $status?->label() ?? 'Pending' }}
                                </span>
                                @if ($fare->last_error_message)
                                    <div class="text-danger small mt-1">{{ Str::limit($fare->last_error_message, 120) }}</div>
                                @endif
                            </td>
                            <td>{{ $fare->last_refreshed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td>
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
                        <tr><td colspan="6" class="text-muted">No dynamic featured fares yet. Add a route above or run the refresh command after saving.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
