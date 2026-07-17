{{-- JP-PORTAL-2B · Customer support tickets — index (JetPK theme)
     Resolved by client_view('support.tickets.index', 'customer'); falls back to
     dashboard.customer.support.tickets.index when standalone mode is off\.

     RECOMPOSED (not wrapped) into the JetPK vocabulary. Preserved verbatim from the legacy view:
       • every column: Ticket, Subject, Booking, Status, Last updated, Action
       • data: $ticket->id, subject, booking->booking_reference, status, last_reply_at/created_at
       • helpers: e(), display_unknown()
       • components: <x-customer.support-status-badge> (canonical — reused, not duplicated)
       • routes: customer.support.tickets.{create,show}
       • pagination: $tickets->links() + hasPages()
       • data-testids: customer-support-tickets-empty, customer-support-tickets-table
       • section contract: account_title / account_subtitle / account_actions / account_content
     Desktop table + mobile card list are both kept — no information is hidden at any width. --}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Support tickets')

@section('account_title', 'Support tickets')
@section('account_subtitle', 'Track questions, requests, and booking support.')

@section('account_actions')
    <a href="{{ route('customer.support.tickets.create') }}" class="jp-btn jp-btn--primary">Create support ticket</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Support tickets'],
    ]" />

    @if (session('status'))
        <x-jp.alert variant="success">{{ session('status') }}</x-jp.alert>
    @endif

    @if ($tickets->isEmpty())
        <x-jp.card class="jp-portal__panel">
            <div class="jp-empty" data-testid="customer-support-tickets-empty">
                <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="message-circle" /></span>
                <p class="jp-empty__title">No support tickets yet</p>
                <p class="jp-empty__help">Create a ticket if you need help with a booking, payment, or document.</p>
                <a href="{{ route('customer.support.tickets.create') }}" class="jp-btn jp-btn--primary">Create support ticket</a>
            </div>
        </x-jp.card>
    @else
        <x-jp.card class="jp-portal__panel jp-portal__panel--flush">
            {{-- Desktop: full table. Every legacy column retained. --}}
            <div class="jp-table-wrap jp-table-wrap--desktop">
                <table class="jp-table" data-testid="customer-support-tickets-table">
                    <thead>
                        <tr>
                            <th scope="col">Ticket</th>
                            <th scope="col">Subject</th>
                            <th scope="col">Booking</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last updated</th>
                            <th scope="col" class="jp-table__cell--end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td data-label="Ticket"><strong>#{{ $ticket->id }}</strong></td>
                                <td data-label="Subject">{{ e($ticket->subject) }}</td>
                                <td data-label="Booking">{{ e(display_unknown($ticket->booking?->booking_reference)) }}</td>
                                <td data-label="Status"><x-customer.support-status-badge :status="$ticket->status" /></td>
                                <td data-label="Last updated">{{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</td>
                                <td data-label="Action" class="jp-table__cell--end">
                                    <a href="{{ route('customer.support.tickets.show', $ticket) }}" class="jp-btn jp-btn--ghost jp-btn--sm">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile: same data as cards — nothing dropped. --}}
            <div class="jp-portal__list jp-portal__list--mobile">
                @foreach ($tickets as $ticket)
                    <article class="jp-portal__list-card">
                        <div class="jp-portal__list-card-head">
                            <span class="jp-portal__list-card-ref">#{{ $ticket->id }}</span>
                            <x-customer.support-status-badge :status="$ticket->status" />
                        </div>
                        <div class="jp-portal__list-card-meta">
                            <span><strong>{{ e($ticket->subject) }}</strong></span>
                            @if ($ticket->booking)
                                <span>Booking: {{ e($ticket->booking->booking_reference) }}</span>
                            @endif
                            <span>{{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="jp-portal__list-card-actions">
                            <a href="{{ route('customer.support.tickets.show', $ticket) }}" class="jp-btn jp-btn--primary jp-btn--sm">View</a>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($tickets->hasPages())
                <div class="jp-portal__pagination">{{ $tickets->links() }}</div>
            @endif
        </x-jp.card>
    @endif
@endsection
