@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    $renderer = app(ClientPageRenderer::class);
    $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
    $instructions = is_array($content['instructions'] ?? null) ? $content['instructions'] : [];
    $cta = is_array($content['cta'] ?? null) ? $content['cta'] : [];
@endphp

@section('title', $seo['title'] ?? 'Lookup your booking')

@section('content')
<section class="jp-page jp-page--lookup" aria-labelledby="jp-lookup-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-lookup-heading"
      :kicker="(string) ($hero['kicker'] ?? '')"
      :title="(string) ($hero['title'] ?? '')"
      :description="(string) ($hero['description'] ?? '')"
    />

    <div class="jp-page-grid jp-page-grid--2">
      <div class="jp-page-stack">
        @if (($instructions['how_it_works'] ?? '') !== '')
          <x-jp.card title="How lookup works">
            <p>{{ $instructions['how_it_works'] }}</p>
            @if (($instructions['hint'] ?? '') !== '')
              <p class="jp-field-hint">{{ $instructions['hint'] }}</p>
            @endif
          </x-jp.card>
        @endif

        @if (($instructions['requirements'] ?? '') !== '')
          <x-jp.card title="What you need">
            <ul class="jp-list">
              @foreach (preg_split('/\r\n|\r|\n/', (string) $instructions['requirements']) ?: [] as $line)
                @if (trim($line) !== '')
                  <li>{{ trim($line) }}</li>
                @endif
              @endforeach
            </ul>
          </x-jp.card>
        @endif
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

          @if (($content['help_text'] ?? '') !== '')
            <p class="jp-field-hint">{{ is_array($content['help_text']) ? ($content['help_text']['text'] ?? '') : $content['help_text'] }}</p>
          @endif

          <x-turnstile />
          <x-jp.button type="submit" variant="primary" block>Lookup booking</x-jp.button>
        </form>

        @if (($cta['label'] ?? '') !== '')
          <p class="jp-form-foot"><a href="{{ $renderer->resolveDestination((string) ($cta['url'] ?? '')) }}">{{ $cta['label'] }}</a></p>
        @endif
      </x-jp.card>
    </div>
  </div>
</section>
@endsection
