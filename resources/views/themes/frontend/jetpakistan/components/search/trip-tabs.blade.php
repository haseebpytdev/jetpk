<div class="search-top">
    <div class="seg tabs" id="segTabs" role="tablist" aria-label="Search product">
        <span class="pill-ind" aria-hidden="true"></span>
        <button type="button" class="on" data-jp-product="flights" role="tab" aria-selected="true">
            <x-jp.icon name="plane" class="icon" />
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
