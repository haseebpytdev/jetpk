@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('why_book')) {
        return;
    }

    $whyEyebrow = $jpHome->field('why_book.eyebrow', 'The JetPakistan difference');
    $whyTitle = $jpHome->field('why_book.title', 'Built for how Pakistan books.');
    $whySubtitle = $jpHome->field('why_book.subtitle', 'Four things we got obsessive about, so your next trip starts without friction.');
    $whyCards = collect($jpHome->field('why_book.cards', [
        ['num' => '01 · Pricing', 'title' => 'True PKR pricing', 'text' => 'Fares converted and locked in rupees — no FX shock between search and payment.'],
        ['num' => '02 · Speed', 'title' => 'Seconds to ticket', 'text' => 'Confirmed PNR and e-ticket delivered to email and WhatsApp the moment you pay.'],
        ['num' => '03 · Choice', 'title' => '400+ airlines', 'text' => 'Local carriers and global alliances side by side, ranked by real total cost.'],
        ['num' => '04 · Trust', 'title' => 'Licensed & secure', 'text' => 'IATA accredited, PCAA licensed, PCI-DSS payments. Your booking is protected end to end.'],
    ]))
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
