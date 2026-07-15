@php
    $directChecked = old('stops', ($defaultStopsDirect ?? false) ? 'direct' : '') === 'direct';
    $nearbyChecked = old('include_nearby', ($defaultIncludeNearby ?? false) ? '1' : '') === '1';
@endphp

<div class="jp-search-action-row search-bottom" data-jp-search-action-row>
    <div class="checks jp-search-checks" data-jp-search-checks>
        <label class="check @if($directChecked) on @endif" data-jp-direct-filter>
            <span class="box" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" fill="none" stroke="currentColor" stroke-width="2.6"/></svg></span>
            <input type="checkbox" name="stops" value="direct" @checked($directChecked) hidden>
            <span>Direct flights only</span>
        </label>
        <label class="check @if($nearbyChecked) on @endif" data-jp-nearby-filter>
            <span class="box" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 12l5 5L20 7" fill="none" stroke="currentColor" stroke-width="2.6"/></svg></span>
            <input type="checkbox" name="include_nearby" value="1" @checked($nearbyChecked) hidden>
            <span>Include nearby airports</span>
        </label>
    </div>
    <div class="jp-chrome-slot jp-submit-slot-action" data-jp-submit-slot-action></div>
</div>
