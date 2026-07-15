@php
    use App\Support\Client\ClientPageKeys;
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('featured_deals')) {
        return;
    }

    $eyebrow = $jpHome->field('featured_deals.eyebrow', 'Live fares');
    $title = $jpHome->field('featured_deals.title', 'Featured deals, updated hourly.');
    $subtitle = $jpHome->field('featured_deals.subtitle', 'Real round-trip prices pulled live from our airline partners.');
    $ctaText = $jpHome->field('featured_deals.cta_text', '');
    $ctaUrl = $jpHome->field('featured_deals.cta_url', '');
    $cardCount = max(1, min(6, (int) $jpHome->field('featured_deals.card_count', 3)));
    $source = (string) $jpHome->field('featured_deals.source', 'hybrid');

    $fares = [
        ['airline' => 'PIA', 'from' => 'KHI', 'to' => 'DXB', 'depart' => '08:40', 'arrive' => '11:15', 'dur' => '2h 35m', 'stops' => 0, 'price' => 96500],
        ['airline' => 'AirBlue', 'from' => 'LHE', 'to' => 'IST', 'depart' => '14:10', 'arrive' => '20:30', 'dur' => '6h 20m', 'stops' => 1, 'price' => 142300],
        ['airline' => 'AirSial', 'from' => 'ISB', 'to' => 'JED', 'depart' => '23:55', 'arrive' => '02:45', 'dur' => '2h 50m', 'stops' => 0, 'price' => 118900],
    ];
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
    @if ($source !== 'demo')
      <p class="jp-muted" style="margin-top:12px;font-size:13px;">Source: {{ str_replace('_', ' ', $source) }} — configure routes in Homepage featured fares.</p>
    @endif
  </div>
</section>
