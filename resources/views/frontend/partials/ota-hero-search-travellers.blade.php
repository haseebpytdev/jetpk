@php
    $paxId = $paxId ?? 'pax';
    $paxSummaryId = $paxSummaryId ?? null;
    $infantSelectMax = min(9, max(0, (int) ($adultsVal ?? 1)));
@endphp
<details class="ota-hero-search-field ota-hero-search-field--pax" data-pax-picker>
    <summary class="ota-hero-search-pax__trigger">
        <span class="ota-hero-search-pax__label">Travellers &amp; cabin</span>
        <span class="ota-hero-search-pax__value" @if($paxSummaryId) id="{{ $paxSummaryId }}" @endif data-pax-summary>{{ $paxSummary }}</span>
    </summary>
    <div class="ota-hero-search-pax__panel">
        <div class="ota-hero-search-pax__group">
            <label class="ota-hero-search-label" for="{{ $widgetId }}-cabin-{{ $paxId }}">Cabin</label>
            <select class="ota-hero-search-control ota-hero-search-control--select" id="{{ $widgetId }}-cabin-{{ $paxId }}" name="cabin" data-pax-input>
                <option value="economy" @selected($cabinVal === 'economy')>Economy</option>
                <option value="premium_economy" @selected($cabinVal === 'premium_economy')>Premium Economy</option>
                <option value="business" @selected($cabinVal === 'business')>Business</option>
                <option value="first" @selected($cabinVal === 'first')>First</option>
            </select>
        </div>
        <div class="ota-hero-search-pax__grid">
            <div class="ota-hero-search-pax__group">
                <label class="ota-hero-search-label" for="{{ $widgetId }}-adults-{{ $paxId }}">Adults</label>
                <select class="ota-hero-search-control ota-hero-search-control--select" id="{{ $widgetId }}-adults-{{ $paxId }}" name="adults" data-pax-input>
                    @for ($a = 1; $a <= 9; $a++)
                        <option value="{{ $a }}" @selected($adultsVal === $a)>{{ $a }}</option>
                    @endfor
                </select>
            </div>
            <div class="ota-hero-search-pax__group">
                <label class="ota-hero-search-label" for="{{ $widgetId }}-children-{{ $paxId }}">Children</label>
                <select class="ota-hero-search-control ota-hero-search-control--select" id="{{ $widgetId }}-children-{{ $paxId }}" name="children" data-pax-input>
                    @for ($c = 0; $c <= 8; $c++)
                        <option value="{{ $c }}" @selected($childrenVal === $c)>{{ $c }}</option>
                    @endfor
                </select>
            </div>
            <div class="ota-hero-search-pax__group">
                <label class="ota-hero-search-label" for="{{ $widgetId }}-infants-{{ $paxId }}">Infants</label>
                <select class="ota-hero-search-control ota-hero-search-control--select" id="{{ $widgetId }}-infants-{{ $paxId }}" name="infants" data-pax-input data-infants-select>
                    @for ($i = 0; $i <= $infantSelectMax; $i++)
                        <option value="{{ $i }}" @selected($infantsVal === $i)>{{ $i }}</option>
                    @endfor
                </select>
            </div>
        </div>
    </div>
</details>
