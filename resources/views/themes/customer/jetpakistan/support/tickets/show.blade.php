{{-- JP-PORTAL-2B · Customer support ticket — show/conversation (JetPK theme)
     Resolved by client_view('support.tickets.show', 'customer'); legacy fallback preserved.

     PRESERVED VERBATIM:
       • @can('reply', $ticket) / @can('close', $ticket) authorization — semantics unchanged
       • reply form: route('customer.support.tickets.reply', $ticket), post, @csrf,
         field name "body", required, maxlength="5000", old('body'), @error('body')
       • close form: route('customer.support.tickets.close', $ticket), post + @method('patch'),
         @csrf, and the confirm() guard
       • closed-ticket branch (@elseif $ticket->isClosed()) incl. the "reference ticket #id" copy
       • components reused (NOT duplicated): <x-support.ticket-timeline audience="customer">,
         <x-customer.support-status-badge>
       • sidebar facts: Status, Category ($ticket->category->label()), Booking link
         (route customer.bookings.show), Last updated (j M Y, H:i)
       • data-testid="customer-support-reply-form"

     KNOWN REMAINING LEGACY FRAGMENT — flagged, not hidden:
       @include('dashboard.support._thread') is a SHARED partial (customer + agent + staff).
       It is retained here deliberately: recomposing it inside a customer-only theme view would
       either fork it for one role or silently change the agent/staff thread. It should become a
       JetPK portal component (e.g. portal/support-thread) in a dedicated pass covering all roles.
       Until then this page is JetPK-themed EXCEPT the message thread. --}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Ticket #' . $ticket->id)

@section('account_title', 'Ticket #' . $ticket->id)
@section('account_subtitle', e($ticket->subject))

@section('account_actions')
    <a href="{{ route('customer.support.tickets.index') }}" class="jp-btn jp-btn--ghost">Back to list</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Support tickets', 'href' => client_route('customer.support.tickets.index')],
        ['label' => '#'.$ticket->id],
    ]" />

    @if (session('status'))
        <x-jp.alert variant="success">{{ session('status') }}</x-jp.alert>
    @endif

    <div class="jp-portal__detail-grid">
        <div class="jp-portal__stack">
            {{-- Shared thread partial — see the header note. --}}
            @include('dashboard.support._thread', ['messages' => $ticket->messages])

            @can('reply', $ticket)
                <x-jp.card class="jp-portal__panel">
                    <form method="post" action="{{ route('customer.support.tickets.reply', $ticket) }}" data-testid="customer-support-reply-form" class="jp-form">
                        @csrf
                        <div class="jp-form-group">
                            <label class="jp-label" for="body">Your reply</label>
                            <textarea
                                name="body"
                                id="body"
                                rows="4"
                                class="jp-textarea @error('body') jp-input--invalid @enderror"
                                required
                                maxlength="5000"
                                @error('body') aria-invalid="true" aria-describedby="reply-body-error" @enderror
                            >{{ old('body') }}</textarea>
                            @error('body')<p class="jp-field-error" id="reply-body-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="jp-form-actions">
                            <button type="submit" class="jp-btn jp-btn--primary">Send reply</button>
                        </div>
                    </form>
                </x-jp.card>
            @elseif ($ticket->isClosed())
                <x-jp.card class="jp-portal__panel">
                    <p class="jp-portal__note"><strong>Need more help?</strong> This ticket is finalised. Open a new support ticket and reference ticket #{{ $ticket->id }} so we can follow up.</p>
                    <a href="{{ route('customer.support.tickets.create') }}" class="jp-btn jp-btn--primary jp-btn--sm">Create new ticket</a>
                </x-jp.card>
            @endcan
        </div>

        <aside class="jp-portal__stack">
            <x-support.ticket-timeline :ticket="$ticket" audience="customer" variant="account" />

            <x-jp.card class="jp-portal__panel">
                <dl class="jp-portal__facts">
                    <dt>Status</dt>
                    <dd><x-customer.support-status-badge :status="$ticket->status" /></dd>

                    <dt>Category</dt>
                    <dd class="jp-portal__facts-capitalize">{{ e($ticket->category->label()) }}</dd>

                    @if ($ticket->booking)
                        <dt>Booking</dt>
                        <dd><a href="{{ route('customer.bookings.show', $ticket->booking) }}">{{ e($ticket->booking->booking_reference) }}</a></dd>
                    @endif

                    <dt>Last updated</dt>
                    <dd>{{ $ticket->last_reply_at?->format('j M Y, H:i') ?? $ticket->created_at->format('j M Y, H:i') }}</dd>
                </dl>

                @can('close', $ticket)
                    <form method="post" action="{{ route('customer.support.tickets.close', $ticket) }}" onsubmit="return confirm('Mark this ticket as resolved on your side?');" class="jp-portal__facts-action">
                        @csrf
                        @method('patch')
                        <button type="submit" class="jp-btn jp-btn--ghost jp-btn--block">Mark as resolved</button>
                    </form>
                    <p class="jp-portal__note jp-portal__note--muted">Staff control the official ticket status. Use this when your issue is handled.</p>
                @endcan
            </x-jp.card>
        </aside>
    </div>
@endsection
