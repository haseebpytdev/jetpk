@php
    use App\Support\Client\ClientPageKeys;
    use App\Support\Client\JetpkHomepageSectionData;

  /** @var JetpkHomepageSectionData $jpHome */
    $jpHome = app(JetpkHomepageSectionData::class);
    $defaults = $jpHome->defaults();

    $jpHeroBg = $jpHome->assetUrl('hero_background') ?? client_branding()->heroImageUrl();
    $heroEyebrow = $jpHome->field('hero.eyebrow', data_get($defaults, 'hero.eyebrow', ''));
    $heroHeadline = $jpHome->field('hero.headline', data_get($defaults, 'hero.headline', ''));
    $heroHighlight = $jpHome->field('hero.headline_highlight', data_get($defaults, 'hero.headline_highlight', ''));
    $heroSubtitle = $jpHome->field('hero.subtitle', data_get($defaults, 'hero.subtitle', ''));
    $trustChips = $jpHome->field('trust_chips', data_get($defaults, 'trust_chips', []));
    $searchVisible = $jpHome->field('hero.search_visible', data_get($defaults, 'hero.search_visible', '1'));
@endphp
<section class="hero @if($jpHeroBg) hero--has-image @endif" id="top" @if($jpHeroBg) style="--jp-hero-bg-image: url('{{ e($jpHeroBg) }}')" @endif>
  @unless ($jpHeroBg)
    <div class="hero-glow"></div>
    <div class="hero-scene" aria-hidden="true">
      <svg class="topo" viewBox="0 0 400 400" preserveAspectRatio="xMidYMid slice"><circle cx="300" cy="120" r="40"/><circle cx="300" cy="120" r="80"/><circle cx="300" cy="120" r="120"/><circle cx="300" cy="120" r="160"/><circle cx="300" cy="120" r="200"/><circle cx="300" cy="120" r="240"/></svg>
      <svg class="route-net" viewBox="0 0 1200 600" preserveAspectRatio="none"><path d="M-40 420 Q500 120 1240 320"/><path class="a2" d="M-40 520 Q650 200 1240 220"/><path d="M-40 300 Q600 540 1240 460"/></svg>
      <div class="globe-wrap">
        <div class="globe-ring"></div>
        <div class="globe">
          <div class="merid" style="transform:rotateY(0deg)"></div>
          <div class="merid" style="transform:rotateY(30deg)"></div>
          <div class="merid" style="transform:rotateY(60deg)"></div>
          <div class="merid" style="transform:rotateY(90deg)"></div>
          <div class="merid" style="transform:rotateY(120deg)"></div>
          <div class="merid" style="transform:rotateY(150deg)"></div>
        </div>
        <div class="par" style="top:32%;bottom:32%"></div>
        <div class="par" style="top:18%;bottom:46%"></div>
        <div class="par" style="top:46%;bottom:18%"></div>
      </div>
    </div>
    <x-jp.flight-arc />
  @else
    <div class="hero-readability" aria-hidden="true"></div>
  @endunless

  <div class="wrap hero-inner">
    @if ($heroEyebrow !== '')
      <span class="eyebrow hseq hseq-1">{{ $heroEyebrow }}</span>
    @endif
    <h1 class="hseq hseq-2">{{ $heroHeadline }}@if($heroHighlight !== '')<br><span class="gold">{{ $heroHighlight }}</span>@endif</h1>
    @if ($heroSubtitle !== '')
      <p class="sub hseq hseq-3">{{ $heroSubtitle }}</p>
    @endif

    @if (\App\Support\Platform\PlatformModuleGate::visible('public_flight_search') && in_array((string) $searchVisible, ['1', 'true', 'yes', 'on'], true))
      @include('themes.frontend.jetpakistan.components.search.home-flights-search', [
          'context' => 'home',
          'defaultDepart' => $defaultDepart ?? '',
          'defaultOrigin' => $defaultOrigin ?? '',
          'defaultDestination' => $defaultDestination ?? '',
          'defaultOriginDisplay' => $defaultOriginDisplay ?? $defaultOrigin ?? '',
          'defaultDestinationDisplay' => $defaultDestinationDisplay ?? $defaultDestination ?? '',
          'defaultReturnDate' => $defaultReturnDate ?? '',
          'defaultTripType' => $defaultTripType ?? 'round_trip',
          'minDate' => $minDate ?? now()->format('Y-m-d'),
          'groupFacets' => $groupFacets ?? [],
          'groupSearchFilters' => $groupSearchFilters ?? [],
      ])
    @endif

    <div class="chips hseq hseq-5">
      @foreach (collect($trustChips)->take(4) as $chip)
        @php $label = is_array($chip) ? ($chip['label'] ?? '') : (string) $chip; @endphp
        @if ($label !== '')
          <x-jp.chip icon="check" :label="$label" />
        @endif
      @endforeach
    </div>

    <button class="scroll-cue hseq hseq-6" id="scrollCue" aria-label="Scroll to content">
      <span class="mouse"></span>Scroll
    </button>
  </div>
</section>
