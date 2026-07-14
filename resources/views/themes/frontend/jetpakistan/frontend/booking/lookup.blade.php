@extends('themes.frontend.jetpakistan.layouts.frontend')

@section('title', 'Lookup your booking')

@section('content')
<section class="jp-page jp-page--lookup" aria-labelledby="jp-lookup-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-lookup-heading"
      kicker="Manage booking"
      title="Lookup your booking"
      description="Access your booking request, documents, payment status, and travel updates securely. We verify your details before showing sensitive information."
    />

    <div class="jp-page-grid jp-page-grid--2">
      <div class="jp-page-stack">
        <x-jp.card title="How lookup works">
          <p>Enter the booking reference from your confirmation together with the email address used when you booked. If everything matches our records, we send you a secure link to view your trip.</p>
          <p class="jp-field-hint">Your reference usually looks like a short code or alphanumeric ID from your confirmation email or receipt.</p>
        </x-jp.card>

        <x-jp.card title="What you need">
          <ul class="jp-list">
            <li><strong>Booking reference</strong> — from your confirmation</li>
            <li><strong>Email address</strong> — must match what we have on file</li>
          </ul>
        </x-jp.card>
      </div>

      <x-jp.card title="Enter your details">
        @if ($errors->has('lookup'))
          <x-jp.alert variant="danger">{{ $errors->first('lookup') }}</x-jp.alert>
        @endif

        <form method="post" action="{{ route('lookup-booking.submit') }}" class="jp-form">
          @csrf

          <x-jp.form-group label="Booking reference" for="booking_reference" :error="$errors->first('booking_reference')">
            <input id="booking_reference" class="jp-input" name="booking_reference" value="{{ old('booking_reference') }}" autocomplete="off" required>
          </x-jp.form-group>

          <x-jp.form-group label="Email address" for="lookup_email" :error="$errors->first('email')">
            <input id="lookup_email" class="jp-input" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required>
          </x-jp.form-group>

          <p class="jp-field-hint">For privacy, access links are only sent when your details match the booking.</p>

          <x-turnstile />
          <x-jp.button type="submit" variant="primary" block>Lookup booking</x-jp.button>
        </form>

        <nav class="jp-form-foot" aria-label="Booking help">
          <a href="{{ client_route('support') }}">Need help? Contact support</a>
          <a href="{{ client_route('home') }}#jp-flight-search">Back to flight search</a>
        </nav>
      </x-jp.card>
    </div>
  </div>
</section>
@endsection
