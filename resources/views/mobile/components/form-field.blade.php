@props([
    'name',
    'label',
    'type' => 'text',
    'id' => null,
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'inputmode' => null,
    'placeholder' => null,
    'invalid' => false,
])

@php
    $fieldId = $id ?? $name;
    $fieldValue = $value ?? old($name);
    $hasError = $invalid || $errors->has($name);
@endphp

<div class="ota-mobile-auth__field">
    <label class="ota-mobile-auth__label" for="{{ $fieldId }}">{{ $label }}</label>
    <input
        id="{{ $fieldId }}"
        class="ota-mobile-auth__input{{ $hasError ? ' is-invalid' : '' }}"
        type="{{ $type }}"
        name="{{ $name }}"
        value="{{ $fieldValue }}"
        @if($required) required @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($inputmode) inputmode="{{ $inputmode }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        {{ $attributes->except(['type', 'name', 'value', 'label', 'id', 'required', 'autocomplete', 'inputmode', 'placeholder', 'invalid']) }}
    />
    @error($name)
        <p class="ota-mobile-auth__error">{{ $message }}</p>
    @enderror
</div>
