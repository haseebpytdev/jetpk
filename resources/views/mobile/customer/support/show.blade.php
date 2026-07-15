@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Ticket #'.$ticket->id)

@section('mobile_app_title', 'Ticket #'.$ticket->id)

@section('mobile_app_back')
    <a href="{{ route('customer.support.tickets.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to support tickets">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    @php
        $status = $ticket->status instanceof \App\Enums\SupportTicketStatus
            ? $ticket->status
            : \App\Enums\SupportTicketStatus::tryFrom((string) $ticket->status);
        $statusLabel = $status?->customerLabel() ?? str_replace('_', ' ', (string) $ticket->status);
    @endphp

    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-support-show">
        @if (session('status'))
            @include('mobile.components.alert', ['type' => 'success', 'message' => session('status')])
        @endif

        <section class="ota-mobile-customer__card">
            <h1 class="ota-mobile-customer__page-title">{{ e($ticket->subject) }}</h1>
            <dl class="ota-mobile-customer__meta">
                <div>
                    <dt>Status</dt>
                    <dd>{{ $statusLabel }}</dd>
                </div>
                <div>
                    <dt>Category</dt>
                    <dd>{{ $ticket->category->label() }}</dd>
                </div>
                @if ($ticket->booking)
                    <div>
                        <dt>Booking</dt>
                        <dd>
                            <a href="{{ route('customer.bookings.show', $ticket->booking) }}" class="ota-mobile-customer__link">
                                {{ e($ticket->booking->booking_reference) }}
                            </a>
                        </dd>
                    </div>
                @endif
                <div>
                    <dt>Last updated</dt>
                    <dd>{{ $ticket->last_reply_at?->format('j M Y, H:i') ?? $ticket->created_at->format('j M Y, H:i') }}</dd>
                </div>
            </dl>
        </section>

        <x-support.ticket-timeline :ticket="$ticket" audience="customer" variant="mobile" />

        <section class="ota-mobile-customer__card" data-testid="support-ticket-thread">
            <h2 class="ota-mobile-customer__card-title">Messages</h2>
            @forelse($ticket->messages as $message)
                <article class="ota-mobile-customer__message">
                    <div class="ota-mobile-customer__message-head">
                        <span class="ota-mobile-customer__message-author">{{ e($message->author?->name ?? 'Support') }}</span>
                        <span class="ota-mobile-customer__muted">{{ $message->created_at?->format('j M Y, g:i A') }}</span>
                    </div>
                    <p class="ota-mobile-customer__message-body">{{ e($message->body) }}</p>
                </article>
            @empty
                <p class="ota-mobile-customer__note">No messages yet.</p>
            @endforelse
        </section>

        @can('reply', $ticket)
            <section class="ota-mobile-customer__card ota-mobile-customer__form-card">
                <h2 class="ota-mobile-customer__card-title">Your reply</h2>
                <form method="post" action="{{ route('customer.support.tickets.reply', $ticket) }}" class="ota-mobile-customer__form" data-testid="customer-support-reply-form">
                    @csrf
                    <div class="ota-mobile-customer__field">
                        <textarea name="body" rows="4" class="ota-mobile-customer__input{{ $errors->has('body') ? ' is-invalid' : '' }}" required maxlength="5000">{{ old('body') }}</textarea>
                        @error('body')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary ota-mobile-customer__btn--block">Send reply</button>
                </form>
            </section>
        @elseif ($ticket->isClosed())
            <section class="ota-mobile-customer__card">
                <p class="ota-mobile-customer__note">This ticket is finalised. Open a new ticket and reference #{{ $ticket->id }}.</p>
                <a href="{{ route('customer.support.tickets.create') }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary">Create new ticket</a>
            </section>
        @endcan

        @can('close', $ticket)
            <section class="ota-mobile-customer__card">
                <form method="post" action="{{ route('customer.support.tickets.close', $ticket) }}" onsubmit="return confirm('Mark this ticket as resolved on your side?');">
                    @csrf
                    @method('patch')
                    <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--secondary ota-mobile-customer__btn--block">Mark as resolved</button>
                </form>
                <p class="ota-mobile-customer__note">Staff control the official ticket status.</p>
            </section>
        @endcan
    </div>
@endsection
