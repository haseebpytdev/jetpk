@extends(client_layout('customer-account', 'customer'))

@section('title', 'Support tickets')

@section('account_title', 'Support tickets')
@section('account_subtitle', 'Track questions, requests, and booking support.')

@section('account_actions')
    <a href="{{ route('customer.support.tickets.create') }}" class="ota-account-btn ota-account-btn--primary">Create support ticket</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => route('customer.dashboard')],
        ['label' => 'Support tickets'],
    ]" />

    @if (session('status'))
        <div class="ota-account-alert ota-account-alert--success">{{ session('status') }}</div>
    @endif

    @if ($tickets->isEmpty())
        <div class="ota-account-card">
            <div class="ota-account-card__body">
                <div class="ota-account-empty ota-account-empty--compact" data-testid="customer-support-tickets-empty">
                    <div class="ota-account-empty-icon" aria-hidden="true"><i class="ti ti-messages"></i></div>
                    <p class="ota-account-empty-title">No support tickets yet</p>
                    <p class="ota-account-empty-help">Create a ticket if you need help with a booking, payment, or document.</p>
                    <div class="ota-account-empty-action">
                        <a href="{{ route('customer.support.tickets.create') }}" class="ota-account-btn ota-account-btn--primary">Create support ticket</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="ota-account-card">
            <div class="ota-account-card__body ota-account-card__body--flush">
                <div class="ota-account-table-wrap ota-account-table--desktop">
                    <table class="ota-account-table mb-0" data-testid="customer-support-tickets-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Subject</th>
                                <th>Booking</th>
                                <th>Status</th>
                                <th>Last updated</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td><strong>#{{ $ticket->id }}</strong></td>
                                    <td>{{ e($ticket->subject) }}</td>
                                    <td>{{ e(display_unknown($ticket->booking?->booking_reference)) }}</td>
                                    <td><x-customer.support-status-badge :status="$ticket->status" /></td>
                                    <td>{{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('customer.support.tickets.show', $ticket) }}" class="ota-account-btn ota-account-btn--secondary ota-account-btn--sm">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="ota-account-list ota-account-list--mobile">
                    @foreach ($tickets as $ticket)
                        <article class="ota-account-list-card">
                            <div class="ota-account-list-card__head">
                                <span class="ota-account-list-card__ref">#{{ $ticket->id }}</span>
                                <x-customer.support-status-badge :status="$ticket->status" />
                            </div>
                            <div class="ota-account-list-card__meta">
                                <span><strong>{{ e($ticket->subject) }}</strong></span>
                                @if ($ticket->booking)
                                    <span>Booking: {{ e($ticket->booking->booking_reference) }}</span>
                                @endif
                                <span>{{ $ticket->last_reply_at?->diffForHumans() ?? $ticket->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="ota-account-list-card__actions">
                                <a href="{{ route('customer.support.tickets.show', $ticket) }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">View</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
            @if ($tickets->hasPages())
                <div class="ota-account-card__footer">{{ $tickets->links() }}</div>
            @endif
        </div>
    @endif
@endsection
