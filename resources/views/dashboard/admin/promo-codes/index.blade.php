@extends(client_layout('dashboard', 'admin'))

@section('title', 'Promo codes')

@section('page-header')
    <div class="jp-between">
        <div class="col">
            <div class="page-pretitle"><a href="{{ route('admin.settings.index') }}">Settings</a></div>
            <h1 class="jp-page-title">Promo codes</h1>
        </div>
        <div class="col-auto ms-auto d-flex gap-2">
            <a href="{{ route('admin.settings.index') }}" class="jp-btn jp-btn--ghost btn-sm">Settings hub</a>
            <a href="{{ route('admin.promo-codes.create') }}" class="jp-btn jp-btn--primary btn-sm">
                <i class="ti ti-plus me-1"></i>Create promo code
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('status') === 'promo-code-created')
        <div class="jp-alert jp-alert--success">Promo code created.</div>
    @elseif (session('status') === 'promo-code-updated')
        <div class="jp-alert jp-alert--success">Promo code updated.</div>
    @elseif (session('status') === 'promo-code-status-updated')
        <div class="jp-alert jp-alert--success">Promo code status updated.</div>
    @endif

    <div class="jp-alert jp-alert--info small mb-3">
        <strong>Checkout integration pending.</strong> Codes are not applied to booking fares until a future release.
    </div>

    <form method="GET" class="jp-card">
        <div class="jp-card__body">
            <div class="jp-form-grid jp-form-grid--filter">
                <div class="col-md-4">
                    <label class="jp-label">Search</label>
                    <input type="text" name="q" class="jp-control" value="{{ $filters['q'] ?? '' }}" placeholder="Code or name">
                </div>
                <div class="col-md-3">
                    <label class="jp-label">Status</label>
                    <select name="status" class="jp-control">
                        <option value="">All</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ ucfirst($status->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="jp-btn jp-btn--outline w-100">Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="jp-card">
        <div class="table-responsive">
            <table class="jp-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Discount</th>
                        <th>Usage</th>
                        <th>Valid</th>
                        <th>Status</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($promoCodes as $promo)
                        <tr>
                            <td class="fw-semibold">{{ $promo->code }}</td>
                            <td>{{ $promo->name ?? '—' }}</td>
                            <td>
                                @if ($promo->type->value === 'percent')
                                    {{ number_format((float) $promo->value, 2) }}%
                                @else
                                    {{ $promo->currency }} {{ number_format((float) $promo->value, 2) }}
                                @endif
                            </td>
                            <td>{{ $promo->used_count }}@if ($promo->usage_limit) / {{ $promo->usage_limit }}@endif</td>
                            <td class="text-secondary small">
                                @if ($promo->starts_at)
                                    from {{ $promo->starts_at->format('Y-m-d') }}
                                @endif
                                @if ($promo->ends_at)
                                    until {{ $promo->ends_at->format('Y-m-d') }}
                                @endif
                                @if (! $promo->starts_at && ! $promo->ends_at)
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $promo->status->value === 'active' ? 'success' : 'secondary' }}-lt">
                                    {{ ucfirst($promo->status->value) }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.promo-codes.edit', $promo) }}" class="jp-btn jp-btn--sm jp-btn--outline">Edit</a>
                                <form method="post" action="{{ route('admin.promo-codes.toggle-status', $promo) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="jp-btn jp-btn--sm jp-btn--ghost">
                                        {{ $promo->status->value === 'active' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-secondary text-center py-4">No promo codes yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($promoCodes->hasPages())
            <div class="card-footer">{{ $promoCodes->links() }}</div>
        @endif
    </div>
@endsection
