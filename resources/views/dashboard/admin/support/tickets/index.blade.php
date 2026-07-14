@extends(client_layout('dashboard', 'admin'))
@section('title', 'Support tickets')
@push('styles')
<style>
    @media (max-width: 767.98px) {
        .admin-support-tickets-table .table thead th:nth-child(4),
        .admin-support-tickets-table .table thead th:nth-child(5),
        .admin-support-tickets-table .table thead th:nth-child(7),
        .admin-support-tickets-table .table tbody td:nth-child(4),
        .admin-support-tickets-table .table tbody td:nth-child(5),
        .admin-support-tickets-table .table tbody td:nth-child(7) {
            display: none;
        }
        .admin-support-tickets-table .table thead th:last-child,
        .admin-support-tickets-table .table tbody td:last-child {
            position: sticky;
            right: 0;
            z-index: 1;
            background: var(--tblr-bg-surface, #fff);
            box-shadow: -4px 0 10px rgba(15, 23, 42, 0.08);
        }
    }
</style>
@endpush
@section('page-header')
    <x-dashboard.section-header title="Support tickets" subtitle="Agency support requests from customers and agents." />
@endsection
@section('content')
    <div class="card border-0 shadow-sm admin-support-tickets-table ota-admin-table">
        <div class="table-responsive ota-r-table-wrap">
            <table class="table card-jp-table mb-0 ota-r-text-safe ota-admin-table" data-testid="admin-support-tickets-table">
                <thead class="table-light"><tr>
                    <th>#</th><th>Subject</th><th>From</th><th>Booking</th><th>Category</th><th>Status</th><th>Last reply</th><th class="text-end w-1">Action</th>
                </tr></thead>
                <tbody>
                @forelse($tickets as $ticket)
                    <tr>
                        <td class="fw-semibold">{{ $ticket->id }}</td>
                        <td>{{ e($ticket->subject) }}</td>
                        <td class="small">{{ e($ticket->createdBy?->name ?? '—') }}</td>
                        <td>{{ e($ticket->booking?->booking_reference ?? '—') }}</td>
                        <td class="text-capitalize">{{ e($ticket->category->label()) }}</td>
                        <td><x-dashboard.status-badge :status="$ticket->status" /></td>
                        <td class="small text-secondary text-nowrap">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                        <td class="text-end">
                            <div class="ota-r-table-actions justify-content-end ota-admin-action-group">
                                <a href="{{ route('admin.support.tickets.show', $ticket) }}" class="jp-btn jp-btn--sm jp-btn--outline">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-dashboard.empty-state title="No tickets" description="Support tickets will appear here." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
            <div class="card-footer">{{ $tickets->links() }}</div>
        @endif
    </div>
@endsection
