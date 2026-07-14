@extends(client_layout('customer-account', 'customer'))

@section('title', 'Create support ticket')

@section('account_title', 'Create support ticket')
@section('account_subtitle', 'Tell us what you need help with.')

@section('account_actions')
    <a href="{{ route('customer.support.tickets.index') }}" class="ota-account-btn ota-account-btn--secondary">Back</a>
@endsection

@section('account_content')
    <div class="ota-account-card ota-account-form-card">
        <div class="ota-account-card__body">
            <form method="post" action="{{ route('customer.support.tickets.store') }}" data-testid="customer-support-ticket-form">
                @csrf
                <div class="ota-account-form-grid">
                    <div class="ota-account-field">
                        <label class="form-label" for="subject">Subject</label>
                        <input type="text" name="subject" id="subject" class="form-control @error('subject') is-invalid @enderror" value="{{ old('subject') }}" required maxlength="200">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="ota-account-form-grid ota-account-form-grid--2">
                        <div class="ota-account-field">
                            <label class="form-label" for="category">Category</label>
                            <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>
                                @endforeach
                            </select>
                            @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="ota-account-field">
                            <label class="form-label" for="booking_id">Related booking (optional)</label>
                            <select name="booking_id" id="booking_id" class="form-select">
                                <option value="">{{ display_unknown(null, '-- None --') }}</option>
                                @foreach ($bookings as $booking)
                                    <option value="{{ $booking->id }}" @selected((string) old('booking_id', request('booking_id')) === (string) $booking->id)>{{ e($booking->booking_reference ?? 'Booking #'.$booking->id) }}{{ display_sep_dot() }}{{ e($booking->route ?? '') }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ota-account-field">
                        <label class="form-label" for="body">Message</label>
                        <textarea name="body" id="body" rows="6" class="form-control @error('body') is-invalid @enderror" required maxlength="5000">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="ota-account-form-actions">
                    <button type="submit" class="ota-account-btn ota-account-btn--primary">Submit ticket</button>
                    <a href="{{ route('customer.support.tickets.index') }}" class="ota-account-btn ota-account-btn--secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection
