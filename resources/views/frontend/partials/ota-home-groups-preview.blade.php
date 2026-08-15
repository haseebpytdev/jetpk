@php
    use App\Support\Platform\PlatformModuleGate;

    $tiles = collect($groupHomepageTiles ?? []);
    $tileCount = $tiles->count();
    $isSlider = $tileCount >= 5;
@endphp
@if (PlatformModuleGate::visible('public_umrah_groups') && $tileCount > 0)
<section class="ota-home-groups-preview" id="groups" data-testid="home-group-departures">
    <div class="ota-home-groups-preview__inner">
        <header class="ota-home-groups-preview__header">
            <h2 class="ota-home-groups-preview__title">Group departures</h2>
            <p class="ota-home-groups-preview__subtitle">Fixed-date group seats with transparent pricing. Browse by route or category.</p>
        </header>

        <div
            class="ota-home-groups-preview__grid{{ $isSlider ? ' is-slider' : '' }}"
            role="list"
            aria-label="Group categories"
            data-slider="{{ $isSlider ? '1' : '0' }}"
        >
            @if ($isSlider)
                <button type="button" class="ota-home-groups-preview-carousel__btn--prev" aria-label="Previous group categories">‹</button>
                <button type="button" class="ota-home-groups-preview-carousel__btn--next" aria-label="Next group categories">›</button>
            @endif

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
                            <span class="ota-home-groups-preview__image-placeholder ota-home-groups-preview-tile__placeholder" aria-hidden="true">
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

        <div class="ota-home-groups-preview__cta">
            <a href="{{ client_route('group-ticketing.search') }}" class="public-btn public-btn-primary ota-home-groups-preview__cta-link">
                <span>View all groups</span>
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>
</section>
@endif
