@extends(client_layout('dashboard', 'admin'))

@section('title', 'Markup Rules')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Markup rules</h1>
            <p>Pricing control for routes, airlines, and agents.</p>
        </div>
        <a href="{{ client_route('admin.markups.create') }}" class="jp-btn jp-btn--sm">Create rule</a>
    </div>
@endsection

@section('content')
@include('themes.admin.jetpakistan.partials.flash')

<div class="jp-alert jp-alert--warn">
    Changing markup rules affects newly created bookings only. Existing booking fare snapshots are preserved.
</div>

@php($k = $kpis ?? [])
<div class="jp-kpis jp-kpis--4">
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['active'] ?? 0)) }}</div><div class="jp-kpi__l">Active rules</div></div>
    <div class="jp-kpi t-amber"><div class="jp-kpi__v">{{ number_format((int) ($k['route'] ?? 0)) }}</div><div class="jp-kpi__l">Route rules</div></div>
    <div class="jp-kpi t-blue"><div class="jp-kpi__v">{{ number_format((int) ($k['airline'] ?? 0)) }}</div><div class="jp-kpi__l">Airline rules</div></div>
    <div class="jp-kpi"><div class="jp-kpi__v">{{ number_format((int) ($k['agent'] ?? 0)) }}</div><div class="jp-kpi__l">Agent rules</div></div>
</div>

@php($f = $filters ?? [])
<form method="GET" action="{{ client_route('admin.markups') }}" class="jp-filterbar">
    <div class="jp-filterbar__field">
        <label class="jp-label">Rule type</label>
        <select name="type" class="jp-select">
            <option value="">All</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($f['type'] ?? '') === $type->value)>{{ str_replace('_', ' ', $type->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__field">
        <label class="jp-label">Status</label>
        <select name="status" class="jp-select">
            <option value="">All</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($f['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
            @endforeach
        </select>
    </div>
    <div class="jp-filterbar__actions">
        <button type="submit" class="jp-btn jp-btn--sm">Apply</button>
        <a href="{{ client_route('admin.markups') }}" class="jp-btn jp-btn--sm jp-btn--ghost">Reset</a>
    </div>
</form>

<div class="jp-dtable-wrap">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th class="num">Value</th>
                <th>Applies to</th>
                <th class="num">Priority</th>
                <th>Status</th>
                <th>Active window</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rules as $rule)
                <tr>
                    <td data-label="Name"><strong>{{ $rule->name }}</strong></td>
                    <td data-label="Type">{{ str_replace('_', ' ', $rule->rule_type->value) }}</td>
                    <td data-label="Value" class="num">
                        @if ($rule->value_type->value === 'percentage')
                            {{ number_format((float) $rule->value, 2) }}%
                        @else
                            Rs {{ number_format((float) $rule->value, 0) }}
                        @endif
                    </td>
                    <td data-label="Applies to" class="jp-cell-sub">{{ $rule->applies_to ? json_encode($rule->applies_to) : display_unknown() }}</td>
                    <td data-label="Priority" class="num">{{ $rule->priority }}</td>
                    <td data-label="Status">
                        @if ($rule->status->value === 'draft')
                            <span class="jp-badge-pill jp-badge-pill--amber">Draft</span>
                        @else
                            @can('update', $rule)
                                <form method="POST" action="{{ client_route('admin.markups.toggle-status', $rule) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label style="display: inline-flex; align-items: center; gap: 6px;">
                                        <input type="checkbox" @checked($rule->status->value === 'active') onchange="this.form.submit()">
                                        <span>{{ $rule->status->value === 'active' ? 'Active' : 'Inactive' }}</span>
                                    </label>
                                </form>
                            @else
                                <x-themes.admin.jetpakistan.components.status-badge :label="$rule->status->value" />
                            @endcan
                        @endif
                    </td>
                    <td data-label="Window" class="jp-cell-sub">
                        {{ $rule->starts_at?->format('Y-m-d') ?? display_unknown() }}
                        {{ display_sep_dot() }}
                        {{ $rule->ends_at?->format('Y-m-d') ?? display_unknown() }}
                    </td>
                    <td data-label="Actions">
                        <a href="{{ client_route('admin.markups.edit', $rule) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Edit</a>
                        @can('delete', $rule)
                            <form method="POST" action="{{ client_route('admin.markups.destroy', $rule) }}" style="display: inline;" onsubmit="return confirm('Delete this markup rule?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">Delete</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-themes.admin.jetpakistan.components.empty-state title="No markup rules" message="Default pricing applies until rules are added." /></td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($rules->hasPages())
        <div class="jp-pagination">{{ $rules->links() }}</div>
    @endif
</div>
@endsection
