@props([
    'id',
    'label',
    'name',
    'value' => '',
    'min' => '',
    'role' => 'depart',
    'extraClass' => '',
    'hidden' => false,
])

@php
    $placeholders = [
        'depart' => 'Departure',
        'return' => 'Return',
        'group_from' => 'From date',
        'group_to' => 'To date',
        'multi_depart' => 'Departure',
    ];
    $placeholder = $placeholders[$role] ?? 'Date';
    $displayValue = '';
    if ($value !== '') {
        try {
            $displayValue = \Illuminate\Support\Carbon::parse($value)->format('d M');
        } catch (\Throwable) {
            $displayValue = '';
        }
    }
@endphp

<div
    class="field {{ $extraClass }} jp-date-field"
    data-jp-date-field
    data-jp-date-role="{{ $role }}"
    data-jp-date-placeholder="{{ $placeholder }}"
    @if($role === 'return') data-jp-return-field @endif
    @if($hidden) hidden @endif
>
    <label id="{{ $id }}-label">{{ $label }}</label>
    <div class="jp-field-value-row">
        <x-jp.icon name="calendar" class="icon" />
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
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ $value }}"
            data-jp-date-value
            @if($min !== '') data-jp-date-min="{{ $min }}" @endif
        >
    </div>
</div>
