@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    /** @var array<string, mixed> $contact */
    $renderer = app(ClientPageRenderer::class);
    $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
    $departments = $renderer->enabledItems($content['department_cards']['items'] ?? []);
    $form = is_array($content['form'] ?? null) ? $content['form'] : [];
@endphp

@section('title', $seo['title'] ?? 'Support & contact')

@section('content')
<section class="jp-page jp-page--support" aria-labelledby="jp-support-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-support-heading"
      :kicker="(string) ($hero['kicker'] ?? '')"
      :title="(string) ($hero['title'] ?? '')"
      :description="(string) ($hero['description'] ?? '')"
    />

    @if (($content['department_cards']['enabled'] ?? '1') !== '0' && $departments !== [])
      <div class="jp-page-grid jp-page-grid--3 jp-support-categories">
        @foreach ($departments as $card)
          <x-jp.card :title="(string) ($card['title'] ?? '')">
            <p>{{ $card['body'] ?? '' }}</p>
          </x-jp.card>
        @endforeach
      </div>
    @endif

    <div class="jp-page-grid jp-page-grid--2">
      <div class="jp-page-stack">
        <x-jp.card title="Contact JetPakistan">
          <ul class="jp-list jp-list--contact">
            @if ($contact['phone'] !== '')
              <li><strong>Phone:</strong> <a href="tel:{{ $contact['phone_e164'] ?: preg_replace('/\D+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>
            @endif
            @if ($contact['whatsapp'] !== '')
              <li><strong>WhatsApp:</strong> <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener">Chat on WhatsApp</a></li>
            @endif
            @if ($contact['email'] !== '')
              <li><strong>Email:</strong> <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
            @endif
            @if ($contact['website'] !== '')
              <li><strong>Website:</strong> <a href="{{ $contact['website'] }}" target="_blank" rel="noopener">{{ parse_url($contact['website'], PHP_URL_HOST) ?: $contact['website'] }}</a></li>
            @endif
          </ul>
        </x-jp.card>

        @php $faqTeaser = is_array($content['faq_teaser'] ?? null) ? $content['faq_teaser'] : []; @endphp
        @if (($faqTeaser['enabled'] ?? '0') === '1' && ($faqTeaser['title'] ?? '') !== '')
          <x-jp.card :title="(string) $faqTeaser['title']">
            <p>{{ $faqTeaser['body'] ?? '' }}</p>
            @if (($faqTeaser['link_label'] ?? '') !== '')
              <p><a href="{{ $renderer->resolveDestination((string) ($faqTeaser['link_url'] ?? 'route:faq')) }}">{{ $faqTeaser['link_label'] }}</a></p>
            @endif
          </x-jp.card>
        @endif
      </div>

      <x-jp.card title="Support request">
        @if (($form['helper_text'] ?? '') !== '')
          <p class="jp-card__lead">{{ $form['helper_text'] }}</p>
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
