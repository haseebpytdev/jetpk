{{-- JP-PORTAL-3 TASK 8 · Agent / Agent Staff support tickets — create (JetPK theme)
     Resolved by client_view('support.tickets.create', 'agent'); dashboard.agent.support.tickets.create
     remains the fallback for standalone mode is off\.
     Route gate: agent.permission:SupportManage + platform.module:agent_support.

     *** ENCODING FIX — DELIBERATE, DOCUMENTED ***
     The legacy file is CP1252-encoded, not UTF-8: byte 0x97 (a Windows-1252 em dash) appears raw
     in "<0x97> None <0x97>" and in the booking option separator. Served as UTF-8 those bytes are
     invalid and render as replacement characters. This view emits proper UTF-8 em dashes (—).
     This changes only decorative separator glyphs — no field name, value, option value or
     submitted datum is affected. Flagged in the contract matrix; the legacy file is left alone.

     PRESERVED EXACTLY:
       • controller vars: $categories, $bookings
       • form: method="post" action=route('agent.support.tickets.store'), @csrf, NO @method spoof,
         NO enctype (no file input on this form)
       • field names: subject, category, booking_id, body
       • subject: required, maxlength="200", old('subject'), @error
       • category: required, options from $categories ($cat->value / $cat->label()),
         @selected(old('category') === $cat->value) — NOTE legacy has NO @error for category;
         not invented here
       • booking_id: optional, "— None —" empty option with value="",
         @selected((string) old('booking_id') === (string) $booking->id) — the string casts are
         load-bearing and preserved; label e($booking->booking_reference ?? 'Booking #'.$id)
         — e($booking->route ?? '')
       • body: textarea rows="6", required, maxlength="5000", old('body') — NOTE legacy has NO
         @error for body on THIS page (unlike show); not invented
       • actions: "Submit ticket" + "Cancel" -> index
       • data-testid: agent-support-ticket-form
--}}
@extends(client_layout('agent-portal', 'agent'))

@section('title', 'New support ticket')

@section('account_title', 'New support ticket')
@section('account_subtitle', 'Describe your issue and we will get back to you by email.')

@section('account_actions')
    <a href="{{ route('agent.support.tickets.index') }}" class="jp-btn jp-btn--ghost">Back to tickets</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('agent.dashboard')],
        ['label' => 'Support tickets', 'href' => route('agent.support.tickets.index')],
        ['label' => 'New ticket'],
    ]" />

    <x-jp.card class="jp-portal__panel">
        <form method="post" action="{{ route('agent.support.tickets.store') }}" data-testid="agent-support-ticket-form" class="jp-form">
            @csrf

            <div class="jp-field">
                <label class="jp-label" for="subject">Subject</label>
                <input type="text" name="subject" id="subject" class="jp-input @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required maxlength="200">
                @error('subject')<p class="jp-field__error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-field">
                <label class="jp-label" for="category">Category</label>
                <select name="category" id="category" class="jp-select" required>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="jp-field">
                <label class="jp-label" for="booking_id">Linked booking (optional)</label>
                <select name="booking_id" id="booking_id" class="jp-select">
                    <option value="">— None —</option>
                    @foreach ($bookings as $booking)
                        <option value="{{ $booking->id }}" @selected((string) old('booking_id') === (string) $booking->id)>{{ e($booking->booking_reference ?? 'Booking #'.$booking->id) }} — {{ e($booking->route ?? '') }}</option>
                    @endforeach
                </select>
            </div>

            <div class="jp-field">
                <label class="jp-label" for="body">Message</label>
                <textarea name="body" id="body" rows="6" class="jp-textarea" required maxlength="5000">{{ old('body') }}</textarea>
            </div>

            <div class="jp-form__actions">
                <button type="submit" class="jp-btn jp-btn--primary">Submit ticket</button>
                <a href="{{ route('agent.support.tickets.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            </div>
        </form>
    </x-jp.card>
@endsection
