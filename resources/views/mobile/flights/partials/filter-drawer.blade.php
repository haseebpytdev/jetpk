<div class="ota-mobile-filter-backdrop" data-mobile-filter-backdrop aria-hidden="true"></div>

<aside
    class="ota-mobile-filter-drawer"
    data-mobile-filter-drawer
    aria-labelledby="ota-mobile-filter-title"
    aria-hidden="true"
    role="dialog"
>
    <header class="ota-mobile-filter-drawer__head">
        <button type="button" class="ota-mobile-filter-drawer__close" data-mobile-filter-close aria-label="Close filters">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true">
                <path d="M18.3 5.71a1 1 0 00-1.41 0L12 10.59 7.11 5.7A1 1 0 105.7 7.11L10.59 12 5.7 16.89a1 1 0 101.41 1.41L12 13.41l4.89 4.89a1 1 0 001.41-1.41L13.41 12l4.89-4.89a1 1 0 000-1.4z"/>
            </svg>
        </button>
        <h2 id="ota-mobile-filter-title" class="ota-mobile-filter-drawer__title">Filters</h2>
        <button type="button" class="ota-mobile-filter-drawer__reset" data-mobile-filter-reset>Reset</button>
    </header>

    <div class="ota-mobile-filter-drawer__body">
        <section class="ota-mobile-filter-section" data-filter-section="airline">
            <div class="ota-mobile-filter-section__head">
                <h3 class="ota-mobile-filter-section__title">Airlines</h3>
                <button type="button" class="ota-mobile-filter-section__link" data-mobile-filter-select-all="airline">Select all</button>
            </div>
            <div class="ota-mobile-filter-chips" data-mobile-filter-airlines></div>
        </section>

        <section class="ota-mobile-filter-section" data-filter-section="stops">
            <h3 class="ota-mobile-filter-section__title">Stops</h3>
            <div class="ota-mobile-filter-chips" data-mobile-filter-stops>
                <button type="button" class="ota-mobile-filter-chip is-active" data-filter-key="stops" data-filter-value="">All</button>
                <button type="button" class="ota-mobile-filter-chip" data-filter-key="stops" data-filter-value="direct">Direct</button>
                <button type="button" class="ota-mobile-filter-chip" data-filter-key="stops" data-filter-value="1_stop">1 Stop</button>
                <button type="button" class="ota-mobile-filter-chip" data-filter-key="stops" data-filter-value="2_plus">2+ Stops</button>
            </div>
        </section>

        <section class="ota-mobile-filter-section">
            <h3 class="ota-mobile-filter-section__title">Baggage</h3>
            <div class="ota-mobile-filter-chips" data-mobile-filter-baggage>
                <button type="button" class="ota-mobile-filter-chip is-active" data-filter-key="baggage" data-filter-value="">All</button>
            </div>
        </section>

        <section class="ota-mobile-filter-section">
            <h3 class="ota-mobile-filter-section__title">Departure time</h3>
            <div class="ota-mobile-filter-chips" data-mobile-filter-departure>
                <button type="button" class="ota-mobile-filter-chip is-active" data-filter-key="departure_window" data-filter-value="">All</button>
            </div>
        </section>

        <section class="ota-mobile-filter-section">
            <h3 class="ota-mobile-filter-section__title">Arrival time</h3>
            <div class="ota-mobile-filter-chips" data-mobile-filter-arrival>
                <button type="button" class="ota-mobile-filter-chip is-active" data-filter-key="arrival_window" data-filter-value="">All</button>
            </div>
        </section>

        <section class="ota-mobile-filter-section">
            <h3 class="ota-mobile-filter-section__title">Fare family</h3>
            <div class="ota-mobile-filter-chips" data-mobile-filter-fare-family>
                <button type="button" class="ota-mobile-filter-chip is-active" data-filter-key="fare_family" data-filter-value="">All</button>
            </div>
        </section>

        <section class="ota-mobile-filter-section">
            <h3 class="ota-mobile-filter-section__title">Refundability</h3>
            <div class="ota-mobile-filter-chips" data-mobile-filter-refundable>
                <button type="button" class="ota-mobile-filter-chip is-active" data-filter-key="refundable" data-filter-value="">All</button>
                <button type="button" class="ota-mobile-filter-chip" data-filter-key="refundable" data-filter-value="1">Refundable</button>
                <button type="button" class="ota-mobile-filter-chip" data-filter-key="refundable" data-filter-value="0">Non-refundable</button>
            </div>
        </section>
    </div>

    <footer class="ota-mobile-filter-drawer__foot">
        <button type="button" class="ota-mobile-filter-drawer__apply" data-mobile-filter-apply>Apply Filters</button>
        <p class="ota-mobile-filter-drawer__count" data-mobile-filter-result-count>Loading flights…</p>
    </footer>
</aside>

<div
    class="ota-mobile-sort-sheet"
    data-mobile-sort-sheet
    aria-labelledby="ota-mobile-sort-title"
    aria-hidden="true"
    role="dialog"
>
    <div class="ota-mobile-sort-sheet__backdrop" data-mobile-sort-close></div>
    <div class="ota-mobile-sort-sheet__panel">
        <header class="ota-mobile-sort-sheet__head">
            <h2 id="ota-mobile-sort-title" class="ota-mobile-sort-sheet__title">Sort by</h2>
            <button type="button" class="ota-mobile-sort-sheet__close" data-mobile-sort-close aria-label="Close sort">×</button>
        </header>
        <div class="ota-mobile-sort-sheet__options" data-mobile-sort-options>
            <button type="button" class="ota-mobile-sort-option is-active" data-sort-value="recommended">Cheapest</button>
            <button type="button" class="ota-mobile-sort-option" data-sort-value="fastest">Fastest</button>
            <button type="button" class="ota-mobile-sort-option" data-sort-value="earliest_departure">Earliest departure</button>
            <button type="button" class="ota-mobile-sort-option" data-sort-value="latest_departure">Latest departure</button>
            <button type="button" class="ota-mobile-sort-option" data-sort-value="airline_az">Airline A–Z</button>
        </div>
    </div>
</div>
