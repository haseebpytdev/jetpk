@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    $renderer = app(ClientPageRenderer::class);
    $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
    $steps = $renderer->enabledItems($content['steps']['items'] ?? []);
    $benefits = is_array($content['benefits'] ?? null) ? $content['benefits'] : [];
    $benefitItems = $renderer->enabledItems($benefits['items'] ?? []);
    $faqItems = $renderer->enabledItems($content['faq']['items'] ?? []);
    $cta = is_array($content['cta'] ?? null) ? $content['cta'] : [];
@endphp

@section('title', $seo['title'] ?? 'Agent partnership')

@section('content')
<section class="jp-page jp-page--agent-landing" aria-labelledby="jp-agent-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-agent-heading"
      :kicker="(string) ($hero['kicker'] ?? '')"
      :title="(string) ($hero['title'] ?? '')"
      :description="(string) ($hero['description'] ?? '')"
    />

    @if (($hero['cta_text'] ?? '') !== '')
      <div class="jp-page-actions">
        <a href="{{ $renderer->resolveDestination((string) ($hero['cta_url'] ?? 'route:agent.register.form')) }}" class="jp-btn jp-btn--primary">{{ $hero['cta_text'] }}</a>
        <a href="{{ client_route('support') }}" class="jp-btn jp-btn--secondary">Partner support</a>
      </div>
    @endif

    @if ($steps !== [])
      <div class="jp-page-grid jp-page-grid--3 jp-agent-steps">
        @foreach ($steps as $step)
          <x-jp.card :title="(string) ($step['title'] ?? '')">
            <p>{{ $step['body'] ?? '' }}</p>
          </x-jp.card>
        @endforeach
      </div>
    @endif

    <div class="jp-page-grid jp-page-grid--2">
      @if ($benefitItems !== [])
        <x-jp.card :title="(string) ($benefits['title'] ?? 'Benefits for agents')">
          <ul class="jp-list">
            @foreach ($benefitItems as $item)
              <li>{{ $item['text'] ?? '' }}</li>
            @endforeach
          </ul>
        </x-jp.card>
      @endif

      @if ($faqItems !== [])
        <x-jp.card title="FAQ">
          @foreach ($faqItems as $faq)
            <details class="jp-faq" id="{{ $faq['id'] ?? '' }}">
              <summary>{{ $faq['question'] ?? '' }}</summary>
              <p>{{ $faq['answer'] ?? '' }}</p>
            </details>
          @endforeach
        </x-jp.card>
      @endif
    </div>

    @if (($cta['title'] ?? '') !== '')
      <x-jp.card :title="(string) $cta['title']">
        @if (($cta['body'] ?? '') !== '')
          <p class="jp-card__lead">{{ $cta['body'] }}</p>
        @endif
        <div class="jp-page-actions">
          @if (($cta['primary_label'] ?? '') !== '')
            <a href="{{ $renderer->resolveDestination((string) ($cta['primary_url'] ?? '')) }}" class="jp-btn jp-btn--primary">{{ $cta['primary_label'] }}</a>
          @endif
          @if (($cta['secondary_label'] ?? '') !== '')
            <a href="{{ $renderer->resolveDestination((string) ($cta['secondary_url'] ?? '')) }}" class="jp-btn jp-btn--secondary">{{ $cta['secondary_label'] }}</a>
          @endif
        </div>
      </x-jp.card>
    @endif
  </div>
</section>
@endsection
