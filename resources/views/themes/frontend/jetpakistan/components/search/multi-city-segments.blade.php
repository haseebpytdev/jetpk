@php
    $multiFrom = old('multi_from', $multiFrom ?? []);
    $multiTo = old('multi_to', $multiTo ?? []);
    $multiFromDisplay = old('multi_from_display', $multiFromDisplay ?? []);
    $multiToDisplay = old('multi_to_display', $multiToDisplay ?? []);
    $multiDepart = old('multi_depart', $multiDepart ?? []);
    if (! is_array($multiFrom)) { $multiFrom = []; }
    if (! is_array($multiTo)) { $multiTo = []; }
    if (! is_array($multiFromDisplay)) { $multiFromDisplay = []; }
    if (! is_array($multiToDisplay)) { $multiToDisplay = []; }
    if (! is_array($multiDepart)) { $multiDepart = []; }
    $segmentCount = max(2, count($multiFrom), count($multiTo), count($multiDepart));
@endphp

<div class="jp-multi-wrap" data-jp-multi-fields @if(($defaultTripType ?? 'round_trip') !== 'multi_city') hidden @endif>
    <div class="jp-multi-segments" data-jp-multi-rows>
        @for ($i = 0; $i < $segmentCount; $i++)
            @php
                $segIndex = $i + 1;
                $segId = $widgetId.'-seg-'.$segIndex;
                $fromCode = $multiFrom[$i] ?? '';
                $toCode = $multiTo[$i] ?? '';
                $fromDisplay = $multiFromDisplay[$i] ?? $fromCode;
                $toDisplay = $multiToDisplay[$i] ?? $toCode;
                $departVal = $multiDepart[$i] ?? '';
            @endphp
            <div class="jp-multi-segment" data-jp-multi-segment data-segment-index="{{ $segIndex }}">
                <div class="jp-multi-segment-head">
                    <span class="jp-multi-segment-badge mono">Segment {{ $segIndex }}</span>
                    @if ($segIndex > 2)
                        <button type="button" class="jp-multi-remove" data-jp-multi-remove aria-label="Remove segment {{ $segIndex }}">Remove</button>
                    @endif
                </div>
                <div class="fields jp-multi-fields-row">
                    @include('themes.frontend.jetpakistan.components.search.airport-field', [
                        'id' => $segId.'-from',
                        'label' => 'From',
                        'displayName' => 'multi_from_display[]',
                        'hiddenName' => 'multi_from[]',
                        'displayValue' => $fromDisplay,
                        'codeValue' => $fromCode,
                        'role' => 'multi_from',
                    ])
                    @include('themes.frontend.jetpakistan.components.search.airport-field', [
                        'id' => $segId.'-to',
                        'label' => 'To',
                        'displayName' => 'multi_to_display[]',
                        'hiddenName' => 'multi_to[]',
                        'displayValue' => $toDisplay,
                        'codeValue' => $toCode,
                        'role' => 'multi_to',
                    ])
                    @include('themes.frontend.jetpakistan.components.search.date-field', [
                        'id' => $segId.'-depart',
                        'label' => 'Depart',
                        'name' => 'multi_depart[]',
                        'value' => $departVal,
                        'min' => $minDate,
                        'role' => 'multi_depart',
                        'extraClass' => 'dep',
                    ])
                </div>
            </div>
        @endfor
    </div>
    <div class="jp-multi-actions">
        <button type="button" class="btn btn-ghost jp-multi-add" data-jp-multi-add>Add flight</button>
    </div>
</div>

<template id="{{ $widgetId }}-multi-segment-tpl">
    <div class="jp-multi-segment" data-jp-multi-segment data-segment-index="__INDEX__">
        <div class="jp-multi-segment-head">
            <span class="jp-multi-segment-badge mono">Segment __INDEX__</span>
            <button type="button" class="jp-multi-remove" data-jp-multi-remove aria-label="Remove segment __INDEX__">Remove</button>
        </div>
        <div class="fields jp-multi-fields-row">
            <div class="field jp-airport-field" data-jp-airport-field>
                <label>From</label>
                <div class="jp-field-value-row">
                    <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <input type="text" name="multi_from_display[]" class="jp-airport-display" data-jp-airport-display="multi_from" data-jp-airport-input autocomplete="off" placeholder="City or airport" role="combobox" aria-autocomplete="list" aria-expanded="false">
                </div>
                <input type="hidden" name="multi_from[]" data-jp-airport-code="multi_from" value="">
                <div class="jp-airport-suggest" role="listbox" aria-label="From suggestions" hidden></div>
            </div>
            <div class="field jp-airport-field" data-jp-airport-field>
                <label>To</label>
                <div class="jp-field-value-row">
                    <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <input type="text" name="multi_to_display[]" class="jp-airport-display" data-jp-airport-display="multi_to" data-jp-airport-input autocomplete="off" placeholder="City or airport" role="combobox" aria-autocomplete="list" aria-expanded="false">
                </div>
                <input type="hidden" name="multi_to[]" data-jp-airport-code="multi_to" value="">
                <div class="jp-airport-suggest" role="listbox" aria-label="To suggestions" hidden></div>
            </div>
            <div class="field dep jp-date-field" data-jp-date-field data-jp-date-role="multi_depart" data-jp-date-placeholder="Departure">
                <label>Depart</label>
                <div class="jp-field-value-row">
                    <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2.5"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
                    <button type="button" class="jp-date-trigger" data-jp-date-trigger aria-haspopup="dialog" aria-expanded="false">
                        <span class="jp-date-display is-placeholder" data-jp-date-display>Departure</span>
                    </button>
                    <input type="hidden" name="multi_depart[]" value="" data-jp-date-value data-jp-date-min="{{ $minDate }}">
                </div>
            </div>
        </div>
    </div>
</template>
