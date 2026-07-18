@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('destinations')) {
        return;
    }

    $defaults = $jpHome->defaults();
    $eyebrow = $jpHome->field('destinations.eyebrow', '');
    $title = $jpHome->field('destinations.title', '');
    $subtitle = $jpHome->field('destinations.subtitle', '');
    $ctaText = $jpHome->field('destinations.cta_text', '');
    $ctaUrl = $jpHome->field('destinations.cta_url', client_route('home').'#jp-flight-search');
    $dests = $jpHome->destinationsForDisplay();
    $carousel = count($dests) > 4;
@endphp
@if ($dests !== [])
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="section-head reveal">
      <div>
        @if ($eyebrow !== '')<span class="eyebrow">{{ $eyebrow }}</span>@endif
        @if ($title !== '')<h2>{{ $title }}</h2>@endif
        @if ($subtitle !== '')<p>{{ $subtitle }}</p>@endif
      </div>
      @if ($ctaText !== '' && $ctaUrl !== '')
      <a href="{{ $ctaUrl }}" class="link">{{ $ctaText }} <x-jp.icon name="arrow-right" /></a>
      @endif
    </div>
    <div class="grid-dest stagger @if($carousel) grid-dest--carousel @endif" @if($carousel) data-jp-card-carousel @endif>
      @foreach($dests as $d)
        <x-jp.dest-card
          :variant="$d['variant'] ?? 'd-default'"
          :country="$d['country'] ?? ($d['code'] ?? '')"
          :city="$d['title'] ?? ($d['city'] ?? '')"
          :price="$d['price'] ?? null"
          :priceLabel="$d['price_label'] ?? null"
          :image="$d['image'] ?? null"
          :href="$d['href'] ?? null"
          :alt="$d['alt'] ?? ($d['title'] ?? '')"
          :badge="$d['badge'] ?? null"
          :text="$d['text'] ?? null"
        />
      @endforeach
    </div>
  </div>
</section>
@endif
