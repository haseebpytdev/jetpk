@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    $renderer = app(ClientPageRenderer::class);
    $hero = is_array($content['hero'] ?? null) ? $content['hero'] : [];
    $categories = $renderer->enabledItems($content['categories']['items'] ?? []);
@endphp

@section('title', $seo['title'] ?? 'FAQ')

@section('content')
<section class="jp-page jp-page--faq" aria-labelledby="jp-faq-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-faq-heading"
      :kicker="(string) ($hero['kicker'] ?? '')"
      :title="(string) ($hero['title'] ?? '')"
      :description="(string) ($hero['description'] ?? '')"
    />

    @foreach ($categories as $category)
      <section class="jp-faq-category" aria-labelledby="faq-cat-{{ $category['id'] ?? $loop->index }}">
        <h2 id="faq-cat-{{ $category['id'] ?? $loop->index }}">{{ $category['title'] ?? '' }}</h2>
        @foreach ($renderer->enabledItems($category['questions'] ?? []) as $question)
          <details class="jp-faq" id="{{ $question['id'] ?? '' }}">
            <summary>{{ $question['question'] ?? '' }}</summary>
            <p>{{ $question['answer'] ?? '' }}</p>
          </details>
        @endforeach
      </section>
    @endforeach

    @php $cta = is_array($content['cta'] ?? null) ? $content['cta'] : []; @endphp
    @if (($cta['label'] ?? '') !== '')
      <div class="jp-page-actions">
        <a href="{{ $renderer->resolveDestination((string) ($cta['url'] ?? '')) }}" class="jp-btn jp-btn--primary">{{ $cta['label'] }}</a>
      </div>
    @endif
  </div>
</section>
@endsection
