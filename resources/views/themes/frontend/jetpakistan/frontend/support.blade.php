@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Support\Client\ClientPageKeys;

    $supportEmail = client_page_content(ClientPageKeys::SUPPORT, 'contact.email', 'ota@jetpakistan.pk');
    $supportPhone = client_page_content(ClientPageKeys::SUPPORT, 'contact.phone', '0311 1222427');
    $supportWhatsapp = client_page_content(ClientPageKeys::SUPPORT, 'contact.whatsapp', '923111222427');
    $supportWebsite = client_page_content(ClientPageKeys::SUPPORT, 'contact.website', 'https://www.jetpakistan.com');
    $supportFormHelper = client_page_content(ClientPageKeys::SUPPORT, 'form.helper_text', 'Tell us what you need and our team will respond shortly.');
    $supportKicker = client_page_content(ClientPageKeys::SUPPORT, 'hero.kicker', 'Support & contact');
    $supportTitle = client_page_content(ClientPageKeys::SUPPORT, 'hero.title', 'Flight booking help, 24/7');
    $supportDescription = client_page_content(ClientPageKeys::SUPPORT, 'hero.description', 'Get assistance with online ticket booking, fare questions, payments, e-tickets, changes, and online check-in.');
@endphp

@section('title', 'Support & contact - JetPakistan')

@section('content')
<section class="jp-page jp-page--support" aria-labelledby="jp-support-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-support-heading"
      :kicker="$supportKicker"
      :title="$supportTitle"
      :description="$supportDescription"
    />

    <div class="jp-page-grid jp-page-grid--3 jp-support-categories">
      <x-jp.card title="Booking assistance">
        <p>New bookings, itinerary changes, and passenger detail updates.</p>
      </x-jp.card>
      <x-jp.card title="Payments & confirmation">
        <p>Payment proof, booking confirmation, and invoice questions.</p>
      </x-jp.card>
      <x-jp.card title="Online check-in">
        <p>Guidance for airline check-in and boarding pass access.</p>
      </x-jp.card>
    </div>

    <div class="jp-page-grid jp-page-grid--2">
      <div class="jp-page-stack">
        <x-jp.card title="Contact JetPakistan">
          <ul class="jp-list jp-list--contact">
            @if ($supportPhone !== '')
              <li><strong>Phone:</strong> <a href="tel:+923111222427">{{ $supportPhone }}</a></li>
            @endif
            @if ($supportWhatsapp !== '')
              <li><strong>WhatsApp:</strong> <a href="https://wa.me/{{ $supportWhatsapp }}" target="_blank" rel="noopener">Chat on WhatsApp</a></li>
            @endif
            @if ($supportEmail !== '')
              <li><strong>Email:</strong> <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a></li>
            @endif
            @if ($supportWebsite !== '')
              <li><strong>Website:</strong> <a href="{{ $supportWebsite }}" target="_blank" rel="noopener">{{ parse_url($supportWebsite, PHP_URL_HOST) ?: $supportWebsite }}</a></li>
            @endif
          </ul>
        </x-jp.card>

        <x-jp.card title="FAQ">
          <details class="jp-faq">
            <summary>How do I book a flight on JetPakistan?</summary>
            <p>Search your route on the homepage, select dates and travellers, then complete checkout with your passenger details.</p>
          </details>
          <details class="jp-faq">
            <summary>Can I book domestic and international flights?</summary>
            <p>Yes. JetPakistan supports both domestic Pakistan routes and international destinations from major Pakistani cities.</p>
          </details>
          <details class="jp-faq">
            <summary>How do I get help after booking?</summary>
            <p>Contact us by phone, WhatsApp, or email with your booking reference. You can also use Manage booking on the website.</p>
          </details>
        </x-jp.card>
      </div>

      <x-jp.card title="Support request">
        @if ($supportFormHelper !== '')
          <p class="jp-card__lead">{{ $supportFormHelper }}</p>
        @endif

        @if ($errors->getBag('supportRequest')->any())
          <x-jp.alert variant="warning">
            <ul class="jp-list jp-list--flush">
              @foreach ($errors->getBag('supportRequest')->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </x-jp.alert>
        @endif

        <form class="jp-form" action="{{ route('support.store') }}" method="post" novalidate>
          @csrf
          <input type="hidden" name="form_type" value="support">
          <div class="jp-visually-hidden" aria-hidden="true">
            <label for="support-website">Website</label>
            <input id="support-website" name="website" type="text" tabindex="-1" autocomplete="off">
          </div>

          @guest
            <x-jp.form-group label="Your name" for="support-name" :error="$errors->getBag('supportRequest')->first('name')">
              <input id="support-name" name="name" class="jp-input @error('name', 'supportRequest') jp-input--invalid @enderror" type="text" value="{{ old('name') }}" autocomplete="name" required>
            </x-jp.form-group>
          @endguest

          <x-jp.form-group label="Email" for="support-email" :error="$errors->getBag('supportRequest')->first('email')">
            <input id="support-email" name="email" class="jp-input @error('email', 'supportRequest') jp-input--invalid @enderror" type="email" value="{{ old('email', auth()->user()?->email) }}" autocomplete="email" required>
          </x-jp.form-group>

          <x-jp.form-group label="Subject" for="support-subject" :error="$errors->getBag('supportRequest')->first('subject')">
            <input id="support-subject" name="subject" class="jp-input @error('subject', 'supportRequest') jp-input--invalid @enderror" type="text" value="{{ old('subject') }}" maxlength="200" required>
          </x-jp.form-group>

          <x-jp.form-group label="Booking reference (optional)" for="support-ref">
            <input id="support-ref" name="booking_reference" class="jp-input" type="text" value="{{ old('booking_reference') }}" autocomplete="off" maxlength="64">
          </x-jp.form-group>

          <x-jp.form-group label="Issue type" for="support-category" :error="$errors->getBag('supportRequest')->first('category')">
            <select id="support-category" name="category" class="jp-select @error('category', 'supportRequest') jp-input--invalid @enderror" required>
              <option value="" disabled @selected(old('category') === null)>Select issue type</option>
              @foreach ($categories as $category)
                <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
              @endforeach
            </select>
          </x-jp.form-group>

          <x-jp.form-group label="Message" for="support-message" :error="$errors->getBag('supportRequest')->first('body')">
            <textarea id="support-message" name="body" class="jp-textarea @error('body', 'supportRequest') jp-input--invalid @enderror" rows="4" maxlength="5000" required>{{ old('body') }}</textarea>
          </x-jp.form-group>

          <x-turnstile />
          <x-jp.button type="submit" variant="primary" block>Submit support request</x-jp.button>
        </form>

        <p class="jp-form-foot">Need status fast? <a href="{{ client_route('booking.lookup') }}">Manage booking</a> · <a href="{{ client_route('agent.register') }}">Travel agent partnership</a>.</p>
      </x-jp.card>
    </div>
  </div>
</section>
@endsection
