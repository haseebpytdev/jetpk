@props([
    'id',
    'departValue' => '',
    'returnValue' => '',
    'min' => '',
    'hidden' => false,
])

@php
    $placeholder = 'Departure - Return';
    $displayValue = '';
    if ($departValue !== '' && $returnValue !== '') {
        try {
            $displayValue = \Illuminate\Support\Carbon::parse($departValue)->format('d M')
                .' - '
                .\Illuminate\Support\Carbon::parse($returnValue)->format('d M');
        } catch (\Throwable) {
            $displayValue = '';
        }
    }
@endphp

<div
    class="field dep ret jp-date-field jp-date-range-field"
    data-jp-date-field
    data-jp-date-role="return_range"
    data-jp-date-placeholder="{{ $placeholder }}"
    @if($hidden) hidden @endif
>
    <label id="{{ $id }}-label">Departure - Return</label>
    <div class="jp-field-value-row">
        <svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2.5"/><path d="M3 10h18M8 2v4M16 2v4"/></svg>
        <button
            type="button"
            class="jp-date-trigger"
            id="{{ $id }}-trigger"
            data-jp-date-trigger
            aria-haspopup="dialog"
            aria-expanded="false"
            aria-labelledby="{{ $id }}-label"
        >
            <span
                class="jp-date-display @if($displayValue === '') is-placeholder @endif"
                data-jp-date-display
            >{{ $displayValue !== '' ? $displayValue : $placeholder }}</span>
        </button>
        <input
            type="hidden"
            id="{{ $id }}-depart"
            name="depart"
            value="{{ $departValue }}"
            data-jp-range-depart
            data-jp-date-value
            @if($min !== '') data-jp-date-min="{{ $min }}" @endif
        >
        <input
            type="hidden"
            id="{{ $id }}-return"
            name="return_date"
            value="{{ $returnValue }}"
            data-jp-range-return
            data-jp-date-value
            @if($departValue !== '') data-jp-date-min="{{ $departValue }}" @elseif($min !== '') data-jp-date-min="{{ $min }}" @endif
        >
    </div>
</div>
