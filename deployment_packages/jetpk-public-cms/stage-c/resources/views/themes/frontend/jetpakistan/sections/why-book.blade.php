@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('why_book')) {
        return;
    }

    $whyEyebrow = $jpHome->field('why_book.eyebrow', '');
    $whyTitle = $jpHome->field('why_book.title', '');
    $whySubtitle = $jpHome->field('why_book.subtitle', '');
    $whyCards = collect($jpHome->field('why_book.cards', []))
        ->filter(static fn ($card) => is_array($card) && ($card['enabled'] ?? '1') !== '0')
        ->values()
        ->all();
@endphp
@if ($whyCards !== [])
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="section-head reveal">
      <div>
        <span class="eyebrow">{{ $whyEyebrow }}</span>
        <h2>{{ $whyTitle }}</h2>
      </div>
      <p>{{ $whySubtitle }}</p>
    </div>
    <div class="grid-4 stagger">
      @foreach (collect($whyCards)->take(4) as $card)
        <x-jp.bene-card
          :num="data_get($card, 'num', '')"
          :title="data_get($card, 'title', '')"
          :text="data_get($card, 'text', '')"
        />
      @endforeach
    </div>
  </div>
</section>
@endif
