@extends(client_layout('dashboard', 'admin'))

@section('title', 'Support tickets')

@section('page-header')
    <div class="jp-between">
        <div>
            <h1>Support tickets</h1>
            <p>Agency support requests from customers and agents.</p>
        </div>
    </div>
@endsection

@section('content')
<div class="jp-dtable-wrap" data-testid="admin-support-tickets-table">
    <table class="jp-dtable">
        <thead>
            <tr>
                <th>#</th>
                <th>Subject</th>
                <th>From</th>
                <th>Booking</th>
                <th>Category</th>
                <th>Status</th>
                <th>Last reply</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($tickets as $ticket)
            <tr>
                <td data-label="#"><span class="jp-cell-id">{{ $ticket->id }}</span></td>
                <td data-label="Subject">{{ e($ticket->subject) }}</td>
                <td data-label="From" class="jp-cell-sub">{{ e($ticket->createdBy?->name ?? '—') }}</td>
                <td data-label="Booking">{{ e($ticket->booking?->booking_reference ?? '—') }}</td>
                <td data-label="Category">{{ e($ticket->category->label()) }}</td>
                <td data-label="Status"><x-themes.admin.jetpakistan.components.status-badge :label="$ticket->status" /></td>
                <td data-label="Last reply" class="jp-cell-sub">{{ $ticket->last_reply_at?->diffForHumans() ?? '—' }}</td>
                <td data-label="Action">
                    <a href="{{ client_route('admin.support.tickets.show', $ticket) }}" class="jp-btn jp-btn--sm jp-btn--ghost">View</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><x-themes.admin.jetpakistan.components.empty-state title="No tickets" message="Support tickets will appear here." /></td></tr>
        @endforelse
        </tbody>
    </table>
    @if($tickets->hasPages())
        <div class="jp-pagination">{{ $tickets->links() }}</div>
    @endif
</div>
@endsection
