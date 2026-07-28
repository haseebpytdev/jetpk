@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('featured_deals')) {
        return;
    }

    $eyebrow = $jpHome->field('featured_deals.eyebrow', '');
    $title = $jpHome->field('featured_deals.title', '');
    $subtitle = $jpHome->field('featured_deals.subtitle', '');
    $ctaText = $jpHome->field('featured_deals.cta_text', '');
    $ctaUrl = $jpHome->field('featured_deals.cta_url', '');
    $cardCount = max(1, min(6, (int) $jpHome->field('featured_deals.card_count', 3)));
    $fares = $jpHome->featuredDealsForDisplay();
@endphp
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
    <div class="grid-fares stagger">
      @foreach(collect($fares)->take($cardCount) as $f)
        <x-jp.fare-card :airline="$f['airline']" :from="$f['from']" :to="$f['to']" :depart="$f['depart']" :arrive="$f['arrive']" :dur="$f['dur']" :stops="$f['stops']" :price="$f['price']" />
      @endforeach
    </div>
  </div>
</section>
