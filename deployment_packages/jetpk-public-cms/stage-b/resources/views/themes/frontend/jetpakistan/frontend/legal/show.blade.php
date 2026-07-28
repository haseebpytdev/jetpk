@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    use App\Support\Client\ClientSafeHtmlSanitizer;
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    /** @var string $legalType */
    $legal = is_array($content['legal'] ?? null) ? $content['legal'] : [];
    $sections = app(ClientPageRenderer::class)->enabledItems($legal['sections'] ?? []);
@endphp

@section('title', $seo['title'] ?? ($legal['title'] ?? 'Legal'))

@section('content')
<section class="jp-page jp-page--legal jp-page--legal-{{ $legalType }}" aria-labelledby="jp-legal-heading">
  <div class="wrap jp-page-wrap">
    <header class="jp-legal-header">
      <h1 id="jp-legal-heading">{{ $legal['title'] ?? '' }}</h1>
      @if (($legal['effective_date'] ?? '') !== '' || ($legal['last_updated'] ?? '') !== '')
        <p class="jp-muted jp-legal-dates">
          @if (($legal['effective_date'] ?? '') !== '')
            <span>Effective: {{ $legal['effective_date'] }}</span>
          @endif
          @if (($legal['last_updated'] ?? '') !== '')
            <span>Last updated: {{ $legal['last_updated'] }}</span>
          @endif
        </p>
      @endif
      @if (($legal['intro'] ?? '') !== '')
        <p class="jp-legal-intro">{{ $legal['intro'] }}</p>
      @endif
    </header>

    <article class="jp-legal-body jp-print-friendly">
      @foreach ($sections as $section)
        <section id="{{ $section['id'] ?? '' }}" class="jp-legal-section">
          @if (($section['heading'] ?? '') !== '')
            <h2>{{ $section['heading'] }}</h2>
          @endif
          {!! nl2br(e(ClientSafeHtmlSanitizer::sanitize((string) ($section['body'] ?? '')))) !!}
        </section>
      @endforeach
    </article>
  </div>
</section>
@endsection
