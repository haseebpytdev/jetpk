@php
    $tiles = $groupHomepageTiles ?? collect();
    $tileCount = $tiles->count();
@endphp
<section class="ota-home-groups-preview" id="groups">
    <div class="ota-home-groups-preview__inner">
        <header class="ota-home-groups-preview__header">
            <h2 class="ota-home-groups-preview__title">Group Departures</h2>
            <p class="ota-home-groups-preview__subtitle">Fixed-date group seats with transparent pricing. Browse by route or category.</p>
        </header>

        @if ($tileCount > 0)
            <div class="ota-home-groups-preview__grid" role="list" aria-label="Group categories">
                @foreach ($tiles as $tile)
                    @php
                        $url = $tile['url'] ?? client_route('group-ticketing.search');
                        $imageUrl = $tile['image_url'] ?? null;
                        $title = $tile['title'] ?? 'Groups';
                    @endphp
                    <a href="{{ $url }}" class="ota-home-groups-preview__card" role="listitem">
                        <div class="ota-home-groups-preview__image-wrap">
                            @if ($imageUrl)
                                <img class="ota-home-groups-preview__image" src="{{ e($imageUrl) }}" alt="" loading="lazy">
                            @else
                                <span class="ota-home-groups-preview__image-placeholder" aria-hidden="true">
                                    <i class="fa fa-users"></i>
                                </span>
                            @endif
                        </div>
                        <div class="ota-home-groups-preview__body">
                            <h3 class="ota-home-groups-preview__card-title">{{ e($title) }}</h3>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="ota-home-groups-preview__cta">
            <a href="{{ client_route('group-ticketing.search') }}" class="public-btn public-btn-primary ota-home-groups-preview__cta-link">
                <span>View all groups</span>
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</section>
