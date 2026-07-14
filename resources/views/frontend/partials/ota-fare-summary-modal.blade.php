<div id="ota-fare-breakdown-modal" class="ota-fare-breakdown-modal ota-fare-summary-modal" hidden aria-modal="true" role="dialog" aria-labelledby="ota-fare-breakdown-title">
    <div class="ota-fare-summary-modal__backdrop ota-fare-breakdown-modal__backdrop" data-close-fare-breakdown tabindex="-1" aria-hidden="true"></div>
    <div class="ota-fare-summary-modal__panel ota-fare-breakdown-modal__panel" role="document">
        <div class="ota-fare-summary-modal__head">
            <div class="ota-fare-summary-modal__head-copy">
                <h4 id="ota-fare-breakdown-title" class="ota-fare-summary-modal__title ota-fare-breakdown-modal__title">{{ __('Fare Summary') }}</h4>
                <p class="ota-fare-summary-modal__subtitle" data-fare-summary-subtitle>{{ __('Review fare, baggage, and policy before booking.') }}</p>
                <p class="ota-fare-summary-modal__route" data-fare-summary-route hidden></p>
            </div>
            <button type="button" class="ota-fare-summary-modal__close" data-close-fare-breakdown aria-label="{{ __('Close fare summary') }}">&times;</button>
        </div>
        <div class="ota-fare-summary-modal__tabs-wrap" data-fare-summary-tabs>
            <div class="ota-fare-summary-modal__tabs ota-flight-detail-tabs" role="tablist" aria-label="{{ __('Fare summary sections') }}">
                <button type="button" class="ota-fare-summary-modal__tab ota-flight-detail-tab is-active" role="tab" data-fare-summary-tab="baggage" aria-selected="true" aria-controls="ota-fare-summary-panel-baggage" id="ota-fare-summary-tab-baggage">{{ __('Baggage Policy') }}</button>
                <button type="button" class="ota-fare-summary-modal__tab ota-flight-detail-tab" role="tab" data-fare-summary-tab="policy" aria-selected="false" aria-controls="ota-fare-summary-panel-policy" id="ota-fare-summary-tab-policy" tabindex="-1">{{ __('Fare Policy') }}</button>
                <button type="button" class="ota-fare-summary-modal__tab ota-flight-detail-tab" role="tab" data-fare-summary-tab="details" aria-selected="false" aria-controls="ota-fare-summary-panel-details" id="ota-fare-summary-tab-details" tabindex="-1">{{ __('Fare Details') }}</button>
            </div>
        </div>
        <div class="ota-fare-summary-modal__body">
            <div id="ota-fare-summary-panel-baggage" class="ota-fare-summary-modal__panel-content" data-fare-summary-panel="baggage" role="tabpanel" aria-labelledby="ota-fare-summary-tab-baggage" data-fare-summary-baggage></div>
            <div id="ota-fare-summary-panel-policy" class="ota-fare-summary-modal__panel-content" data-fare-summary-panel="policy" role="tabpanel" aria-labelledby="ota-fare-summary-tab-policy" data-fare-summary-policy hidden></div>
            <div id="ota-fare-summary-panel-details" class="ota-fare-summary-modal__panel-content" data-fare-summary-panel="details" role="tabpanel" aria-labelledby="ota-fare-summary-tab-details" hidden>
                <div class="ota-fare-breakdown-modal__rows" data-fare-breakdown-rows></div>
            </div>
        </div>
        <div class="ota-fare-summary-modal__footer">
            <div class="ota-fare-summary-modal__total-wrap">
                <span class="ota-fare-summary-modal__total-label">{{ __('Grand total') }}</span>
                <span class="ota-fare-summary-modal__total-note">{{ __('Inclusive of all taxes & fees') }}</span>
                <span class="ota-fare-summary-modal__total-value" data-fare-summary-total>—</span>
                <p class="ota-fare-summary-modal__disclaimer ota-fare-breakdown-modal__disclaimer">{{ __('Fares may change until booking is confirmed.') }}</p>
            </div>
            <div class="ota-fare-summary-modal__actions ota-fare-breakdown-modal__actions">
                <button type="button" class="btn btn-default" data-close-fare-breakdown>{{ __('Close') }}</button>
                <button type="button" class="btn btn-primary ota-select-primary" data-fare-summary-select hidden>{{ __('Select') }}</button>
            </div>
        </div>
    </div>
</div>
