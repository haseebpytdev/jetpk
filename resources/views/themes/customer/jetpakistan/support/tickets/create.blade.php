{{-- JP-PORTAL-2B · Customer support tickets — create (JetPK theme)
     Resolved by client_view('support.tickets.create', 'customer'); legacy fallback preserved.

     PRESERVED VERBATIM — do not "simplify" any of these:
       • form action route('customer.support.tickets.store') + method post + @csrf
       • field names: subject, category, booking_id, body   (JS/backend contract)
       • ids: subject, category, booking_id, body           (label association)
       • required + maxlength="200" / maxlength="5000"      (client-side validation parity)
       • old() repopulation on every field, @selected() logic incl. request('booking_id')
       • @error blocks for subject, category, body
       • $categories (->value / ->label()) and $bookings loops, display_unknown(), display_sep_dot()
       • data-testid="customer-support-ticket-form"
     Only the presentation moves to JetPK: jp-form-group / jp-label / jp-input / jp-select /
     jp-textarea / jp-field-error / jp-btn. --}}
@extends(client_layout('customer-account', 'customer'))

@section('title', 'Create support ticket')

@section('account_title', 'Create support ticket')
@section('account_subtitle', 'Tell us what you need help with.')

@section('account_actions')
    <a href="{{ route('customer.support.tickets.index') }}" class="jp-btn jp-btn--ghost">Back</a>
@endsection

@section('account_content')
    <x-dashboard.breadcrumbs :items="[
        ['label' => 'Dashboard', 'href' => client_route('customer.dashboard')],
        ['label' => 'Support tickets', 'href' => client_route('customer.support.tickets.index')],
        ['label' => 'New ticket'],
    ]" />

    <x-jp.card class="jp-portal__panel">
        <form method="post" action="{{ route('customer.support.tickets.store') }}" data-testid="customer-support-ticket-form" class="jp-form">
            @csrf

            <div class="jp-form-group">
                <label class="jp-label" for="subject">Subject</label>
                <input
                    type="text"
                    name="subject"
                    id="subject"
                    class="jp-input @error('subject') jp-input--invalid @enderror"
                    value="{{ old('subject') }}"
                    required
                    maxlength="200"
                    @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror
                >
                @error('subject')<p class="jp-field-error" id="subject-error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-form-grid jp-form-grid--2">
                <div class="jp-form-group">
                    <label class="jp-label" for="category">Category</label>
                    <select
                        name="category"
                        id="category"
                        class="jp-select @error('category') jp-input--invalid @enderror"
                        required
                        @error('category') aria-invalid="true" aria-describedby="category-error" @enderror
                    >
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="jp-field-error" id="category-error">{{ $message }}</p>@enderror
                </div>

                <div class="jp-form-group">
                    <label class="jp-label" for="booking_id">Related booking (optional)</label>
                    <select name="booking_id" id="booking_id" class="jp-select">
                        <option value="">{{ display_unknown(null, '-- None --') }}</option>
                        @foreach ($bookings as $booking)
                            <option value="{{ $booking->id }}" @selected((string) old('booking_id', request('booking_id')) === (string) $booking->id)>{{ e($booking->booking_reference ?? 'Booking #'.$booking->id) }}{{ display_sep_dot() }}{{ e($booking->route ?? '') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="jp-form-group">
                <label class="jp-label" for="body">Message</label>
                <textarea
                    name="body"
                    id="body"
                    rows="6"
                    class="jp-textarea @error('body') jp-input--invalid @enderror"
                    required
                    maxlength="5000"
                    @error('body') aria-invalid="true" aria-describedby="body-error" @enderror
                >{{ old('body') }}</textarea>
                @error('body')<p class="jp-field-error" id="body-error">{{ $message }}</p>@enderror
            </div>

            <div class="jp-form-actions">
                <button type="submit" class="jp-btn jp-btn--primary">Submit ticket</button>
                <a href="{{ route('customer.support.tickets.index') }}" class="jp-btn jp-btn--ghost">Cancel</a>
            </div>
        </form>
    </x-jp.card>
@endsection
