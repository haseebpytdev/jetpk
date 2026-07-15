@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agent applications')

@php
    $f = $filters ?? [];
    $exportQuery = request()->only(['search', 'status', 'submitted_from', 'submitted_to', 'city_country', 'duplicate_only']);
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

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Agent applications</h1>
            <p>Review partner applications, approve qualified agents, and track onboarding status.</p>
        </div>
        <a href="{{ client_route('admin.agent-applications.export', $exportQuery) }}" class="jp-btn jp-btn--sm jp-btn--outline" data-testid="ota-agent-applications-export-csv-header">Export CSV</a>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-kpis jp-kpis--compact">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($kpis['total'] ?? 0)) }}</div><div class="jp-kpi__l">Total</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($kpis['pending'] ?? 0)) }}</div><div class="jp-kpi__l">Pending</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format((int) ($kpis['approved'] ?? 0)) }}</div><div class="jp-kpi__l">Approved</div></div>
    <div class="jp-kpi t-danger"><div class="jp-kpi__v">{{ number_format((int) ($kpis['rejected'] ?? 0)) }}</div><div class="jp-kpi__l">Rejected</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($kpis['converted'] ?? 0)) }}</div><div class="jp-kpi__l">Converted</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($kpis['duplicates'] ?? 0)) }}</div><div class="jp-kpi__l">Duplicate emails</div></div>
</div>

<form method="GET" action="{{ client_route('admin.agent-applications.index') }}" class="jp-filterbar" id="agent-applications-filter-form">
    <div class="jp-filterbar__field">
        <label class="jp-label" for="applications-search">Search</label>
        <input type="search" id="applications-search" name="search" value="{{ $f['search'] ?? '' }}" class="jp-input" placeholder="Name, email, company, phone">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="applications-status">Status</label>
        <select id="applications-status" name="status" class="jp-select">
            <option value="" @selected(($f['status'] ?? '') === '')>All statuses</option>
            @foreach (['pending', 'approved', 'rejected', 'needs_more_info'] as $st)
                <option value="{{ $st }}" @selected(($f['status'] ?? '') === $st)>{{ $statusLabelFor($st) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="applications-submitted-from">Submitted from</label>
        <input type="date" id="applications-submitted-from" name="submitted_from" value="{{ $f['submitted_from'] ?? '' }}" class="jp-input">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="applications-submitted-to">Submitted to</label>
        <input type="date" id="applications-submitted-to" name="submitted_to" value="{{ $f['submitted_to'] ?? '' }}" class="jp-input">
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label" for="applications-city-country">City/Country</label>
        <input type="text" id="applications-city-country" name="city_country" value="{{ $f['city_country'] ?? '' }}" class="jp-input" placeholder="City or country">
    </div>
    <div class="jp-filterbar__field" style="display: flex; align-items: flex-end;">
        <label style="display: flex; align-items: center; gap: 8px;">
            <input type="checkbox" id="applications-duplicate-only" name="duplicate_only" value="1" @checked(! empty($f['duplicate_only']))>
            <span>Duplicate only</span>
        </label>
    </div>
    <div class="jp-filterbar__actions">
        <button type="submit" class="jp-btn jp-btn--sm">Apply</button>
        <a href="{{ client_route('admin.agent-applications.index') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Reset</a>
        <a href="{{ client_route('admin.agent-applications.export', $exportQuery) }}" class="jp-btn jp-btn--sm jp-btn--outline">Export CSV</a>
    </div>
</form>

<div class="jp-dtable-wrap" data-testid="ota-agent-applications-list">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Applicant</th>
                <th>Company</th>
                <th>Contact</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Flags</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($applications as $application)
                @php
                    $applicantName = trim($application->first_name.' '.$application->last_name);
                    $emailKey = strtolower((string) $application->email);
                    $isDuplicate = in_array($emailKey, $duplicateKeys, true);
                    $isConverted = in_array($emailKey, $convertedKeys, true);
                    $status = (string) $application->status;
                @endphp
                <tr>
                    <td data-label="Applicant">
                        <a href="{{ client_route('admin.agent-applications.show', $application) }}" class="jp-cell-id">{{ $applicantName !== '' ? $applicantName : '—' }}</a>
                    </td>
                    <td data-label="Company">{{ $application->company_name ?: '—' }}</td>
                    <td data-label="Contact">
                        {{ $application->email }}
                        @if (trim((string) $application->mobile) !== '')<div class="jp-cell-sub">{{ $application->mobile }}</div>@endif
                    </td>
                    <td data-label="Status">
                        <span class="jp-badge-pill {{ $status === 'approved' ? 'jp-badge-pill--green' : ($status === 'pending' ? 'jp-badge-pill--amber' : ($status === 'rejected' ? 'jp-badge-pill--danger' : '')) }}">
                            {{ $statusLabelFor($status) }}
                        </span>
                    </td>
                    <td data-label="Submitted">{{ $application->created_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td data-label="Flags">
                        @if ($isConverted)<span class="jp-badge-pill jp-badge-pill--blue">Converted</span>@endif
                        @if ($isDuplicate)<span class="jp-badge-pill jp-badge-pill--amber">Duplicate</span>@endif
                    </td>
                    <td data-label="Action">
                        <a href="{{ client_route('admin.agent-applications.show', $application) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Review</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-themes.admin.jetpakistan.components.empty-state title="No applications" /></td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($applications->hasPages())
        <div class="jp-pagination">{{ $applications->links() }}</div>
    @endif
</div>
@endsection
