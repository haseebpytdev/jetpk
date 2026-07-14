@php
    $tiles = collect($groupHomepageTiles ?? []);
    $tileCount = $tiles->count();
    $preserveHref = static function (?string $href): string {
        $href = trim((string) $href);
        if ($href === '') {
            return ui_preserve_route('group-ticketing.search');
        }
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            $parsed = parse_url($href);
            $path = $parsed['path'] ?? '/';
            $query = isset($parsed['query']) && $parsed['query'] !== '' ? '?'.$parsed['query'] : '';

            return url(ui_preserve_url($path.$query));
        }

        return ui_preserve_url($href);
    };
@endphp
@if (\App\Support\Platform\PlatformModuleGate::visible('public_umrah_groups'))
<section class="ota-v2-section ota-v2-group-section" id="groups" data-testid="v2-group-departures">
    <div class="ota-v2-page-wrap">
        <header class="ota-v2-section__head">
            <p class="ota-v2-label">Group travel</p>
            <h2 class="ota-v2-section-title">Group departures</h2>
            <p class="ota-v2-section__desc">Fixed-date group seats with transparent pricing. Browse by route or category.</p>
        </header>

        @if ($tileCount > 0)
            <div class="ota-v2-group-grid" role="list" aria-label="Group categories">
                @foreach ($tiles as $tile)
                    @php
                        $url = $preserveHref($tile['url'] ?? null);
                        $imageUrl = $tile['image_url'] ?? null;
                        $title = $tile['title'] ?? 'Groups';
                    @endphp
                    <a href="{{ $url }}" class="ota-v2-group-card" role="listitem">
                        <span class="ota-v2-group-card__media">
                            @if ($imageUrl)
                                <img src="{{ e($imageUrl) }}" alt="" class="ota-v2-group-card__image" loading="lazy">
                            @else
                                <span class="ota-v2-group-card__placeholder" aria-hidden="true"><i class="fa fa-users"></i></span>
                            @endif
                        </span>
                        <span class="ota-v2-group-card__body">
                            <span class="ota-v2-group-card__title">{{ e($title) }}</span>
                            <span class="ota-v2-group-card__link">View packages <i class="fa fa-arrow-right" aria-hidden="true"></i></span>
                        </span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="ota-v2-empty-state">
                <p class="ota-v2-empty-state__title">No group departures yet</p>
                <p class="ota-v2-empty-state__text">New group inventory will appear here. Browse all groups to see what is available now.</p>
                <a href="{{ ui_preserve_route('group-ticketing.search') }}" class="ota-v2-btn ota-v2-btn--primary">Browse groups</a>
            </div>
        @endif

        <p class="ota-v2-section__cta">
            <a href="{{ ui_preserve_route('group-ticketing.search') }}" class="ota-v2-btn ota-v2-btn--ghost">View all groups</a>
        </p>
    </div>
</section>
@endif
