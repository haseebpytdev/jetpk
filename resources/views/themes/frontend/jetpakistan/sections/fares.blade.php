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

    $dynamicFares = is_array($dynamicFeaturedFares ?? null) ? $dynamicFeaturedFares : [];
    $fares = [];

    if ($dynamicFares !== []) {
        foreach ($dynamicFares as $fare) {
            $snap = is_array($fare->snapshot ?? null) ? $fare->snapshot : [];
            $fares[] = [
                'airline' => trim((string) ($snap['airline_name'] ?? '')),
                'airline_code' => trim((string) ($snap['airline_code'] ?? '')),
                'from' => strtoupper(trim((string) ($snap['origin_code'] ?? $fare->origin_code ?? ''))),
                'to' => strtoupper(trim((string) ($snap['destination_code'] ?? $fare->destination_code ?? ''))),
                'depart' => trim((string) ($snap['departure_time'] ?? $snap['departure_date'] ?? '')),
                'arrive' => trim((string) ($snap['arrival_time'] ?? '')),
                'dur' => trim((string) ($snap['duration_label'] ?? '')),
                'stops' => (int) ($snap['stops'] ?? 0),
                'price' => (int) ($snap['price_total'] ?? 0),
            ];
        }
    }

    if ($fares === []) {
        $fares = $jpHome->featuredDealsForDisplay();
    }

    if ($fares === []) {
        $fares = [
            ['airline' => '', 'from' => 'LHE', 'to' => 'DXB', 'depart' => '', 'arrive' => '', 'dur' => '', 'stops' => 0, 'price' => 0],
            ['airline' => '', 'from' => 'KHI', 'to' => 'JED', 'depart' => '', 'arrive' => '', 'dur' => '', 'stops' => 0, 'price' => 0],
            ['airline' => '', 'from' => 'ISB', 'to' => 'IST', 'depart' => '', 'arrive' => '', 'dur' => '', 'stops' => 0, 'price' => 0],
        ];
    }
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
        @php
          $airlineLabel = trim((string) ($f['airline'] ?? ''));
          $airlineCode = trim((string) ($f['airline_code'] ?? ''));
          if ($airlineLabel !== '' && $airlineCode !== '') {
              $airlineLabel = $airlineLabel.' ('.$airlineCode.')';
          } elseif ($airlineLabel === '' && $airlineCode !== '') {
              $airlineLabel = $airlineCode;
          }
        @endphp
        <x-jp.fare-card :airline="$airlineLabel" :from="$f['from']" :to="$f['to']" :depart="$f['depart']" :arrive="$f['arrive']" :dur="$f['dur']" :stops="$f['stops']" :price="$f['price']" />
      @endforeach
    </div>
  </div>
</section>
