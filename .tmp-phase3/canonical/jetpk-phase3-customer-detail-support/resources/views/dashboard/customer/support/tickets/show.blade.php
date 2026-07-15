@extends(client_layout('customer-account', 'customer'))

@section('title', 'Ticket #' . $ticket->id)

@section('account_title', 'Ticket #' . $ticket->id)
@section('account_subtitle', e($ticket->subject))

@section('account_actions')
    <a href="{{ route('customer.support.tickets.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to list</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => route('customer.dashboard')],
        ['label' => 'Support tickets', 'href' => route('customer.support.tickets.index')],
        ['label' => '#' . $ticket->id],
    ]" />

    @if (session('status'))
        <div class="ota-account-alert ota-account-alert--success">{{ session('status') }}</div>
    @endif

    <div class="ota-account-detail-grid">
        <div class="ota-account-stack">
            @include('dashboard.support._thread', ['messages' => $ticket->messages])
            @can('reply', $ticket)
                <div class="ota-account-card ota-account-form-card">
                    <div class="ota-account-card__body">
                        <form method="post" action="{{ route('customer.support.tickets.reply', $ticket) }}" data-testid="customer-support-reply-form">
                            @csrf
                            <label class="form-label" for="body">Your reply</label>
                            <textarea name="body" id="body" rows="4" class="form-control @error('body') is-invalid @enderror" required maxlength="5000">{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="ota-account-form-actions">
                                <button type="submit" class="ota-account-btn ota-account-btn--primary">Send reply</button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif ($ticket->isClosed())
                <div class="ota-account-card">
                    <div class="ota-account-card__body">
                        <p class="mb-2"><strong>Need more help?</strong> This ticket is finalised. Open a new support ticket and reference ticket #{{ $ticket->id }} so we can follow up.</p>
                        <a href="{{ route('customer.support.tickets.create') }}" class="ota-account-btn ota-account-btn--primary ota-account-btn--sm">Create new ticket</a>
                    </div>
                </div>
            @endcan
        </div>
        <aside class="ota-account-stack">
            <x-support.ticket-timeline :ticket="$ticket" audience="customer" variant="account" />
            <div class="ota-account-card">
                <div class="ota-account-card__body vstack gap-3">
                    <div>
                        <span class="d-block small text-secondary mb-1">Status</span>
                        <x-customer.support-status-badge :status="$ticket->status" />
                    </div>
                    <div>
                        <span class="d-block small text-secondary mb-1">Category</span>
                        <span class="text-capitalize">{{ e($ticket->category->label()) }}</span>
                    </div>
                    @if ($ticket->booking)
                        <div>
                            <span class="d-block small text-secondary mb-1">Booking</span>
                            <a href="{{ route('customer.bookings.show', $ticket->booking) }}">{{ e($ticket->booking->booking_reference) }}</a>
                        </div>
                    @endif
                    <div>
                        <span class="d-block small text-secondary mb-1">Last updated</span>
                        <span>{{ $ticket->last_reply_at?->format('j M Y, H:i') ?? $ticket->created_at->format('j M Y, H:i') }}</span>
                    </div>
                    @can('close', $ticket)
                        <form method="post" action="{{ route('customer.support.tickets.close', $ticket) }}" onsubmit="return confirm('Mark this ticket as resolved on your side?');">
                            @csrf
                            @method('patch')
                            <button type="submit" class="ota-account-btn ota-account-btn--secondary ota-account-btn--block">Mark as resolved</button>
                        </form>
                        <p class="small text-muted mb-0">Staff control the official ticket status. Use this when your issue is handled.</p>
                    @endcan
                </div>
            </div>
        </aside>
    </div>
@endsection
