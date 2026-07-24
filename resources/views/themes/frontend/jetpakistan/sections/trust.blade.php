@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('trust')) {
        return;
    }

    $defaults = $jpHome->defaults();
    $eyebrow = $jpHome->field('trust.eyebrow', data_get($defaults, 'trust.eyebrow', ''));
    $title = $jpHome->field('trust.title', data_get($defaults, 'trust.title', ''));
    $subtitle = $jpHome->field('trust.subtitle', data_get($defaults, 'trust.subtitle', ''));
    $cards = collect($jpHome->trustCardsWithFallback())
        ->filter(static fn ($card) => is_array($card) && ($card['enabled'] ?? '1') !== '0' && trim((string) ($card['title'] ?? '')) !== '')
        ->values()
        ->all();
@endphp
@if ($cards !== [])
<section class="section">
  <div class="wrap">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">{{ $eyebrow }}</span>
        @if ($title !== '')<h2>{!! nl2br(e($title)) !!}</h2>@endif
      </div>
      @if ($subtitle !== '')<p>{{ $subtitle }}</p>@endif
    </div>
    <div class="grid-3 trust-grid stagger">
      @foreach ($cards as $card)
        <x-jp.trust-card
          :icon="$card['icon'] ?? 'check-square'"
          :title="$card['title'] ?? ''"
          :text="$card['text'] ?? ''"
        />
      @endforeach
    </div>
  </div>
</section>
@endif
