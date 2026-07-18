@php
    $adultsVal = max(1, (int) ($adultsVal ?? 1));
    $childrenVal = max(0, (int) ($childrenVal ?? 0));
    $infantsVal = max(0, (int) ($infantsVal ?? 0));
    $cabinVal = $cabinVal ?? 'economy';
    $cabinLabels = [
        'economy' => 'Economy',
        'premium_economy' => 'Premium Economy',
        'business' => 'Business',
        'first' => 'First',
    ];
    $paxSummary = $adultsVal.' adult'.($adultsVal === 1 ? '' : 's');
    if ($childrenVal > 0) {
        $paxSummary .= ', '.$childrenVal.' child'.($childrenVal === 1 ? '' : 'ren');
    }
    if ($infantsVal > 0) {
        $paxSummary .= ', '.$infantsVal.' infant'.($infantsVal === 1 ? '' : 's');
    }
    $paxSummary .= ' · '.($cabinLabels[$cabinVal] ?? 'Economy');
@endphp

<div class="field jp-pax-field ota-hero-search-field--pax" data-jp-pax-picker data-pax-picker>
    <label id="{{ $widgetId }}-pax-label">Travellers</label>
    <div class="jp-field-value-row">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-8 0v2M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
        <button
            type="button"
            class="jp-pax-trigger ota-hero-search-pax__trigger"
            data-jp-pax-trigger
            aria-expanded="false"
            aria-labelledby="{{ $widgetId }}-pax-label"
            aria-controls="{{ $widgetId }}-pax-panel"
        >
            <span class="jp-pax-summary ota-hero-search-pax__value" data-jp-pax-summary data-pax-summary>{{ $paxSummary }}</span>
        </button>
    </div>
    <p class="jp-pax-inline-error" data-jp-pax-error hidden></p>
    <div class="jp-pax-panel ota-hero-search-pax__panel" id="{{ $widgetId }}-pax-panel" data-jp-pax-panel hidden>
        <div class="jp-pax-row">
            <span class="jp-pax-row-label">Cabin</span>
            <select name="cabin" class="jp-pax-cabin" data-jp-pax-cabin aria-label="Cabin class">
                @foreach ($cabinLabels as $value => $label)
                    <option value="{{ $value }}" @selected($cabinVal === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="jp-pax-row">
            <span class="jp-pax-row-label">Adults</span>
            <div class="jp-pax-stepper" data-jp-pax-stepper="adults" data-min="1" data-max="9">
                <button type="button" class="jp-pax-step" data-jp-pax-dec aria-label="Fewer adults">−</button>
                <span class="jp-pax-count" data-jp-pax-count>{{ $adultsVal }}</span>
                <button type="button" class="jp-pax-step" data-jp-pax-inc aria-label="More adults">+</button>
                <input type="hidden" value="{{ $adultsVal }}" data-jp-pax-input="adults">
            </div>
        </div>
        <div class="jp-pax-row">
            <span class="jp-pax-row-label">Children</span>
            <div class="jp-pax-stepper" data-jp-pax-stepper="children" data-min="0" data-max="8">
                <button type="button" class="jp-pax-step" data-jp-pax-dec aria-label="Fewer children">−</button>
                <span class="jp-pax-count" data-jp-pax-count>{{ $childrenVal }}</span>
                <button type="button" class="jp-pax-step" data-jp-pax-inc aria-label="More children">+</button>
                <input type="hidden" value="{{ $childrenVal }}" data-jp-pax-input="children">
            </div>
        </div>
        <div class="jp-pax-row">
            <span class="jp-pax-row-label">Infants</span>
            <div class="jp-pax-stepper" data-jp-pax-stepper="infants" data-min="0" data-max="{{ $adultsVal }}">
                <button type="button" class="jp-pax-step" data-jp-pax-dec aria-label="Fewer infants">−</button>
                <span class="jp-pax-count" data-jp-pax-count>{{ $infantsVal }}</span>
                <button type="button" class="jp-pax-step" data-jp-pax-inc aria-label="More infants">+</button>
                <input type="hidden" value="{{ $infantsVal }}" data-jp-pax-input="infants">
            </div>
        </div>
        <p class="jp-pax-hint">Max 9 passengers. Infants cannot exceed adults.</p>
        {{-- Playwright/legacy OTA selector contract: functional selects synced with steppers in passengers.js --}}
        <div class="jp-pax-compat-selects" aria-hidden="true">
            <select name="adults" class="jp-pax-compat-select" tabindex="-1" data-jp-pax-compat-select="adults">
                @for ($a = 1; $a <= 9; $a++)
                    <option value="{{ $a }}" @selected($adultsVal === $a)>{{ $a }}</option>
                @endfor
            </select>
            <select name="children" class="jp-pax-compat-select" tabindex="-1" data-jp-pax-compat-select="children">
                @for ($c = 0; $c <= 8; $c++)
                    <option value="{{ $c }}" @selected($childrenVal === $c)>{{ $c }}</option>
                @endfor
            </select>
            <select name="infants" class="jp-pax-compat-select" tabindex="-1" data-jp-pax-compat-select="infants">
                @for ($i = 0; $i <= $adultsVal; $i++)
                    <option value="{{ $i }}" @selected($infantsVal === $i)>{{ $i }}</option>
                @endfor
            </select>
        </div>
    </div>
</div>
