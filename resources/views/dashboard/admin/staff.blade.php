@extends(client_layout('dashboard', 'admin'))

@section('title', 'Staff')

@push('styles')
<style>
    .staff-kpi .card { border: 1px solid rgba(98, 105, 118, 0.16); height: 100%; }
    .staff-kpi .card-body { padding: 0.85rem 1rem; }
    .staff-kpi .h2 { font-size: 1.4rem; margin-bottom: 0; font-variant-numeric: tabular-nums; }
    .staff-filters {
        background: #f8fafc;
        border-radius: 10px;
        padding: 1rem 1.1rem;
        border: 1px solid rgba(98, 105, 118, 0.14);
    }
    .staff-filters .jp-label {
        font-size: 0.72rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 0.3rem;
    }
    .staff-list-wrap {
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(98, 105, 118, 0.12);
        background: #fff;
    }
    .staff-list-wrap .card-header {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid rgba(98, 105, 118, 0.08);
    }
    .staff-table-wrap { max-width: 100%; min-width: 0; }
    .staff-table-wrap .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
    @media (max-width: 767.98px) {
        .staff-table-wrap .table thead th:nth-child(4),
        .staff-table-wrap .table thead th:nth-child(5),
        .staff-table-wrap .table thead th:nth-child(6),
        .staff-table-wrap .table tbody td:nth-child(4),
        .staff-table-wrap .table tbody td:nth-child(5),
        .staff-table-wrap .table tbody td:nth-child(6) {
            display: none;
        }
    }
</style>
@endpush

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle">Network</div>
            <h1 class="jp-page-title">Staff management</h1>
            <div class="text-secondary mt-1">
                Staff profiles, access status, and assignment visibility from live records.
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="row row-cards staff-kpi mb-3">
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="jp-card__body">
                    <div class="text-secondary small">Total staff</div>
                    <div class="h2 mb-0">{{ number_format($kpis['total'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="jp-card__body">
                    <div class="text-secondary small">Active staff</div>
                    <div class="h2 mb-0 text-success">{{ number_format($kpis['active'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="jp-card__body">
                    <div class="text-secondary small">Inactive/suspended</div>
                    <div class="h2 mb-0 text-warning">{{ number_format($kpis['inactive'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card card-sm">
                <div class="jp-card__body">
                    <div class="text-secondary small">Assigned bookings</div>
                    <div class="h2 mb-0">{{ number_format($kpis['assigned_bookings'] ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="staff-filters mb-3">
        <form method="get" class="jp-form-grid jp-form-grid--filter ota-r-form-grid">
            <div class="col-12 col-md-4">
                <label class="jp-label" for="staff-search">Search</label>
                <input id="staff-search" type="text" class="jp-control" name="search" placeholder="Name, email, department, job title" value="{{ $filters['search'] ?? '' }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="jp-label" for="staff-department">Department</label>
                <select id="staff-department" class="jp-control" name="department">
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept }}" @selected(($filters['department'] ?? '') === $dept)>{{ $dept }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="jp-label" for="staff-status">Status</label>
                <select id="staff-status" class="jp-control" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-12 col-md-2">
                <div class="ota-r-action-bar">
                    <button type="submit" class="jp-btn jp-btn--primary w-100">Apply filters</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card staff-list-wrap staff-table-wrap">
        <div class="card-header border-0 pb-0">
            <h3 class="jp-card__title mb-0">Staff</h3>
            <div class="jp-card__subtitle text-secondary">Click a row to open the staff member profile.</div>
        </div>
        <div class="table-responsive ota-r-table-wrap">
            <table class="jp-table table-hover table-striped mb-0 ota-r-text-safe">
                <thead class="table-light">
                    <tr>
                        <th>Staff code</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Job title</th>
                        <th class="text-end">Assigned bookings</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($staff as $row)
                        @php
                            $st = trim((string) ($row['status'] ?? ''));
                            if ($st === '') {
                                $st = 'unknown';
                            }
                            $openUrl = ! empty($row['user_id'])
                                ? route('admin.users.show', ['user' => $row['user_id']])
                                : route('admin.users.index', ['account_type' => 'staff', 'search' => $row['email'] ?? '']);
                        @endphp
                        <tr class="ota-admin-click-row"
                            data-href="{{ $openUrl }}"
                            tabindex="0"
                            role="link"
                            aria-label="Open staff member {{ $row['name'] }}">
                            <td class="fw-semibold">{{ $row['staff_code'] }}</td>
                            <td>{{ $row['name'] }}</td>
                            <td class="small">{{ $row['email'] }}</td>
                            <td>{{ $row['department'] }}</td>
                            <td class="small">{{ $row['job_title'] }}</td>
                            <td class="text-end">{{ number_format((int) ($row['assigned_bookings'] ?? 0)) }}</td>
                            <td><x-dashboard.status-badge :status="$st" data-testid="admin-staff-status-{{ $st }}" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-4">
                                No staff users have been created yet. Create staff from Users &amp; Access.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
