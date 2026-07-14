@extends(client_layout('dashboard', 'admin'))

@section('title', 'Agent deposits')

@push('styles')
<style>
    @media (max-width: 767.98px) {
        .admin-agent-deposits-table .jp-table thead th:nth-child(5),
        .admin-agent-deposits-table .jp-table tbody td:nth-child(5) {
            display: none;
        }
        .admin-agent-deposits-table .jp-table thead th:last-child,
        .admin-agent-deposits-table .jp-table tbody td:last-child {
            position: sticky;
            right: 0;
            z-index: 1;
            background: var(--jp-color-surface, #fff);
            box-shadow: -4px 0 10px rgba(15, 23, 42, 0.08);
        }
    }
</style>
@endpush

@section('page-header')
    <x-dashboard.section-header
        title="Agency deposit requests"
        subtitle="Review submitted fund-load requests by agency. Approve or reject with notes."
    />
@endsection

@section('content')
    @php
        use App\Support\Identity\IdentityDisplay;
    @endphp

    <div class="row g-3 mb-4" data-testid="admin-agent-deposits-kpis">
        <div class="col-md-4">
            <div class="jp-card">
                <div class="jp-card__body">
                    <div class="small text-secondary">Pending review</div>
                    <div class="h3 mb-0">{{ number_format((int) $pendingCount) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="jp-card mb-3">
        <div class="jp-card__body py-2">
            <form method="get" class="jp-form-grid jp-form-grid--filter ota-r-form-grid">
                <div class="col-12 col-sm-auto">
                    <label class="jp-label small mb-0" for="status">Status</label>
                    <select name="status" id="status" class="jp-control jp-control--sm">
                        <option value="">All</option>
                        <option value="submitted" @selected(($filters['status'] ?? '') === 'submitted')>Submitted (pending)</option>
                        <option value="approved" @selected(($filters['status'] ?? '') === 'approved')>Approved</option>
                        <option value="rejected" @selected(($filters['status'] ?? '') === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="col-12 col-sm-auto">
                    <div class="jp-action-bar">
                        <button type="submit" class="jp-btn jp-btn--sm jp-btn--outline">Filter</button>
                        @if (($filters['status'] ?? '') !== 'submitted')
                            <a href="{{ route('admin.agent-deposits.index', ['status' => 'submitted']) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Pending only</a>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="jp-card admin-agent-deposits-table">
        <div class="jp-card__body jp-card__body--flush">
            <div class="table-responsive ota-r-table-wrap">
                <table class="jp-table mb-0 ota-r-text-safe" data-testid="admin-agent-deposits-table">
                    <thead>
                        <tr>
                            <th>Submitted</th>
                            <th>Agency</th>
                            <th>Requested by</th>
                            <th>Amount</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $deposit)
                            @php
                                $agency = $deposit->agency;
                                $agencyCode = IdentityDisplay::agencyCodeDisplay($agency);
                                $requester = $deposit->user ?? $deposit->agent?->user;
                            @endphp
                            <tr data-testid="admin-agent-deposit-row-{{ $deposit->id }}">
                                <td class="text-nowrap">{{ $deposit->created_at?->format('Y-m-d H:i') }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $agency?->name ?? 'Agency #'.$deposit->agency_id }}</div>
                                    @if ($agencyCode !== null)
                                        <div class="small text-secondary">{{ IdentityDisplay::labelAgencyCode() }}: {{ $agencyCode }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $requester?->name ?? '—' }}</div>
                                    @if ($requester?->email)
                                        <div class="small text-secondary">{{ $requester->email }}</div>
                                    @endif
                                    @if ($requester)
                                        <div class="small text-secondary">{{ IdentityDisplay::labelUserActorId() }}: <span class="font-monospace">{{ IdentityDisplay::userActorId($requester) }}</span></div>
                                        <div class="small text-secondary">{{ IdentityDisplay::labelAccessType() }}: {{ IdentityDisplay::accessTypeLabel($requester) }}</div>
                                    @endif
                                </td>
                                <td class="text-nowrap">Rs {{ number_format((float) $deposit->amount, 2) }}</td>
                                <td class="ota-r-text-safe">{{ $deposit->reference ?? '—' }}</td>
                                <td><x-dashboard.status-badge :status="$deposit->status->value" /></td>
                                <td class="text-end">
                                    <div class="jp-action-bar jp-action-bar--end flex-wrap">
                                        <a href="{{ route('admin.agent-deposits.show', $deposit) }}" class="jp-btn jp-btn--sm jp-btn--outline" data-testid="admin-deposit-review-{{ $deposit->id }}">Review</a>
                                        @if ($deposit->status->value === 'submitted' && filled($deposit->proof_path))
                                            <a href="{{ route('admin.agent-deposits.proof', $deposit) }}" class="jp-btn jp-btn--sm jp-btn--ghost">Proof</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-dashboard.empty-state icon="ti-cash" title="No deposit requests" help="Agent deposit requests will appear here." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($deposits->hasPages())
            <div class="jp-card__footer">{{ $deposits->links() }}</div>
        @endif
    </div>
@endsection
