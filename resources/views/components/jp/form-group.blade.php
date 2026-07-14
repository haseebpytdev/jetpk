@props([
    'label',
    'for' => null,
    'error' => null,
    'hint' => null,
])

<div {{ $attributes->class(['jp-form-group']) }}>
  <label class="jp-label" @if($for) for="{{ $for }}" @endif>{{ $label }}</label>
  {{ $slot }}
  @if($hint)
    <p class="jp-field-hint">{{ $hint }}</p>
  @endif
  @if($error)
    <p class="jp-field-error">{{ $error }}</p>
  @endif
</div>
