@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    /** @var array<string, mixed> $contact */
    $renderer = app(ClientPageRenderer::class);
@endphp

@section('title', $seo['title'] ?? 'About us')

@section('content')
<section class="jp-page jp-page--about" aria-labelledby="jp-about-heading">
  <div class="wrap jp-page-wrap">
    @if (($hero = $content['hero'] ?? null) && is_array($hero))
      <x-jp.page-hero
        id="jp-about-heading"
        :kicker="(string) ($hero['kicker'] ?? '')"
        :title="(string) ($hero['title'] ?? '')"
        :description="(string) ($hero['description'] ?? '')"
      />
    @endif

    @php $featureCards = $renderer->enabledItems($content['feature_cards']['items'] ?? []); @endphp
    @if (($content['feature_cards']['enabled'] ?? '1') !== '0' && $featureCards !== [])
      <div class="jp-feature-strip jp-page-grid jp-page-grid--3">
        @foreach ($featureCards as $card)
          <x-jp.card :title="(string) ($card['title'] ?? '')">
            <p>{{ $card['body'] ?? '' }}</p>
          </x-jp.card>
        @endforeach
      </div>
    @endif

    @php $gridItems = $renderer->enabledItems($content['content_grid']['items'] ?? []); @endphp
    @if (($content['content_grid']['enabled'] ?? '1') !== '0' && $gridItems !== [])
      <div class="jp-page-grid jp-page-grid--2">
        @foreach ($gridItems as $item)
          <x-jp.card :title="(string) ($item['title'] ?? '')">
            @if (($item['format'] ?? '') === 'list')
              <ul class="jp-list">
                @foreach (preg_split('/\r\n|\r|\n/', (string) ($item['body'] ?? '')) ?: [] as $line)
                  @if (trim($line) !== '')
                    <li>{{ trim($line) }}</li>
                  @endif
                @endforeach
              </ul>
            @else
              @foreach (preg_split('/\r\n\r\n|\n\n/', (string) ($item['body'] ?? '')) ?: [] as $paragraph)
                @if (trim($paragraph) !== '')
                  <p>{{ trim($paragraph) }}</p>
                @endif
              @endforeach
            @endif
          </x-jp.card>
        @endforeach

        <x-jp.card title="Contact JetPakistan">
          <ul class="jp-list jp-list--contact">
            @if ($contact['phone'] !== '')
              <li><strong>Phone:</strong> <a href="tel:{{ $contact['phone_e164'] ?: preg_replace('/\D+/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a></li>
            @endif
            @if ($contact['email'] !== '')
              <li><strong>Email:</strong> <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a></li>
            @endif
            @if ($contact['website'] !== '')
              <li><strong>Website:</strong> <a href="{{ $contact['website'] }}" target="_blank" rel="noopener">{{ parse_url($contact['website'], PHP_URL_HOST) ?: $contact['website'] }}</a></li>
            @endif
            @if ($contact['office'] !== '')
              <li><strong>Office:</strong> {{ $contact['office'] }}</li>
            @endif
          </ul>
        </x-jp.card>
      </div>
    @endif

    @php $cta = is_array($content['cta'] ?? null) ? $content['cta'] : []; @endphp
    @if (($cta['primary_label'] ?? '') !== '' || ($cta['secondary_label'] ?? '') !== '')
      <div class="jp-page-actions">
        @if (($cta['secondary_label'] ?? '') !== '')
          <a href="{{ $renderer->resolveDestination((string) ($cta['secondary_url'] ?? '')) }}" class="jp-btn jp-btn--secondary">{{ $cta['secondary_label'] }}</a>
        @endif
        @if (($cta['primary_label'] ?? '') !== '')
          <a href="{{ $renderer->resolveDestination((string) ($cta['primary_url'] ?? '')) }}" class="jp-btn jp-btn--primary">{{ $cta['primary_label'] }}</a>
        @endif
      </div>
    @endif
  </div>
</section>
@endsection
