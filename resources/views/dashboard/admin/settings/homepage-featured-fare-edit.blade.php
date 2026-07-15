@extends(client_layout('dashboard', 'admin'))

@section('title', 'Edit Featured Fare Route')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.settings.homepage.edit') }}#featured-fares">Featured fares</a></div>
            <h1 class="jp-page-title">Edit {{ $fare->origin_code }} → {{ $fare->destination_code }}</h1>
        </div>
    </div>
@endsection

@section('content')
    @if ($errors->any())
        <div class="jp-alert jp-alert--danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="jp-card">
        <div class="jp-card__body">
            <form method="post" action="{{ route('admin.settings.homepage-featured-fares.update', $fare) }}" class="row g-2">
                @csrf
                @method('PATCH')
                <div class="col-md-3">
                    <label class="jp-label">From (IATA)</label>
                    <input class="jp-control text-uppercase" name="origin_code" maxlength="3" value="{{ old('origin_code', $fare->origin_code) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="jp-label">To (IATA)</label>
                    <input class="jp-control text-uppercase" name="destination_code" maxlength="3" value="{{ old('destination_code', $fare->destination_code) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Departure offset</label>
                    <select class="jp-control" name="date_offset_days" required>
                        @foreach ($offsetOptions as $days)
                            <option value="{{ $days }}" @selected((int) old('date_offset_days', $fare->date_offset_days) === $days)>Today + {{ $days }} days</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Cabin</label>
                    <select class="jp-control" name="cabin">
                        <option value="economy" @selected(old('cabin', $fare->cabin) === 'economy')>Economy</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Sort order</label>
                    <input class="jp-control" type="number" name="sort_order" value="{{ old('sort_order', $fare->sort_order) }}" min="0">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <label class="form-check mb-2">
                        <input type="hidden" name="is_enabled" value="0">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $fare->is_enabled))>
                        <span class="form-check-label">Enabled</span>
                    </label>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="jp-btn jp-btn--primary">Save</button>
                    <a href="{{ route('admin.settings.homepage.edit') }}#featured-fares" class="jp-btn jp-btn--ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
