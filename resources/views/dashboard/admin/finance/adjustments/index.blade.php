@extends(client_layout('dashboard', 'admin'))

@section('title', 'Manual Adjustments')

@section('page-header')
    <x-dashboard.section-header title="Manual Adjustments" subtitle="Controlled wallet corrections with ledger posting.">
        <x-slot name="actions">
            <a href="{{ route('admin.finance.adjustments.export') }}" class="jp-btn jp-btn--outline btn-sm" data-testid="finance-adjustments-export-csv">
                <i class="ti ti-download me-1"></i> Export Manual Adjustments CSV
            </a>
            <a href="{{ route('admin.finance.adjustments.create') }}" class="jp-btn jp-btn--primary" data-testid="finance-adjustments-create-link">New adjustment</a>
        </x-slot>
    </x-dashboard.section-header>
@endsection

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="jp-card__body jp-card__body--flush">
            <div class="table-responsive">
                <table class="jp-table mb-0" data-testid="finance-adjustments-index-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Agency</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Balance after</th>
                            <th>By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $tx)
                            @php
                                $isReversal = isset($reversalOfIds[$tx->id]);
                                $isReversed = isset($reversedOriginalIds[$tx->id]);
                            @endphp
                            <tr>
                                <td>#{{ $tx->id }}</td>
                                <td>{{ $tx->created_at?->format('Y-m-d H:i') }}</td>
                                <td>{{ $tx->agency?->name ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-secondary-lt">{{ str_replace('_', ' ', $tx->type->value) }}</span>
                                    @if ($isReversal)
                                        <span class="badge bg-azure-lt" data-testid="finance-adjustment-badge-reversal">Reversal</span>
                                    @endif
                                    @if ($isReversed)
                                        <span class="badge bg-yellow-lt" data-testid="finance-adjustment-badge-reversed">Reversed</span>
                                    @endif
                                </td>
                                <td>Rs {{ number_format((float) $tx->amount, 2) }}</td>
                                <td>Rs {{ number_format((float) $tx->balance_after, 2) }}</td>
                                <td>{{ $tx->creator?->name ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.finance.adjustments.show', $tx) }}" class="jp-btn jp-btn--sm jp-btn--ghost">View</a>
                                    @if ($adjustments->canReverse($tx))
                                        <a href="{{ route('admin.finance.adjustments.reverse.confirm', $tx) }}" class="btn btn-sm btn-outline-danger" data-testid="finance-adjustment-reverse-link">Reverse</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-secondary text-center py-4">No manual adjustments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($transactions->hasPages())
            <div class="card-footer">{{ $transactions->links() }}</div>
        @endif
    </div>
@endsection
