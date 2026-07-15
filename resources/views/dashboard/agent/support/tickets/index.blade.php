@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Support tickets')

@section('account_title', 'Support tickets')
@section('account_subtitle', 'Track your requests and replies from our team.')

@section('account_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage))
        <a href="{{ route('agent.support.tickets.create') }}" class="ota-account-btn ota-account-btn--primary" data-testid="agent-support-create-link">New ticket</a>
    @endif
@endsection

@section('account_content')
    <div class="ota-account-card">
        <div class="ota-account-card__body ota-account-card__body--flush">
            <div class="ota-account-table-wrap">
                <table class="ota-account-table mb-0" data-testid="agent-support-tickets-table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Booking</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Last reply</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td class="fw-semibold">{{ $ticket->id }}</td>
                            <td>{{ e($ticket->subject) }}</td>
                            <td>{{ e($ticket->booking?->booking_reference ?? '?') }}</td>
                            <td class="text-capitalize">{{ e($ticket->category->label()) }}</td>
                            <td><x-dashboard.status-badge :status="$ticket->status" /></td>
                            <td class="small text-secondary">{{ $ticket->last_reply_at?->diffForHumans() ?? '?' }}</td>
                            <td class="text-end">
                                <a href="{{ route('agent.support.tickets.show', $ticket) }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="ota-account-empty ota-account-empty--compact">
                                    <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-messages"></i></div>
                                    <p class="ota-account-empty-title">No support tickets yet</p>
                                    <p class="ota-account-empty-help">Create a ticket if you need help with a booking, payment, or document.</p>
                                    <div class="ota-account-empty-action">
                                        <a href="{{ route('agent.support.tickets.create') }}" class="ota-account-btn ota-account-btn--primary">New ticket</a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="ota-account-card__footer">{{ $tickets->links() }}</div>
            @endif
        </div>
    </div>
@endsection