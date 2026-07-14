@extends(client_layout('dashboard', 'admin'))

@section('title', 'Markup Rules')

@push('styles')
<style>
    .markups-filter-card .jp-control,
    .markups-filter-card .jp-control {
        max-width: 100%;
        min-width: 0;
    }
    .markups-rules-table .btn-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 0.35rem;
        max-width: 100%;
    }
    .markups-rules-table .btn-list .btn {
        max-width: 100%;
        white-space: normal;
    }
    .markups-rules-table .form-check {
        max-width: 100%;
        min-width: 0;
    }
    .markups-rules-table .form-check-input {
        flex-shrink: 0;
    }
    .markups-rules-table .form-check-label {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    @media (max-width: 991.98px) {
        .markups-rules-table .ota-admin-table th:nth-child(4),
        .markups-rules-table .ota-admin-table td:nth-child(4),
        .markups-rules-table .ota-admin-table th:nth-child(7),
        .markups-rules-table .ota-admin-table td:nth-child(7) {
            display: none;
        }
    }
    @media (max-width: 767.98px) {
        .markups-rules-table .ota-admin-table th:nth-child(6),
        .markups-rules-table .ota-admin-table td:nth-child(6),
        .markups-rules-table .ota-admin-table th:nth-child(3),
        .markups-rules-table .ota-admin-table td:nth-child(3) {
            display: none;
        }
        .markups-rules-table .form-check-label {
            font-size: 0.72rem;
        }
        .markups-rules-table .table-responsive {
            overflow-x: visible;
        }
        .markups-rules-table .ota-admin-table {
            width: 100%;
            table-layout: fixed;
        }
        .markups-rules-table .ota-admin-table thead {
            display: none;
        }
        .markups-rules-table .ota-admin-table tbody,
        .markups-rules-table .ota-admin-table tr,
        .markups-rules-table .ota-admin-table td {
            display: block;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .markups-rules-table .ota-admin-table tbody tr {
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 10px;
            margin: 0 0 0.65rem;
            padding: 0.65rem 0.75rem;
            background: #fff;
        }
        .markups-rules-table .ota-admin-table td.text-end {
            text-align: left;
            padding-top: 0.5rem;
        }
        .markups-rules-table .btn-list {
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
        }
        .markups-rules-table .btn-list .btn,
        .markups-rules-table .btn-list form {
            display: block;
            width: 100%;
            max-width: 100%;
        }
        .markups-rules-table .btn-list form .btn {
            width: 100%;
        }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Pricing Control</div>
            <h1 class="jp-page-title">Markup rules</h1>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('admin.markups.create') }}" class="jp-btn jp-btn--primary btn-sm">
                <i class="ti ti-plus me-1"></i>Create rule
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'markup-rule-deleted')
        <div class="jp-alert jp-alert--success alert-dismissible mb-3" role="alert">
            Markup rule deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @elseif (session('status') === 'markup-rule-created')
        <div class="jp-alert jp-alert--success alert-dismissible mb-3" role="alert">
            Markup rule created.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @elseif (session('status') === 'markup-rule-updated')
        <div class="jp-alert jp-alert--success alert-dismissible mb-3" role="alert">
            Markup rule updated.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @elseif (session('status') === 'markup-rule-status-updated')
        <div class="jp-alert jp-alert--success alert-dismissible mb-3" role="alert">
            Rule status updated.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="jp-alert jp-alert--warn mb-3">
        Changing markup rules affects newly created bookings only. Existing booking fare snapshots are preserved.
    </div>

    @php($k = $kpis ?? [])
    <div class="row row-cards g-3 mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm ota-kpi-card h-100">
                <div class="card-body py-3">
                    <div class="text-secondary text-uppercase small mb-1">Active rules</div>
                    <div class="h2 mb-0">{{ number_format((int) ($k['active'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm ota-kpi-card ota-kpi-accent-amber h-100">
                <div class="card-body py-3">
                    <div class="text-secondary text-uppercase small mb-1">Route rules</div>
                    <div class="h2 mb-0">{{ number_format((int) ($k['route'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm ota-kpi-card ota-kpi-accent-emerald h-100">
                <div class="card-body py-3">
                    <div class="text-secondary text-uppercase small mb-1">Airline rules</div>
                    <div class="h2 mb-0">{{ number_format((int) ($k['airline'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm ota-kpi-card ota-kpi-accent-violet h-100">
                <div class="card-body py-3">
                    <div class="text-secondary text-uppercase small mb-1">Agent rules</div>
                    <div class="h2 mb-0">{{ number_format((int) ($k['agent'] ?? 0)) }}</div>
                </div>
            </div>
        </div>
    </div>

    @php($f = $filters ?? [])
    <form method="GET" action="{{ route('admin.markups') }}" class="card mb-3 markups-filter-card">
        <div class="card-header py-2">
            <h3 class="jp-card__title mb-0">Filters</h3>
        </div>
        <div class="card-body py-3">
            <div class="jp-form-grid jp-form-grid--filter ota-r-form-grid">
                <div class="col-12 col-md-4">
                    <label class="jp-label mb-1">Rule type</label>
                    <select name="type" class="jp-control jp-control-sm">
                        <option value="">All</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(($f['type'] ?? '') === $type->value)>{{ str_replace('_', ' ', $type->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="jp-label mb-1">Status</label>
                    <select name="status" class="jp-control jp-control-sm">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($f['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <div class="d-flex gap-2 flex-wrap ota-r-action-bar">
                        <button type="submit" class="jp-btn jp-btn--outline btn-sm flex-fill">Apply</button>
                        <a href="{{ route('admin.markups') }}" class="jp-btn jp-btn--ghost btn-sm">Reset</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card markups-rules-table">
        <div class="card-header py-2 d-none d-md-block">
            <h3 class="jp-card__title mb-0">Rules</h3>
        </div>
        <div class="table-responsive">
            <table class="jp-table table-sm card-table ota-admin-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Value</th>
                        <th>Applies to</th>
                        <th class="text-center">Priority</th>
                        <th>Status</th>
                        <th>Active window</th>
                        <th class="text-end w-1">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $rule->name }}</td>
                            <td class="text-capitalize text-nowrap">{{ str_replace('_', ' ', $rule->rule_type->value) }}</td>
                            <td class="text-end text-nowrap">
                                @if ($rule->value_type->value === 'percentage')
                                    {{ number_format((float) $rule->value, 2) }}%
                                @else
                                    Rs {{ number_format((float) $rule->value, 0) }}
                                @endif
                            </td>
                            <td class="small text-secondary text-truncate" style="max-width: 12rem;" title="{{ $rule->applies_to ? json_encode($rule->applies_to) : '--' }}">
                                {{ $rule->applies_to ? json_encode($rule->applies_to) : display_unknown() }}
                            </td>
                            <td class="text-center">{{ $rule->priority }}</td>
                            <td class="text-nowrap">
                                @if ($rule->status->value === 'draft')
                                    <span class="badge bg-warning-lt">Draft</span>
                                @else
                                    @can('update', $rule)
                                        <form method="POST" action="{{ route('admin.markups.toggle-status', $rule) }}" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <div class="form-check form-switch m-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="markup-status-{{ $rule->id }}"
                                                    @checked($rule->status->value === 'active')
                                                    onchange="this.form.submit()"
                                                    aria-label="Toggle active status for {{ $rule->name }}"
                                                >
                                                <label class="form-check-label" for="markup-status-{{ $rule->id }}">
                                                    {{ $rule->status->value === 'active' ? 'Active' : 'Inactive' }}
                                                </label>
                                            </div>
                                        </form>
                                    @else
                                        <span class="badge bg-{{ $rule->status->value === 'active' ? 'success' : 'secondary' }}-lt">
                                            {{ ucfirst($rule->status->value) }}
                                        </span>
                                    @endcan
                                @endif
                            </td>
                            <td class="small text-nowrap text-secondary">
                                {{ $rule->starts_at?->format('Y-m-d') ?? display_unknown() }}
                                <span class="text-muted">{{ display_sep_dot() }}</span>
                                {{ $rule->ends_at?->format('Y-m-d') ?? display_unknown() }}
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="btn-list ota-r-table-actions justify-content-end">
                                    <a href="{{ route('admin.markups.edit', $rule) }}" class="jp-btn jp-btn--sm jp-btn--outline">Edit</a>
                                    @can('delete', $rule)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.markups.destroy', $rule) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('Delete this markup rule? This cannot be undone.');"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-secondary py-4">No markup rules yet. Default pricing will apply until rules are added.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rules->hasPages())
            <div class="card-footer py-2">{{ $rules->links() }}</div>
        @endif
    </div>
@endsection
