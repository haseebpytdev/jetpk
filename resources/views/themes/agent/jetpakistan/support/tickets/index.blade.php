{{-- JP-PORTAL-3 TASK 8 · Agent / Agent Staff support tickets — index (JetPK theme)
     Resolved by client_view('support.tickets.index', 'agent'); dashboard.agent.support.tickets.index
     remains the fallback for standalone mode is off\.
     Route gate: agent.permission:SupportManage + platform.module:agent_support.

     PRESERVED EXACTLY:
       • controller var: $tickets (paginator)
       • "New ticket" gated by hasAgentPermission(SupportManage) — reproduced verbatim
       • columns: #, Subject, Booking, Category, Status, Last reply, Action
       • LITERAL '?' FALLBACKS: legacy renders
             $ticket->booking?->booking_reference ?? '?'
             $ticket->last_reply_at?->diffForHumans() ?? '?'
         The legacy file is pure ASCII — that really is a question mark, NOT a mangled em dash.
         Reproduced verbatim. It looks like a typo for '—', but changing it would alter rendered
         output, so it is preserved and flagged in the contract matrix instead.
       • <x-dashboard.status-badge :status="$ticket->status" /> — note the AGENT page uses the
         shared dashboard badge, NOT <x-customer.support-status-badge> (which the customer index
         uses). Reused as-is; the two roles legitimately differ here.
       • e() escaping on subject, booking_reference, category label
       • category rendered capitalised via $ticket->category->label()
       • pagination: $tickets->links() gated by hasPages()
       • empty state copy verbatim, including its unconditional "New ticket" action (legacy does
         NOT permission-gate the empty-state button, unlike the header — reproduced as-is)
       • data-testids: agent-support-create-link, agent-support-tickets-table
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Support tickets')

@section('account_title', 'Support tickets')
@section('account_subtitle', 'Track your requests and replies from our team.')

@section('account_actions')
    @if (auth()->user()?->hasAgentPermission(\App\Support\Agents\AgentPermission::SupportManage))
        <a href="{{ route('agent.support.tickets.create') }}" class="jp-btn jp-btn--primary" data-testid="agent-support-create-link">New ticket</a>
    @endif
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Support tickets'],
    ]" />

    <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
        <div class="jp-table-wrap jp-table-wrap--desktop">
            <table class="jp-table" data-testid="agent-support-tickets-table">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Subject</th>
                        <th scope="col">Booking</th>
                        <th scope="col">Category</th>
                        <th scope="col">Status</th>
                        <th scope="col">Last reply</th>
                        <th scope="col" class="jp-table__cell--end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td data-label="#"><strong>{{ $ticket->id }}</strong></td>
                            <td data-label="Subject">{{ e($ticket->subject) }}</td>
                            <td data-label="Booking">{{ e($ticket->booking?->booking_reference ?? '?') }}</td>
                            <td data-label="Category" class="jp-table__cell--capitalize">{{ e($ticket->category->label()) }}</td>
                            <td data-label="Status"><x-dashboard.status-badge :status="$ticket->status" /></td>
                            <td data-label="Last reply">{{ $ticket->last_reply_at?->diffForHumans() ?? '?' }}</td>
                            <td data-label="Action" class="jp-table__cell--end">
                                <a href="{{ route('agent.support.tickets.show', $ticket) }}" class="jp-btn jp-btn--ghost jp-btn--sm">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="jp-empty">
                                    <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="message-circle" /></span>
                                    <p class="jp-empty__title">No support tickets yet</p>
                                    <p class="jp-empty__help">Create a ticket if you need help with a booking, payment, or document.</p>
                                    <a href="{{ route('agent.support.tickets.create') }}" class="jp-btn jp-btn--primary">New ticket</a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="jp-portal__list jp-portal__list--mobile">
            @forelse ($tickets as $ticket)
                <article class="jp-portal__list-card">
                    <div class="jp-portal__list-card-head">
                        <span class="jp-portal__list-card-ref">#{{ $ticket->id }}</span>
                        <x-dashboard.status-badge :status="$ticket->status" />
                    </div>
                    <div class="jp-portal__list-card-meta">
                        <span><strong>{{ e($ticket->subject) }}</strong></span>
                        <span>Booking: {{ e($ticket->booking?->booking_reference ?? '?') }}</span>
                        <span class="jp-table__cell--capitalize">Category: {{ e($ticket->category->label()) }}</span>
                        <span>Last reply: {{ $ticket->last_reply_at?->diffForHumans() ?? '?' }}</span>
                    </div>
                    <div class="jp-portal__list-card-actions">
                        <a href="{{ route('agent.support.tickets.show', $ticket) }}" class="jp-btn jp-btn--primary jp-btn--sm">View</a>
                    </div>
                </article>
            @empty
                <div class="jp-empty">
                    <p class="jp-empty__title">No support tickets yet</p>
                    <p class="jp-empty__help">Create a ticket if you need help with a booking, payment, or document.</p>
                    <a href="{{ route('agent.support.tickets.create') }}" class="jp-btn jp-btn--primary">New ticket</a>
                </div>
            @endforelse
        </div>

        @if ($tickets->hasPages())
            <div class="jp-portal__pagination">{{ $tickets->links() }}</div>
        @endif
    </x-jp.card>
@endsection
