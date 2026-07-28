@extends('themes.frontend.jetpakistan.layouts.frontend')

@php
    use App\Services\Client\ClientPageRenderer;
    /** @var \App\Models\ClientPage $page */
    /** @var array<string, mixed> $content */
    /** @var array<string, mixed> $seo */
    $renderer = app(ClientPageRenderer::class);
    $sections = $renderer->enabledItems($content['sections']['items'] ?? $content['sections'] ?? []);
@endphp

@section('title', $seo['title'] ?? $page->public_title)

@section('content')
<section class="jp-page jp-page--content" aria-labelledby="jp-content-heading">
  <div class="wrap jp-page-wrap">
    <x-jp.page-hero
      id="jp-content-heading"
      :title="$page->public_title"
      :description="(string) ($content['identity']['subtitle'] ?? '')"
    />

    @foreach ($sections as $section)
      @include('themes.frontend.jetpakistan.sections.cms.'.($section['type'] ?? 'rich_text'), ['section' => $section, 'renderer' => $renderer])
    @endforeach
  </div>
</section>
@endsection
