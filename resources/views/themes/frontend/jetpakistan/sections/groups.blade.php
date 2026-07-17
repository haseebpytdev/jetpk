@php
    use App\Support\Client\JetpkHomepageSectionData;

    $jpHome = app(JetpkHomepageSectionData::class);
    if (! $jpHome->isEnabled('group_cards')) {
        return;
    }

    $defaults = $jpHome->defaults();
    $eyebrow = $jpHome->field('group_cards.eyebrow', data_get($defaults, 'group_cards.eyebrow', ''));
    $title = $jpHome->field('group_cards.title', data_get($defaults, 'group_cards.title', ''));
    $subtitle = $jpHome->field('group_cards.subtitle', data_get($defaults, 'group_cards.subtitle', ''));
    $groups = $jpHome->groupCardsWithFallback();

    // JETPK-HOMEPAGE-CMS Task 9: previously read the retired 'groups.cta_url' key
    // and never rendered it at all. Now reads the canonical 'group_cards.*'
    // fields per Task 6's decision, and the link below actually uses them.
    $ctaText = $jpHome->field('group_cards.cta_text', data_get($defaults, 'group_cards.cta_text', 'View all packages'));
    $ctaUrl = $jpHome->field('group_cards.cta_url', data_get($defaults, 'group_cards.cta_url', ''));
    $ctaHref = $ctaUrl !== '' ? (str_starts_with($ctaUrl, 'http') ? $ctaUrl : client_url($ctaUrl)) : client_route('group-ticketing.search');
@endphp
@if ($groups !== [])
<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="section-head reveal">
      <div>
        @if ($eyebrow !== '')<span class="eyebrow">{{ $eyebrow }}</span>@endif
        @if ($title !== '')<h2>{{ $title }}</h2>@endif
        @if ($subtitle !== '')<p>{{ $subtitle }}</p>@endif
      </div>
      @if ($ctaText !== '')
        <a href="{{ $ctaHref }}" class="link">{{ $ctaText }} <x-jp.icon name="arrow-right" /></a>
      @endif
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
