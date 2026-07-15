<div class="search-top">
    <div class="seg tabs" id="segTabs" role="tablist" aria-label="Search product">
        <span class="pill-ind" aria-hidden="true"></span>
        <button type="button" class="on" data-jp-product="flights" role="tab" aria-selected="true">
            <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3.5c-.5-.5-2.5 0-4 1.5L13.5 8.5 5.3 6.7c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 3.8c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z" stroke="none" fill="currentColor"/></svg>
            Flights
        </button>
        @if ($showGroupTab ?? true)
            <button type="button" data-jp-product="groups" role="tab" aria-selected="false">Groups</button>
        @endif
    </div>
    <div class="seg trip" id="segTrip" role="tablist" aria-label="Trip type" data-jp-trip-tabs>
        <span class="pill-ind" aria-hidden="true"></span>
        <button type="button" class="@if(($defaultTripType ?? 'round_trip') === 'round_trip') on @endif" data-jp-trip="round_trip" role="tab" aria-selected="{{ ($defaultTripType ?? 'round_trip') === 'round_trip' ? 'true' : 'false' }}">Return</button>
        <button type="button" class="@if(($defaultTripType ?? 'round_trip') === 'one_way') on @endif" data-jp-trip="one_way" role="tab" aria-selected="{{ ($defaultTripType ?? 'round_trip') === 'one_way' ? 'true' : 'false' }}">One-way</button>
        <button type="button" class="@if(($defaultTripType ?? 'round_trip') === 'multi_city') on @endif" data-jp-trip="multi_city" role="tab" aria-selected="{{ ($defaultTripType ?? 'round_trip') === 'multi_city' ? 'true' : 'false' }}">Multi-city</button>
    </div>
</div>
