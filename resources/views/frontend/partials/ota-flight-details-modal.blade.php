<div id="ota-flight-details-modal" class="ota-flight-details-modal" hidden aria-modal="true" role="dialog" aria-labelledby="ota-flight-details-title">
    <div class="ota-flight-details-modal__backdrop" data-close-flight-details tabindex="-1" aria-hidden="true"></div>
    <div class="ota-flight-details-modal__panel" role="document">
        <div class="ota-flight-details-modal__head">
            <div class="ota-flight-details-modal__head-copy">
                <h4 id="ota-flight-details-title" class="ota-flight-details-modal__title">{{ __('Flight Details') }}</h4>
                <p class="ota-flight-details-modal__subtitle" data-flight-details-subtitle>{{ __('Review your itinerary, connections, and segment details.') }}</p>
                <p class="ota-flight-details-modal__route" data-flight-details-route hidden></p>
            </div>
            <button type="button" class="ota-flight-details-modal__close" data-close-flight-details aria-label="{{ __('Close flight details') }}">&times;</button>
        </div>
        <div class="ota-flight-details-modal__body" data-flight-details-body></div>
        <div class="ota-flight-details-modal__footer">
            <button type="button" class="btn btn-default" data-close-flight-details>{{ __('Close') }}</button>
        </div>
    </div>
</div>
