@php
    use App\Support\Client\ClientPageKeys;
    use App\Support\Client\JetpkHomepageSectionData;
    use App\Support\Homepage\JetpkHeroLcpPresenter;

  /** @var JetpkHomepageSectionData $jpHome */
    $jpHome = app(JetpkHomepageSectionData::class);
    $defaults = $jpHome->defaults();

    $jpHeroBg = $jpHome->assetUrl('hero_background') ?? client_branding()->heroImageUrl();
    $jpHeroManifest = null;
    if ($jpHeroBg && function_exists('current_client_profile')) {
        $jpProfile = current_client_profile();
        if ($jpProfile !== null) {
            $jpHeroManifest = app(JetpkHeroLcpPresenter::class)->manifestForHeroUrl($jpHeroBg, $jpProfile->id);
        }
    }
    $jpHeroLcp = $jpHeroBg ? app(JetpkHeroLcpPresenter::class)->present($jpHeroBg, $jpHeroManifest) : null;
    $heroEyebrow = $jpHome->field('hero.eyebrow', data_get($defaults, 'hero.eyebrow', ''));
    $heroHeadline = $jpHome->field('hero.headline', data_get($defaults, 'hero.headline', ''));
    $heroHighlight = $jpHome->field('hero.headline_highlight', data_get($defaults, 'hero.headline_highlight', ''));
    $heroSubtitle = $jpHome->field('hero.subtitle', data_get($defaults, 'hero.subtitle', ''));
    $trustChips = $jpHome->field('trust_chips', data_get($defaults, 'trust_chips', []));
    $searchVisible = $jpHome->field('hero.search_visible', data_get($defaults, 'hero.search_visible', '1'));
    $heroCtaPrimaryText = $jpHome->field('hero.cta_primary_text', data_get($defaults, 'hero.cta_primary_text', ''));
    $heroCtaPrimaryUrl = $jpHome->field('hero.cta_primary_url', data_get($defaults, 'hero.cta_primary_url', ''));
    $heroCtaSecondaryText = $jpHome->field('hero.cta_secondary_text', data_get($defaults, 'hero.cta_secondary_text', ''));
    $heroCtaSecondaryUrl = $jpHome->field('hero.cta_secondary_url', data_get($defaults, 'hero.cta_secondary_url', ''));
@endphp
@if ($jpHeroLcp)
  @push('head')
    @foreach ($jpHeroLcp['preloads'] as $preload)
      <link
        rel="preload"
        as="image"
        href="{{ $preload['href'] }}"
        @if (! empty($preload['type'])) type="{{ $preload['type'] }}" @endif
        media="{{ $preload['media'] }}"
        @if ($loop->first) fetchpriority="high" @endif
      >
    @endforeach
  @endpush
@endif
<section class="hero @if($jpHeroLcp) hero--has-image @endif" id="top">
  @if ($jpHeroLcp)
    <div class="hero-media" aria-hidden="true">
      <picture>
        @foreach ($jpHeroLcp['sources'] as $source)
          <source type="{{ $source['type'] }}" media="{{ $source['media'] }}" srcset="{{ $source['srcset'] }}">
        @endforeach
        <img
          class="hero-img"
          src="{{ $jpHeroLcp['fallback_url'] }}"
          alt="{{ $jpHeroLcp['alt'] }}"
          width="{{ $jpHeroLcp['width'] }}"
          height="{{ $jpHeroLcp['height'] }}"
          loading="eager"
          fetchpriority="high"
          decoding="async"
        >
      </picture>
    </div>
    <div class="hero-readability" aria-hidden="true"></div>
  @else
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
  @endif

  <div class="wrap hero-inner">
    @if ($heroEyebrow !== '')
      <span class="eyebrow hseq hseq-1">{{ $heroEyebrow }}</span>
    @endif
    <h1 class="hseq hseq-2">{{ $heroHeadline }}@if($heroHighlight !== '')<br><span class="gold">{{ $heroHighlight }}</span>@endif</h1>
    @if ($heroSubtitle !== '')
      <p class="sub hseq hseq-3">{{ $heroSubtitle }}</p>
    @endif

    @php
      $heroCtaPrimaryHref = $heroCtaPrimaryUrl !== '' ? (str_starts_with($heroCtaPrimaryUrl, 'http') ? $heroCtaPrimaryUrl : client_url($heroCtaPrimaryUrl)) : null;
      $heroCtaSecondaryHref = $heroCtaSecondaryUrl !== '' ? (str_starts_with($heroCtaSecondaryUrl, 'http') ? $heroCtaSecondaryUrl : client_url($heroCtaSecondaryUrl)) : null;
    @endphp
    @if (($heroCtaPrimaryText !== '' && $heroCtaPrimaryHref) || ($heroCtaSecondaryText !== '' && $heroCtaSecondaryHref))
      <div class="hero-ctas hseq hseq-4">
        @if ($heroCtaPrimaryText !== '' && $heroCtaPrimaryHref)
          <a href="{{ $heroCtaPrimaryHref }}" class="btn btn-primary btn-lg hero-cta-primary">{{ $heroCtaPrimaryText }}</a>
        @endif
        @if ($heroCtaSecondaryText !== '' && $heroCtaSecondaryHref)
          <a href="{{ $heroCtaSecondaryHref }}" class="btn btn-ghost btn-lg hero-cta-secondary">{{ $heroCtaSecondaryText }}</a>
        @endif
      </div>
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
