{{-- Loading skeleton; live cards are rendered client-side from flights.results.data --}}
@php
    $skeletonRoundTrip = $skeletonRoundTrip ?? false;
@endphp
@if ($skeletonRoundTrip)
    <article class="ota-mobile-result-card ota-mobile-result-card--roundtrip ota-mobile-result-card--skeleton" data-flight-card aria-hidden="true">
        <div class="ota-mobile-result-card__head">
            <span class="ota-mobile-result-card__logo ota-mobile-result-card__shimmer"></span>
            <span class="ota-mobile-result-card__airline ota-mobile-result-card__shimmer"></span>
        </div>
        <div class="ota-mobile-result-card__legs">
            @foreach (['Outbound', 'Return'] as $legLabel)
                <div class="ota-mobile-result-leg">
                    <span class="ota-mobile-result-leg__label">{{ $legLabel }}</span>
                    <div class="ota-mobile-result-leg__route">
                        <div class="ota-mobile-result-leg__point">
                            <span class="ota-mobile-result-leg__code ota-mobile-result-card__shimmer"></span>
                            <span class="ota-mobile-result-leg__time ota-mobile-result-card__shimmer"></span>
                        </div>
                        <div class="ota-mobile-result-leg-meta">
                            <span class="ota-mobile-result-leg-meta__duration ota-mobile-result-card__shimmer"></span>
                            <span class="ota-mobile-result-leg-meta__stops ota-mobile-result-card__shimmer"></span>
                        </div>
                        <div class="ota-mobile-result-leg__point ota-mobile-result-leg__point--arr">
                            <span class="ota-mobile-result-leg__code ota-mobile-result-card__shimmer"></span>
                            <span class="ota-mobile-result-leg__time ota-mobile-result-card__shimmer"></span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="ota-mobile-result-card__footer">
            <span class="ota-mobile-result-card__price ota-mobile-result-card__shimmer"></span>
            <span class="ota-mobile-result-card__select ota-mobile-result-card__shimmer"></span>
        </div>
    </article>
@else
    <article class="ota-mobile-result-card ota-mobile-result-card--skeleton" data-flight-card aria-hidden="true">
        <div class="ota-mobile-result-card__head">
            <span class="ota-mobile-result-card__logo ota-mobile-result-card__shimmer"></span>
            <span class="ota-mobile-result-card__airline ota-mobile-result-card__shimmer"></span>
        </div>
        <div class="ota-mobile-result-card__route">
            <div class="ota-mobile-result-card__point">
                <span class="ota-mobile-result-card__time ota-mobile-result-card__shimmer"></span>
                <span class="ota-mobile-result-card__code ota-mobile-result-card__shimmer"></span>
            </div>
            <div class="ota-mobile-result-card__mid">
                <span class="ota-mobile-result-card__duration ota-mobile-result-card__shimmer"></span>
                <span class="ota-mobile-result-card__stops ota-mobile-result-card__shimmer"></span>
            </div>
            <div class="ota-mobile-result-card__point ota-mobile-result-card__point--arr">
                <span class="ota-mobile-result-card__time ota-mobile-result-card__shimmer"></span>
                <span class="ota-mobile-result-card__code ota-mobile-result-card__shimmer"></span>
            </div>
        </div>
        <div class="ota-mobile-result-card__footer">
            <span class="ota-mobile-result-card__price ota-mobile-result-card__shimmer"></span>
            <span class="ota-mobile-result-card__select ota-mobile-result-card__shimmer"></span>
        </div>
    </article>
@endif
