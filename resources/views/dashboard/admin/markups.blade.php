@extends(client_layout('dashboard', 'admin'))

@section('title', 'Markups')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Commercial rules</div>
            <h1 class="jp-page-title">Markups &amp; commissions</h1>
            <div class="text-secondary mt-1">
                Reference view from <code>config/ota-markups.php</code>. Database save actions are not enabled on this screen.
            </div>
        </div>
    </div>
@endsection

@section('content')
    @php
        $g = $globalMarkup ?? [];
        $routes = $routeMarkups ?? [];
        $airlines = $airlineMarkups ?? [];
        $agents = $agentCommissions ?? [];
    @endphp

    <div class="alert alert-secondary mb-4" role="alert">
        <i class="ti ti-info-circle me-2"></i>
        <strong>Note.</strong> {{ $demoNote ?? '' }}
    </div>

    {{-- Dynamic: rule builder, versioning, A/B tests, tenant overrides --}}

    {{-- 1. Global markup card --}}
    <div class="jp-card">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Global markup</h3>
        </div>
        <div class="jp-card__body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-secondary small">Name</div>
                    <div class="fw-semibold">{{ $g['name'] ?? '—' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Rule type</div>
                    <div><span class="badge bg-secondary">{{ $g['rule_type'] ?? '—' }}</span></div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Status</div>
                    <div>
                        @php $gs = $g['status'] ?? 'inactive'; @endphp
                        <span class="badge {{ $gs === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($gs) }}</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-secondary small">Value</div>
                    <div class="h3 mb-0">
                        @if (($g['value_type'] ?? '') === 'percentage')
                            {{ number_format((float) ($g['value'] ?? 0), 2) }}%
                        @else
                            Rs {{ number_format((float) ($g['value'] ?? 0), 0) }}
                        @endif
                        <span class="text-secondary small fw-normal">({{ $g['value_type'] ?? '—' }})</span>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="text-secondary small">Applies to</div>
                    <div>{{ $g['applies_to'] ?? '—' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Route markup table --}}
    <div class="jp-card">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Route markups</h3>
        </div>
        <div class="table-responsive">
            <table class="jp-jp-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Rule type</th>
                        <th>Value</th>
                        <th>Type</th>
                        <th>Applies to</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($routes as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $row['rule_type'] ?? '—' }}</span></td>
                            <td>
                                @if (($row['value_type'] ?? '') === 'percentage')
                                    {{ number_format((float) ($row['value'] ?? 0), 2) }}%
                                @else
                                    Rs {{ number_format((float) ($row['value'] ?? 0), 0) }}
                                @endif
                            </td>
                            <td>{{ $row['value_type'] ?? '—' }}</td>
                            <td style="max-width: 280px;">{{ $row['applies_to'] ?? '—' }}</td>
                            <td>
                                @php $s = $row['status'] ?? 'inactive'; @endphp
                                <span class="badge {{ $s === 'active' ? 'bg-success' : ($s === 'draft' ? 'bg-warning' : 'bg-secondary') }}">{{ ucfirst($s) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No route rules.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 3. Airline markup table --}}
    <div class="jp-card">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Airline markups</h3>
        </div>
        <div class="table-responsive">
            <table class="jp-jp-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Rule type</th>
                        <th>Value</th>
                        <th>Type</th>
                        <th>Applies to</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($airlines as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $row['rule_type'] ?? '—' }}</span></td>
                            <td>
                                @if (($row['value_type'] ?? '') === 'percentage')
                                    {{ number_format((float) ($row['value'] ?? 0), 2) }}%
                                @else
                                    Rs {{ number_format((float) ($row['value'] ?? 0), 0) }}
                                @endif
                            </td>
                            <td>{{ $row['value_type'] ?? '—' }}</td>
                            <td style="max-width: 280px;">{{ $row['applies_to'] ?? '—' }}</td>
                            <td>
                                @php $s = $row['status'] ?? 'inactive'; @endphp
                                <span class="badge {{ $s === 'active' ? 'bg-success' : ($s === 'draft' ? 'bg-warning' : 'bg-secondary') }}">{{ ucfirst($s) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No airline rules.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 4. Agent commission table --}}
    <div class="jp-card">
        <div class="jp-card__head">
            <h3 class="jp-card__title">Agent commissions</h3>
        </div>
        <div class="table-responsive">
            <table class="jp-jp-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Rule type</th>
                        <th>Value</th>
                        <th>Type</th>
                        <th>Applies to</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agents as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['name'] ?? '—' }}</td>
                            <td><span class="badge bg-secondary">{{ $row['rule_type'] ?? '—' }}</span></td>
                            <td>
                                @if (($row['value_type'] ?? '') === 'percentage')
                                    {{ number_format((float) ($row['value'] ?? 0), 2) }}%
                                @else
                                    Rs {{ number_format((float) ($row['value'] ?? 0), 0) }}
                                @endif
                            </td>
                            <td>{{ $row['value_type'] ?? '—' }}</td>
                            <td style="max-width: 280px;">{{ $row['applies_to'] ?? '—' }}</td>
                            <td>
                                @php $s = $row['status'] ?? 'inactive'; @endphp
                                <span class="badge {{ $s === 'active' ? 'bg-success' : ($s === 'draft' ? 'bg-warning' : 'bg-secondary') }}">{{ ucfirst($s) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-secondary">No commission rules.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center text-secondary small">
        <button type="button" class="jp-btn jp-btn--primary btn-planned-action" disabled>Save changes @include('components.planned-hint')</button>
        <span class="d-block mt-2">Forms and persistence are not enabled on this screen.</span>
    </div>
@endsection

