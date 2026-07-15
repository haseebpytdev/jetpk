@extends(client_layout('mobile-app', 'mobile'))

@section('title', 'Create support ticket')

@section('mobile_app_title', 'New ticket')

@section('mobile_app_back')
    <a href="{{ route('customer.support.tickets.index') }}" class="ota-mobile-app__back-btn" aria-label="Back to support tickets">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    </a>
@endsection

@section('content')
    <div class="ota-mobile-customer" data-testid="ota-mobile-customer-support-create">
        <div class="ota-mobile-customer__card ota-mobile-customer__form-card">
            <h1 class="ota-mobile-customer__page-title">Create support ticket</h1>
            <p class="ota-mobile-customer__note">Tell us what you need help with.</p>

            <form method="post" action="{{ route('customer.support.tickets.store') }}" class="ota-mobile-customer__form" data-testid="customer-support-ticket-form">
                @csrf

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="subject">Subject</label>
                    <input type="text" name="subject" id="subject" class="ota-mobile-customer__input{{ $errors->has('subject') ? ' is-invalid' : '' }}" value="{{ old('subject') }}" required maxlength="200">
                    @error('subject')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="category">Category</label>
                    <select name="category" id="category" class="ota-mobile-customer__input{{ $errors->has('category') ? ' is-invalid' : '' }}" required>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->value }}" @selected(old('category') === $cat->value)>{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="booking_id">Related booking (optional)</label>
                    <select name="booking_id" id="booking_id" class="ota-mobile-customer__input">
                        <option value="">— None —</option>
                        @foreach ($bookings as $booking)
                            <option value="{{ $booking->id }}" @selected((string) old('booking_id', request('booking_id')) === (string) $booking->id)>
                                {{ e($booking->booking_reference ?? 'Booking #'.$booking->id) }} — {{ e($booking->route ?? '') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="ota-mobile-customer__field">
                    <label class="ota-mobile-customer__label" for="body">Message</label>
                    <textarea name="body" id="body" rows="6" class="ota-mobile-customer__input{{ $errors->has('body') ? ' is-invalid' : '' }}" required maxlength="5000">{{ old('body') }}</textarea>
                    @error('body')<p class="ota-mobile-customer__error">{{ $message }}</p>@enderror
                </div>

                <button type="submit" class="ota-mobile-customer__btn ota-mobile-customer__btn--primary ota-mobile-customer__btn--block">Submit ticket</button>
                <a href="{{ route('customer.support.tickets.index') }}" class="ota-mobile-customer__btn ota-mobile-customer__btn--secondary ota-mobile-customer__btn--block">Cancel</a>
            </form>
        </div>
    </div>
@endsection
