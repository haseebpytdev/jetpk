{{-- JP-PORTAL-3 TASK 8 · Agent / Agent Staff support ticket — show (JetPK theme)
     Resolved by client_view('support.tickets.show', 'agent'); dashboard.agent.support.tickets.show
     remains the fallback for standalone mode is off\.
     Route gate: agent.permission:SupportManage + platform.module:agent_support.

     THREAD: uses the SAME role-neutral JetPK portal support-thread component as the customer show
     view (JP-PORTAL-3 TASK 4), with audience="agent". dashboard.support._thread and
     dashboard.support._message are NOT referenced from this page.

     PRESERVED EXACTLY:
       • controller var: $ticket
       • account_title / account_subtitle are SECTION BODIES (dynamic), not string args
       • @can('reply', $ticket) gate on the reply form — semantics unchanged. Legacy has NO
         @can('close') branch and NO closed-ticket branch on the AGENT page (unlike customer);
         neither is invented here.
       • reply form: route('agent.support.tickets.reply', $ticket), post, @csrf,
         field name "body", required, maxlength="5000", old('body'), @error('body')
       • data-testid="customer-support-reply-form" on the AGENT reply form — a legacy misnomer,
         but renaming it would break the existing suite. Kept verbatim.
       • <x-support.ticket-timeline :ticket="$ticket" audience="agent" variant="account" />
         (canonical component — reused, audience correctly "agent")
       • <x-dashboard.status-badge :status="$ticket->status" /> — the agent page uses the shared
         dashboard badge, matching agent index
       • facts: Status, Category (e($ticket->category->label()), capitalised), Booking link via
         route('agent.bookings.show', $ticket->booking) gated by @if($ticket->booking)
       • e() escaping preserved on subject, category label, booking_reference
     Support-ticket backend untouched.
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'Ticket #' . $ticket->id)

@section('account_title')
    Ticket #{{ $ticket->id }}
@endsection

@section('account_subtitle')
    {{ e($ticket->subject) }}
@endsection

@section('account_actions')
    <a href="{{ route('agent.support.tickets.index') }}" class="jp-btn jp-btn--ghost">Back to list</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Support tickets', 'href' => route('agent.support.tickets.index')],
        ['label' => '#'.$ticket->id],
    ]" />

    @if (session('status'))
        <x-jp.alert variant="success">{{ session('status') }}</x-jp.alert>
    @endif

    <div class="jp-portal__detail-grid">
        <div class="jp-portal__stack">
            @include('themes.frontend.jetpakistan.components.portal.support-thread', [
                'messages' => $ticket->messages,
                'audience' => 'agent',
            ])

            @can('reply', $ticket)
                <x-jp.card class="jp-portal__panel">
                    <form method="post" action="{{ route('agent.support.tickets.reply', $ticket) }}" data-testid="customer-support-reply-form" class="jp-form">
                        @csrf
                        <div class="jp-field">
                            <label class="jp-label" for="body">Your reply</label>
                            <textarea
                                name="body"
                                id="body"
                                rows="4"
                                class="jp-textarea @error('body') is-invalid @enderror"
                                required
                                maxlength="5000"
                                @error('body') aria-invalid="true" aria-describedby="reply-body-error" @enderror
                            >{{ old('body') }}</textarea>
                            @error('body')<p class="jp-field__error" id="reply-body-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="jp-form__actions">
                            <button type="submit" class="jp-btn jp-btn--primary">Send reply</button>
                        </div>
                    </form>
                </x-jp.card>
            @endcan
        </div>

        <aside class="jp-portal__stack">
            <x-support.ticket-timeline :ticket="$ticket" audience="agent" variant="account" />

            <x-jp.card class="jp-portal__panel">
                <dl class="jp-portal__facts">
                    <dt>Status</dt>
                    <dd><x-dashboard.status-badge :status="$ticket->status" /></dd>

                    <dt>Category</dt>
                    <dd class="jp-table__cell--capitalize">{{ e($ticket->category->label()) }}</dd>

                    @if ($ticket->booking)
                        <dt>Booking</dt>
                        <dd><a href="{{ route('agent.bookings.show', $ticket->booking) }}">{{ e($ticket->booking->booking_reference) }}</a></dd>
                    @endif
                </dl>
            </x-jp.card>
        </aside>
    </div>
@endsection
