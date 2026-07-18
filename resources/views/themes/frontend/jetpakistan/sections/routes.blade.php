@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('routes')) {
        return;
    }

    $defaults = $jpHome->defaults();
    $eyebrow = $jpHome->field('routes.eyebrow', '');
    $title = $jpHome->field('routes.title', '');
    $subtitle = $jpHome->field('routes.subtitle', '');
    $ctaText = $jpHome->field('routes.cta_text', '');
    $ctaUrl = $jpHome->field('routes.cta_url', client_route('home').'#jp-flight-search');
    $routes = $jpHome->routesForDisplay();
    $carousel = count($routes) > 4;
@endphp
@if ($routes !== [])
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="section-head reveal">
      <div>
        @if ($eyebrow !== '')<span class="eyebrow">{{ $eyebrow }}</span>@endif
        @if ($title !== '')<h2>{{ $title }}</h2>@endif
      </div>
      @if ($subtitle !== '')<p>{{ $subtitle }}</p>@endif
      @if ($ctaText !== '' && $ctaUrl !== '')
      <a href="{{ $ctaUrl }}" class="link">{{ $ctaText }} <x-jp.icon name="arrow-right" /></a>
      @endif
    </div>
    <div class="grid-routes stagger @if($carousel) grid-routes--carousel @endif" @if($carousel) data-jp-card-carousel @endif>
      @foreach($routes as $r)
        <x-jp.route-card
          :from="$r['from'] ?? ''"
          :to="$r['to'] ?? ''"
          :airlines="$r['price_label'] ?? ($r['airlines'] ?? \App\Support\Client\JetpkHomepageFareDisplay::neutralAvailabilityLabel())"
          :href="$r['search_url'] ?? null"
          :badge="$r['badge'] ?? null"
        />
      @endforeach
    </div>
  </div>
</section>
@endif
