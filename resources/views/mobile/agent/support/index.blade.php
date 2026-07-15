@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Support tickets')

@section('mobile_app_title', 'Support')

@section('mobile_app_top_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage))
        <a href="{{ route('agent.support.tickets.create') }}" class="ota-mobile-app__top-action" data-testid="ota-mobile-agent-support-create-link">New</a>
    @endif
@endsection

@section('content')
    <div class="ota-mobile-agent" data-testid="ota-mobile-agent-support-index">
        @if (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        @if ($tickets->isEmpty())
            <div class="ota-mobile-agent__empty" data-testid="agent-support-tickets-empty">
                <p class="ota-mobile-agent__empty-title">No support tickets yet</p>
                <p class="ota-mobile-agent__empty-help">Create a ticket if you need help with a booking, payment, or document.</p>
                @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage))
                    <a href="{{ route('agent.support.tickets.create') }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">Create ticket</a>
                @endif
            </div>
        @else
            <div class="ota-mobile-agent__list">
                @foreach ($tickets as $ticket)
                    @php
                        $status = $ticket->status instanceof \App\Enums\SupportTicketStatus
                            ? $ticket->status
                            : \App\Enums\SupportTicketStatus::tryFrom((string) $ticket->status);
                        $statusLabel = $status?->customerLabel() ?? str_replace('_', ' ', (string) $ticket->status);
                        $statusTone = match ($status) {
                            \App\Enums\SupportTicketStatus::Open, \App\Enums\SupportTicketStatus::Pending => 'pending',
                            \App\Enums\SupportTicketStatus::Resolved, \App\Enums\SupportTicketStatus::Closed => 'positive',
                            default => 'muted',
                        };
                    @endphp
                    <article class="ota-mobile-agent__card" data-testid="ota-mobile-agent-support-ticket-card">
                        <div class="ota-mobile-agent__card-head">
                            <span class="ota-mobile-agent__ref">#{{ $ticket->id }}</span>
                            <span class="ota-mobile-agent__pill ota-mobile-agent__pill--{{ $statusTone }}">{{ $statusLabel }}</span>
                        </div>
                        <p class="ota-mobile-agent__ticket-subject">{{ e($ticket->subject) }}</p>
                        @if ($ticket->booking)
                            <p class="ota-mobile-agent__muted">Booking: {{ e($ticket->booking->booking_reference) }}</p>
                        @endif
                        <p class="ota-mobile-agent__muted">
                            Updated {{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}
                        </p>
                        <div class="ota-mobile-agent__actions">
                            <a href="{{ route('agent.support.tickets.show', $ticket) }}" class="ota-mobile-agent__btn ota-mobile-agent__btn--primary">View</a>
                        </div>
                    </article>
                @endforeach
            </div>
            @if ($tickets->hasPages())
                <div class="ota-mobile-agent__pagination">{{ $tickets->links() }}</div>
            @endif
        @endif
    </div>
@endsection
