@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('group_cards')) {
        return;
    }

    $defaults = $jpHome->defaults();
    $eyebrow = $jpHome->field('group_cards.eyebrow', data_get($defaults, 'group_cards.eyebrow', ''));
    $title = $jpHome->field('group_cards.title', data_get($defaults, 'group_cards.title', ''));
    $groups = $jpHome->groupCardsWithFallback();
    $ctaUrl = $jpHome->field('groups.cta_url', data_get($defaults, 'groups.cta_url', '/group-ticketing'));
@endphp
@if ($groups !== [])
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="section-head reveal">
      <div>
        @if ($eyebrow !== '')<span class="eyebrow">{{ $eyebrow }}</span>@endif
        @if ($title !== '')<h2>{{ $title }}</h2>@endif
      </div>
      <a href="{{ client_route('group-ticketing.search') }}" class="link">View all packages <x-jp.icon name="arrow-right" /></a>
    </div>
    <div class="grid-3 stagger">
      @foreach($groups as $g)
        @php
          $image = $g['image'] ?? $jpHome->assetUrl('group_card_'.($loop->index + 1));
          $link = trim((string) ($g['link'] ?? '')) !== '' ? $g['link'] : client_route('group-ticketing.search');
        @endphp
        <x-jp.group-card
          :variant="$g['variant'] ?? 'g-default'"
          :badge="$g['badge'] ?? ''"
          :gold="(bool) ($g['gold'] ?? false)"
          :title="$g['title'] ?? ''"
          :meta="$g['meta'] ?? ''"
          :price="(int) ($g['price'] ?? 0)"
          :image="$image"
          :href="$link"
        />
      @endforeach
    </div>
  </div>
</section>
@endif
