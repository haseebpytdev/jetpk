@props([
    'id',
    'label',
    'displayName',
    'hiddenName',
    'displayValue' => '',
    'codeValue' => '',
    'role' => 'from',
    'placeholder' => 'City or airport',
    'required' => false,
])

<div class="field jp-airport-field" data-jp-airport-field>
    <label for="{{ $id }}-display">{{ $label }}</label>
    <div class="jp-field-value-row">
        <x-jp.icon name="map-pin" class="icon" />
        <input
            type="text"
            id="{{ $id }}-display"
            name="{{ $displayName }}"
            class="jp-airport-display"
            data-jp-airport-display="{{ $role }}"
            data-jp-airport-input
            value="{{ $displayValue }}"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            role="combobox"
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="{{ $id }}-suggest"
            @if($required) required @endif
        >
    </div>
    <input
        type="hidden"
        id="{{ $id }}-code"
        name="{{ $hiddenName }}"
        data-jp-airport-code="{{ $role }}"
        value="{{ $codeValue }}"
    >
    <div class="jp-airport-suggest" id="{{ $id }}-suggest" role="listbox" aria-label="{{ $label }} suggestions" hidden></div>
</div>
