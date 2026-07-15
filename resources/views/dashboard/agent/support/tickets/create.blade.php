@extends(client_layout('agent-portal', 'agent'))
@section('title', 'New support ticket')
@section('account_title', 'New support ticket')
@section('account_subtitle', 'Describe your issue and we will get back to you by email.')
@section('account_actions')
    <a href="{{ route('agent.support.tickets.index') }}" class="ota-account-btn ota-account-btn--secondary">Back to tickets</a>
@endsection
@section('account_content')
    <div class="ota-account-card ota-account-form-card"><div class="ota-account-card__body">
        <form method="post" action="{{ route('agent.support.tickets.store') }}" class="vstack gap-3" data-testid="agent-support-ticket-form">
            @csrf
            <div><label class="form-label" for="subject">Subject</label>
            <input type="text" name="subject" id="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required maxlength="200">
            @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div><label class="form-label" for="category">Category</label>
            <select name="category" id="category" class="form-select" required>
                @foreach($categories as $cat)<option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>@endforeach
            </select></div>
            <div><label class="form-label" for="booking_id">Linked booking (optional)</label>
            <select name="booking_id" id="booking_id" class="form-select"><option value="">— None —</option>
                @foreach($bookings as $booking)<option value="{{ $booking->id }}" @selected((string) old('booking_id') === (string) $booking->id)>{{ e($booking->booking_reference ?? 'Booking #'.$booking->id) }} — {{ e($booking->route ?? '') }}</option>@endforeach
            </select></div>
            <div><label class="form-label" for="body">Message</label>
            <textarea name="body" id="body" rows="6" class="form-control" required maxlength="5000">{{ old('body') }}</textarea></div>
            <div class="ota-account-form-actions"><button type="submit" class="ota-account-btn ota-account-btn--primary">Submit ticket</button>
            <a href="{{ route('agent.support.tickets.index') }}" class="ota-account-btn ota-account-btn--secondary">Cancel</a></div>
        </form>
    </div></div>
@endsection